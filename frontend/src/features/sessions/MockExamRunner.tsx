import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Timer } from 'lucide-react';
import { Progress } from '@/components/ui/progress';
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

  const question = session.questions[index];
  const isLast = index === session.questions.length - 1;
  const progressPercent = Math.round(((index + (revealed ? 1 : 0)) / session.questions.length) * 100);
  const { elapsedMs } = useQuestionTimer(question?.id);

  const finishExam = async () => {
    await completeSession.mutateAsync();
    navigate(`/session/${session.id}/report`);
  };

  useEffect(() => {
    if (!session.time_limit_seconds || expired) return;
    const id = setInterval(() => {
      setSecondsRemaining((s) => {
        if (s <= 1) {
          clearInterval(id);
          setExpired(true);
          return 0;
        }
        return s - 1;
      });
    }, 1000);
    return () => clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session.time_limit_seconds]);

  useEffect(() => {
    if (expired) {
      finishExam();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [expired]);

  if (!question || expired) {
    return null;
  }

  const handleSelect = async (key: string) => {
    if (revealed) return;
    setSelected(key);
    const result = await submitAnswer.mutateAsync({
      questionId: question.id,
      selectedOptionKey: key,
      responseTimeMs: elapsedMs(),
    });
    setRevealed({ isCorrect: result.is_correct, correctKey: result.correct_option_key });
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
