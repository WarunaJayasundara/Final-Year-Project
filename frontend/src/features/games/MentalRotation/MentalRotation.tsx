import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { GameResultCard } from '../GameResultCard';
import { useSubmitGameScore } from '../useGames';
import { GRID_SIZE, generateRound, type Cell, type RotationRound } from './generator';

const TOTAL_ROUNDS = 8;

function ShapeGrid({ cells, highlight }: { cells: Cell[]; highlight?: 'correct' | 'wrong' }) {
  const filled = new Set(cells.map(([r, c]) => `${r},${c}`));

  return (
    <div
      className="grid gap-0.5"
      style={{ gridTemplateColumns: `repeat(${GRID_SIZE}, minmax(0, 1fr))` }}
    >
      {Array.from({ length: GRID_SIZE * GRID_SIZE }, (_, i) => {
        const r = Math.floor(i / GRID_SIZE);
        const c = i % GRID_SIZE;
        const isFilled = filled.has(`${r},${c}`);
        return (
          <span
            key={i}
            className={`aspect-square rounded-sm ${
              isFilled
                ? highlight === 'correct'
                  ? 'bg-emerald-500'
                  : highlight === 'wrong'
                    ? 'bg-destructive'
                    : 'bg-primary'
                : 'bg-muted'
            }`}
          />
        );
      })}
    </div>
  );
}

export function MentalRotation() {
  const { t } = useTranslation('games');
  const [round, setRound] = useState(1);
  const [current, setCurrent] = useState<RotationRound>(() => generateRound());
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [correctCount, setCorrectCount] = useState(0);
  const [startedAt] = useState(Date.now());
  const [finished, setFinished] = useState(false);
  const [result, setResult] = useState<{ score: number; bestScore?: number; isNewBest?: boolean } | null>(null);

  const submitScore = useSubmitGameScore('mental_rotation');

  useEffect(() => {
    if (!finished) return;
    const seconds = Math.round((Date.now() - startedAt) / 1000);
    const score = Math.max(0, correctCount * 100 - Math.floor(seconds / 2));
    submitScore.mutate(
      { score, durationSeconds: seconds, metadata: { correctCount, rounds: TOTAL_ROUNDS } },
      { onSuccess: (data) => setResult({ score, bestScore: data.best_score, isNewBest: data.is_new_best }) },
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [finished]);

  const handleSelect = (optionId: number, isCorrect: boolean) => {
    if (selectedId !== null) return;
    setSelectedId(optionId);
    if (isCorrect) {
      setCorrectCount((c) => c + 1);
    }

    setTimeout(() => {
      if (round >= TOTAL_ROUNDS) {
        setFinished(true);
        return;
      }
      setRound((r) => r + 1);
      setCurrent(generateRound());
      setSelectedId(null);
    }, 700);
  };

  const reset = () => {
    setRound(1);
    setCurrent(generateRound());
    setSelectedId(null);
    setCorrectCount(0);
    setFinished(false);
    setResult(null);
  };

  if (finished && result) {
    return (
      <GameResultCard score={result.score} bestScore={result.bestScore} isNewBest={result.isNewBest} onPlayAgain={reset} />
    );
  }

  return (
    <div className="mx-auto flex max-w-lg flex-col gap-6">
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span>{t('mentalRotation.round', { current: round, total: TOTAL_ROUNDS })}</span>
          <span>{t('mentalRotation.correct', { count: correctCount })}</span>
        </div>
        <Progress value={((round - 1) / TOTAL_ROUNDS) * 100} />
      </div>

      <Card>
        <CardContent className="flex flex-col items-center gap-6 p-8">
          <p className="text-sm text-muted-foreground">{t('mentalRotation.prompt')}</p>
          <div className="w-28">
            <ShapeGrid cells={current.target} />
          </div>

          <div className="grid w-full grid-cols-2 gap-4">
            {current.options.map((option) => {
              const isSelected = selectedId === option.id;
              const revealCorrect = selectedId !== null && option.isCorrect;
              const revealWrong = isSelected && !option.isCorrect;
              return (
                <button
                  key={option.id}
                  type="button"
                  disabled={selectedId !== null}
                  onClick={() => handleSelect(option.id, option.isCorrect)}
                  className={`rounded-xl border p-3 transition-colors ${
                    revealCorrect
                      ? 'border-emerald-500 bg-emerald-500/10'
                      : revealWrong
                        ? 'border-destructive bg-destructive/10'
                        : 'border-border hover:bg-muted disabled:opacity-70'
                  }`}
                >
                  <ShapeGrid cells={option.cells} highlight={revealCorrect ? 'correct' : revealWrong ? 'wrong' : undefined} />
                </button>
              );
            })}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
