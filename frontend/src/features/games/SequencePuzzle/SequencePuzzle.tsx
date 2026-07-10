import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { GameResultCard } from '../GameResultCard';
import { useSubmitGameScore } from '../useGames';
import { generateRound, type SequenceRound } from './generator';

const TOTAL_ROUNDS = 10;

export function SequencePuzzle() {
  const { t } = useTranslation('games');
  const [round, setRound] = useState(1);
  const [current, setCurrent] = useState<SequenceRound>(() => generateRound(1));
  const [selected, setSelected] = useState<number | null>(null);
  const [correctCount, setCorrectCount] = useState(0);
  const [startedAt] = useState(Date.now());
  const [finished, setFinished] = useState(false);
  const [result, setResult] = useState<{ score: number; bestScore?: number; isNewBest?: boolean } | null>(null);

  const submitScore = useSubmitGameScore('sequence_puzzle');

  useEffect(() => {
    if (!finished) return;
    const seconds = Math.round((Date.now() - startedAt) / 1000);
    const rawScore = correctCount * 100 - Math.floor(seconds / 2);
    const score = Math.max(0, rawScore);
    submitScore.mutate(
      { score, durationSeconds: seconds, metadata: { correctCount, rounds: TOTAL_ROUNDS } },
      { onSuccess: (data) => setResult({ score, bestScore: data.best_score, isNewBest: data.is_new_best }) },
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [finished]);

  const handleSelect = (value: number) => {
    if (selected !== null) return;
    setSelected(value);
    if (value === current.answer) {
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
      setSelected(null);
    }, 600);
  };

  const reset = () => {
    setRound(1);
    setCurrent(generateRound(1));
    setSelected(null);
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
          <span>{t('sequencePuzzle.round', { current: round, total: TOTAL_ROUNDS })}</span>
          <span>{t('sequencePuzzle.correct', { count: correctCount })}</span>
        </div>
        <Progress value={((round - 1) / TOTAL_ROUNDS) * 100} />
      </div>

      <Card>
        <CardContent className="flex flex-col items-center gap-6 p-8">
          <p className="text-sm text-muted-foreground">{t('sequencePuzzle.prompt')}</p>
          <div className="flex items-center gap-3 text-2xl font-semibold">
            {current.sequence.map((n, i) => (
              <span key={i}>{n}</span>
            ))}
            <span className="text-primary">?</span>
          </div>

          <div className="grid w-full grid-cols-2 gap-3">
            {current.options.map((option) => {
              const isSelected = selected === option;
              const isCorrectOption = selected !== null && option === current.answer;
              return (
                <button
                  key={option}
                  type="button"
                  disabled={selected !== null}
                  onClick={() => handleSelect(option)}
                  className={`rounded-xl border p-4 text-lg font-medium transition-colors ${
                    isCorrectOption
                      ? 'border-emerald-500 bg-emerald-50 text-emerald-900'
                      : isSelected
                        ? 'border-destructive bg-destructive/10 text-destructive'
                        : 'border-border hover:bg-muted disabled:opacity-70'
                  }`}
                >
                  {option}
                </button>
              );
            })}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
