import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Progress } from '@/components/ui/progress';
import { useCompleteSession, useSubmitAnswer } from './useSessions';
import { useQuestionTimer } from './useQuestionTimer';
import { QuestionCard, type RevealState } from './QuestionCard';
import type { AdaptiveSessionData, SessionQuestion } from './types';

/**
 * Drives the placement test's real computerized-adaptive-testing (CAT) flow:
 * unlike SessionRunner (which paginates through a fixed pre-loaded question
 * array), each answer here is submitted individually and the backend responds
 * with the *next adaptively-selected question* (or a signal that the test has
 * gathered enough information to stop) - see TestSessionController's
 * handleAdaptiveAnswer for the Rasch-model item selection driving this.
 */
export function AdaptivePlacementRunner({ session }: { session: AdaptiveSessionData }) {
  const { t } = useTranslation(['common', 'sessions']);
  const navigate = useNavigate();
  const submitAnswer = useSubmitAnswer(session.id);
  const completeSession = useCompleteSession(session.id);

  const [question, setQuestion] = useState<SessionQuestion>(session.current_question);
  const [pendingNextQuestion, setPendingNextQuestion] = useState<SessionQuestion | null>(null);
  const [itemsAnswered, setItemsAnswered] = useState(session.items_answered);
  const [selected, setSelected] = useState<string | null>(null);
  const [revealed, setRevealed] = useState<RevealState | null>(null);
  const [readyToComplete, setReadyToComplete] = useState(false);
  const { elapsedMs } = useQuestionTimer(question.id);

  const progressPercent = Math.min(100, Math.round((itemsAnswered / session.max_items) * 100));

  const handleSelect = async (key: string) => {
    if (revealed) return;
    setSelected(key);
    const result = await submitAnswer.mutateAsync({
      questionId: question.id,
      selectedOptionKey: key,
      responseTimeMs: elapsedMs(),
    });
    // Reveal correctness for the question just answered, but hold the next
    // question in reserve - handleNext() advances to it once the student has
    // seen the reveal and clicked through, matching SessionRunner's pacing.
    setRevealed({ isCorrect: result.is_correct, correctKey: result.correct_option_key });
    setItemsAnswered(result.items_answered ?? itemsAnswered + 1);
    setReadyToComplete(!!result.ready_to_complete);
    setPendingNextQuestion(result.next_question ?? null);
  };

  const handleNext = async () => {
    if (readyToComplete) {
      await completeSession.mutateAsync();
      navigate(`/session/${session.id}/report`);
      return;
    }

    if (pendingNextQuestion) {
      setQuestion(pendingNextQuestion);
    }
    setPendingNextQuestion(null);
    setSelected(null);
    setRevealed(null);
  };

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span className="font-medium text-foreground">{t('types.placement', { ns: 'sessions' })}</span>
          <span>{t('placement.progress', { ns: 'sessions', current: itemsAnswered + 1, max: session.max_items })}</span>
        </div>
        <Progress value={progressPercent} />
        <p className="text-xs text-muted-foreground">{t('placement.adaptiveNote', { ns: 'sessions' })}</p>
      </div>

      <QuestionCard
        question={question}
        selected={selected}
        revealed={revealed}
        onSelect={handleSelect}
        onAdvance={handleNext}
        advanceDisabled={!revealed || completeSession.isPending}
        advanceLabel={readyToComplete ? t('actions.finish') : t('actions.next')}
      />
    </div>
  );
}
