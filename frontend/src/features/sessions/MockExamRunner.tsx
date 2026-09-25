import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Timer } from 'lucide-react';
import { toast } from 'sonner';
import { Progress } from '@/components/ui/progress';
import { ErrorState } from '@/components/ui/error-state';
import { InlineLoader } from '@/components/brand/BrandLoader';
import { apiErrorMessage } from '@/lib/apiError';
import { useCompleteSession, useSubmitAnswer } from './useSessions';
import { useQuestionTimer } from './useQuestionTimer';
import { QuestionCard, type RevealState } from './QuestionCard';
import type { SessionData } from './types';

function formatClock(totalSeconds: number): string {
  const m = Math.floor(totalSeconds / 60);
  const s = totalSeconds % 60;
  return `${m}:${String(s).padStart(2, '0')}`;
}

/**
 * A mock exam is a real TestSession like any other (see MockExamController)
 * with one difference the UI must actually enforce: time_limit_seconds. This
 * is the first countdown timer of this kind in the test-taking flow -
 * SessionRunner/AdaptivePlacementRunner have no time pressure at all. On
 * expiry, the exam auto-submits/locks exactly where the student is (brief
 * §13/§10: real exam conditions, not an untimed practice set). The countdown
 * stays calm (neutral border) until the final 60 seconds, when it shifts to
 * a warning tone - not a constant red pulse for the whole exam.
 */
export function MockExamRunner({ session }: { session: SessionData }) {
  const { t } = useTranslation(['common', 'sessions']);
  const navigate = useNavigate();
  const submitAnswer = useSubmitAnswer(session.id);
  const completeSession = useCompleteSession(session.id);

  const [index, setIndex] = useState(0);
  const [selected, setSelected] = useState<string | null>(null);
  const [revealed, setRevealed] = useState<RevealState | null>(null);
  const [secondsRemaining, setSecondsRemaining] = useState(session.time_limit_seconds ?? 0);
  const [expired, setExpired] = useState(false);
  const [finishFailed, setFinishFailed] = useState(false);

  const question = session.questions[index];
  const isLast = index === session.questions.length - 1;
  const progressPercent = Math.round(((index + (revealed ? 1 : 0)) / session.questions.length) * 100);
  const { elapsedMs } = useQuestionTimer(question?.id);

  const finishExam = async () => {
    setFinishFailed(false);
    try {
      await completeSession.mutateAsync();
    } catch (error) {
      // Never leave the student on a dead screen: say what happened and let them retry (completing is idempotent).
      setFinishFailed(true);
      toast.error(apiErrorMessage(error, t));
      return;
    }
    navigate(`/session/${session.id}/report`);
  };

  useEffect(() => {
    if (!session.time_limit_seconds || expired) return;
    // Count against a wall-clock deadline instead of "one tick = one second": timers stop while a phone is
    // locked or a tab sleeps, which would silently hand the student extra exam time.
    const deadline = Date.now() + session.time_limit_seconds * 1000;
    const id = setInterval(() => {
      const left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
      setSecondsRemaining(left);
      if (left <= 0) {
        clearInterval(id);
        setExpired(true);
      }
    }, 500);
    return () => clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session.time_limit_seconds]);

  useEffect(() => {
    if (expired) {
      finishExam();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [expired]);

  if (expired) {
    return finishFailed ? (
      <ErrorState onRetry={finishExam} />
    ) : (
      <div className="flex min-h-[40vh] items-center justify-center">
        <InlineLoader />
      </div>
    );
  }

  if (!question) {
    return null;
  }

  const handleSelect = async (key: string) => {
    if (revealed || submitAnswer.isPending) return;
    setSelected(key);
    try {
      const result = await submitAnswer.mutateAsync({
        questionId: question.id,
        selectedOptionKey: key,
        responseTimeMs: elapsedMs(),
      });
      setRevealed({ isCorrect: result.is_correct, correctKey: result.correct_option_key });
    } catch (error) {
      // Not saved: unmark the choice so the student can answer again instead of being stuck.
      setSelected(null);
      toast.error(apiErrorMessage(error, t));
    }
  };

  const handleNext = async () => {
    if (isLast) {
      await finishExam();
      return;
    }
    setIndex((i) => i + 1);
    setSelected(null);
    setRevealed(null);
  };

  const lowTime = session.time_limit_seconds != null && secondsRemaining <= 60;

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span className="font-medium text-foreground">{t('types.mock', { ns: 'sessions' })}</span>
          <div className="flex items-center gap-3">
            <span>
              {index + 1} / {session.questions.length}
            </span>
            {session.time_limit_seconds != null && (
              <span
                className={`flex items-center gap-1 rounded-full border px-2 py-0.5 font-semibold tabular-nums ${
                  lowTime ? 'border-warning/40 bg-warning/15 text-warning-foreground' : 'border-border'
                }`}
              >
                <Timer className="h-3.5 w-3.5" /> {formatClock(secondsRemaining)}
              </span>
            )}
          </div>
        </div>
        <Progress value={progressPercent} />
      </div>

      <QuestionCard
        question={question}
        selected={selected}
        revealed={revealed}
        onSelect={handleSelect}
        onAdvance={handleNext}
        advanceDisabled={!revealed || completeSession.isPending}
        advanceLabel={isLast ? t('actions.finish') : t('actions.next')}
        showExpectedTime
      />
    </div>
  );
}
