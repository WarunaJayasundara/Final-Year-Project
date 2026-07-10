import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowUp } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { GameResultCard } from '../GameResultCard';
import { useSubmitGameScore } from '../useGames';
import { generateRound, type AttentionRound } from './generator';

const TOTAL_ROUNDS = 8;

export function SelectiveAttention() {
  const { t } = useTranslation('games');
  const [round, setRound] = useState(1);
  const [current, setCurrent] = useState<AttentionRound>(() => generateRound(1));
  const [selectedIndex, setSelectedIndex] = useState<number | null>(null);
  const [correctCount, setCorrectCount] = useState(0);
  const [startedAt] = useState(Date.now());
  const [finished, setFinished] = useState(false);
  const [result, setResult] = useState<{ score: number; bestScore?: number; isNewBest?: boolean } | null>(null);

  const submitScore = useSubmitGameScore('selective_attention');

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

  const handleSelect = (index: number) => {
    if (selectedIndex !== null) return;
    setSelectedIndex(index);
    if (index === current.targetIndex) {
      setCorrectCount((c) => c + 1);
    }

    setTimeout(() => {
      if (round >= TOTAL_ROUNDS) {
        setFinished(true);
        return;
      }
      const nextRound = round + 1;
      setRound(nextRound);
      setCurrent(generateRound(nextRound));
      setSelectedIndex(null);
    }, 500);
  };

  const reset = () => {
    setRound(1);
    setCurrent(generateRound(1));
    setSelectedIndex(null);
    setCorrectCount(0);
    setFinished(false);
    setResult(null);
  };

  if (finished && result) {
    return (
      <GameResultCard score={result.score} bestScore={result.bestScore} isNewBest={result.isNewBest} onPlayAgain={reset} />
    );
  }

  const cellCount = current.gridSize * current.gridSize;

  return (
    <div className="mx-auto flex max-w-lg flex-col gap-6">
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span>{t('selectiveAttention.round', { current: round, total: TOTAL_ROUNDS })}</span>
          <span>{t('selectiveAttention.correct', { count: correctCount })}</span>
        </div>
        <Progress value={((round - 1) / TOTAL_ROUNDS) * 100} />
      </div>

      <Card>
        <CardContent className="flex flex-col items-center gap-6 p-8">
          <p className="text-sm text-muted-foreground">{t('selectiveAttention.prompt')}</p>
          <div
            className="grid gap-2"
            style={{ gridTemplateColumns: `repeat(${current.gridSize}, minmax(0, 1fr))` }}
          >
            {Array.from({ length: cellCount }, (_, index) => {
              const isTarget = index === current.targetIndex;
              const rotation = isTarget ? current.targetRotationDeg : current.baseRotationDeg;
              const isSelected = selectedIndex === index;
              const revealWrong = isSelected && !isTarget;
              const revealCorrect = selectedIndex !== null && isTarget;

              return (
                <button
                  key={index}
                  type="button"
                  disabled={selectedIndex !== null}
                  onClick={() => handleSelect(index)}
                  className={`flex aspect-square items-center justify-center rounded-lg border transition-colors ${
                    revealCorrect
                      ? 'border-emerald-500 bg-emerald-500/10'
                      : revealWrong
                        ? 'border-destructive bg-destructive/10'
                        : 'border-border hover:bg-muted'
                  }`}
                >
                  <ArrowUp
                    className="h-5 w-5 text-primary"
                    style={{ transform: `rotate(${rotation}deg)` }}
                  />
                </button>
              );
            })}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
