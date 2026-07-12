import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Progress } from '@/components/ui/progress';
import { useCompleteSession, useSubmitAnswer } from './useSessions';
import { useQuestionTimer } from './useQuestionTimer';
import { QuestionCard, type RevealState } from './QuestionCard';
import type { SessionData } from './types';

export function SessionRunner({ session }: { session: SessionData }) {
  const { t } = useTranslation(['common', 'sessions']);
  const navigate = useNavigate();
  const submitAnswer = useSubmitAnswer(session.id);
  const completeSession = useCompleteSession(session.id);

  const [index, setIndex] = useState(0);
  const [selected, setSelected] = useState<string | null>(null);
  const [revealed, setRevealed] = useState<RevealState | null>(null);

  const question = session.questions[index];
  const { elapsedMs } = useQuestionTimer(question?.id);
  const isLast = index === session.questions.length - 1;
  const progressPercent = Math.round(((index + (revealed ? 1 : 0)) / session.questions.length) * 100);

  if (!question) {
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
      await completeSession.mutateAsync();
      navigate(`/session/${session.id}/report`);
      return;
    }
    setIndex((i) => i + 1);
    setSelected(null);
    setRevealed(null);
  };

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span className="font-medium text-foreground">{t(`types.${session.session_type}`, { ns: 'sessions' })}</span>
          <span>
            {index + 1} / {session.questions.length}
          </span>
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
      />
    </div>
  );
}
