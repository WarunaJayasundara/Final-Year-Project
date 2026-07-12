import { useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { CheckCircle2, Timer, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { SessionQuestion } from './types';

export interface RevealState {
  isCorrect: boolean;
  correctKey: string;
}

interface QuestionCardProps {
  question: SessionQuestion;
  selected: string | null;
  revealed: RevealState | null;
  onSelect: (key: string) => void;
  onAdvance: () => void;
  advanceDisabled: boolean;
  advanceLabel: string;
  /** Only timed contexts (mock exams, explicit timed challenges) show the
   * expected-time chip - ordinary practice/daily/placement questions still
   * record response_time_ms silently in the background, they just don't
   * display it, so students aren't put under unnecessary timer pressure. */
  showExpectedTime?: boolean;
}

const OPTION_KEYS_ORDER = ['A', 'B', 'C', 'D', 'E', 'F'];

/**
 * The shared question-answering surface for SessionRunner, AdaptivePlacementRunner,
 * and MockExamRunner - previously duplicated near-verbatim across all three.
 * Adds: a larger image area for visual questions, a live elapsed-time chip
 * (quiet by default, only when the question sets an expected_time_seconds),
 * keyboard support (1-6/A-F to pick an option, Enter/Space to advance once
 * revealed), and token-driven success/destructive colors instead of hardcoded
 * emerald classes.
 */
export function QuestionCard({
  question,
  selected,
  revealed,
  onSelect,
  onAdvance,
  advanceDisabled,
  advanceLabel,
  showExpectedTime = false,
}: QuestionCardProps) {
  const { t } = useTranslation('sessions');

  const imageUrl = useMemo(
    () => (question.image_path ? `/storage/${question.image_path}` : null),
    [question.image_path],
  );

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;

      if (!revealed) {
        const digitIndex = Number(e.key) - 1;
        const letterIndex = OPTION_KEYS_ORDER.indexOf(e.key.toUpperCase());
        const index = e.key >= '1' && e.key <= '6' ? digitIndex : letterIndex;
        if (index >= 0 && index < question.options.length) {
          onSelect(question.options[index].key);
        }
      } else if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (!advanceDisabled) onAdvance();
      }
    };

    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [question.options, revealed, advanceDisabled, onSelect, onAdvance]);

  return (
    <Card>
      <CardContent className="flex flex-col gap-6 p-6">
        {imageUrl && (
          <img
            src={imageUrl}
            alt=""
            className="mx-auto max-h-80 rounded-lg border border-border object-contain sm:max-h-[28rem]"
          />
        )}
        <div className="flex items-start justify-between gap-3">
          <p className="text-lg font-medium leading-relaxed">{question.question_text}</p>
          {showExpectedTime && question.expected_time_seconds > 0 && (
            <span className="flex shrink-0 items-center gap-1 rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground">
              <Timer className="h-3 w-3" /> ~{question.expected_time_seconds}s
            </span>
          )}
        </div>

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
                onClick={() => onSelect(option.key)}
                className={`flex items-center gap-3 rounded-xl border p-4 text-left text-sm font-medium transition-colors ${
                  isCorrectOption
                    ? 'border-success bg-success/10 text-success'
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

        <div className="flex items-center justify-between">
          <p className="text-xs text-muted-foreground">{t('keyboardHint')}</p>
          <Button onClick={onAdvance} disabled={advanceDisabled}>
            {advanceLabel}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
