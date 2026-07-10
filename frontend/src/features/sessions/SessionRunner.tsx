import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { CheckCircle2, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useCompleteSession, useSubmitAnswer } from './useSessions';
import type { SessionData } from './types';

export function SessionRunner({ session }: { session: SessionData }) {
  const { t } = useTranslation(['common', 'sessions']);
  const navigate = useNavigate();
  const submitAnswer = useSubmitAnswer(session.id);
  const completeSession = useCompleteSession(session.id);

  const [index, setIndex] = useState(0);
  const [selected, setSelected] = useState<string | null>(null);
  const [revealed, setRevealed] = useState<{ isCorrect: boolean; correctKey: string } | null>(null);

  const question = session.questions[index];
  const isLast = index === session.questions.length - 1;
  const progressPercent = Math.round(((index + (revealed ? 1 : 0)) / session.questions.length) * 100);

  const imageUrl = useMemo(
    () => (question?.image_path ? `/storage/${question.image_path}` : null),
    [question?.image_path],
  );

  if (!question) {
    return null;
  }

  const handleSelect = async (key: string) => {
    if (revealed) return;
    setSelected(key);
    const result = await submitAnswer.mutateAsync({ questionId: question.id, selectedOptionKey: key });
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
              {isLast ? t('actions.finish') : t('actions.next')}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
