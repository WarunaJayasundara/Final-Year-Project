import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { CheckCircle2, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useCompleteSession, useSubmitAnswer } from './useSessions';
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
  const [revealed, setRevealed] = useState<{ isCorrect: boolean; correctKey: string } | null>(null);
  const [readyToComplete, setReadyToComplete] = useState(false);

  const progressPercent = Math.min(100, Math.round((itemsAnswered / session.max_items) * 100));

  const imageUrl = useMemo(
    () => (question.image_path ? `/storage/${question.image_path}` : null),
    [question.image_path],
  );

  const handleSelect = async (key: string) => {
    if (revealed) return;
    setSelected(key);
    const result = await submitAnswer.mutateAsync({ questionId: question.id, selectedOptionKey: key });
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

      <Card>
        <CardContent className="flex flex-col gap-6 p-6">
          {imageUrl && (
            <img src={imageUrl} alt="" className="mx-auto max-h-64 rounded-lg border border-border object-contain" />
          )}
          <p className="text-lg font-medium leading-relaxed">{question.question_text}</p>

          <div className="grid gap-3 sm:grid-cols-2">
            {question.options.map((option) => {
              const isSelected = selected === option.key;
              const isCorrectOption = revealed && option.key === revealed.correctKey;
              const isWrongSelected = revealed && isSelected && !revealed.isCorrect;

              return (
                <button
                  key={option.key}
                  type="button"
                  disabled={!!revealed}
                  onClick={() => handleSelect(option.key)}
                  className={`flex items-center gap-3 rounded-xl border p-4 text-left text-sm font-medium transition-colors ${
                    isCorrectOption
                      ? 'border-emerald-500 bg-emerald-50 text-emerald-900'
                      : isWrongSelected
                        ? 'border-destructive bg-destructive/10 text-destructive'
                        : isSelected
                          ? 'border-primary bg-primary/5'
                          : 'border-border hover:bg-muted disabled:opacity-70'
                  }`}
                >
                  <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-current text-xs">
                    {option.key}
                  </span>
                  {option.image_path ? (
                    <img src={`/storage/${option.image_path}`} alt="" className="h-12 w-12 object-contain" />
                  ) : (
                    <span>{option.text}</span>
                  )}
                  {isCorrectOption && <CheckCircle2 className="ml-auto h-4 w-4" />}
                  {isWrongSelected && <XCircle className="ml-auto h-4 w-4" />}
                </button>
              );
            })}
          </div>

          <div className="flex justify-end">
            <Button onClick={handleNext} disabled={!revealed || completeSession.isPending}>
              {readyToComplete ? t('actions.finish') : t('actions.next')}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
