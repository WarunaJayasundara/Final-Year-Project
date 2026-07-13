import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Button } from '@/components/ui/button';
import { GameResultCard } from '../GameResultCard';
import { useSubmitGameScore } from '../useGames';
import {
  generateSceneRound,
  generatePathRound,
  MIN_ITEMS,
  MAX_ITEMS,
  MIN_PATH_SPAN,
  MAX_PATH_SPAN,
  type MemoryRound,
  type SceneRound,
  type PathRound,
} from './generator';

type Phase = 'scene-exposure' | 'scene-question' | 'path-flash' | 'path-recall' | 'feedback' | 'finished';

const ROUND_ROTATION: MemoryRound['type'][] = ['scene', 'path', 'scene', 'path', 'scene', 'path'];
const FLASH_STEP_MS = 700;

function shuffled<T>(items: T[]): T[] {
  const arr = [...items];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

interface RoundLog {
  type: MemoryRound['type'];
  correct: boolean;
}

export function VisualSpatialMemory() {
  const { t } = useTranslation('games');
  const [roundIndex, setRoundIndex] = useState(0);
  const [itemCount, setItemCount] = useState(MIN_ITEMS);
  const [pathSpan, setPathSpan] = useState(MIN_PATH_SPAN);
  const [consecutiveCorrect, setConsecutiveCorrect] = useState(0);
  const [round, setRound] = useState<MemoryRound>(() => generateSceneRound(MIN_ITEMS, 1));
  const [phase, setPhase] = useState<Phase>('scene-exposure');
  const [flashStep, setFlashStep] = useState(0);
  const [pathInput, setPathInput] = useState<number[]>([]);
  const [lastCorrect, setLastCorrect] = useState<boolean | null>(null);
  const [logs, setLogs] = useState<RoundLog[]>([]);
  const [startedAt] = useState(Date.now());
  const [result, setResult] = useState<{ score: number; bestScore?: number; isNewBest?: boolean } | null>(null);

  const submitScore = useSubmitGameScore('visual_spatial_memory', {
    onSuccess: (data) => setResult((prev) => (prev ? { ...prev, bestScore: data.best_score, isNewBest: data.is_new_best } : prev)),
  });

  useEffect(() => {
    if (phase !== 'scene-exposure') return;
    const timer = setTimeout(() => setPhase('scene-question'), 2500 + itemCount * 350);
    return () => clearTimeout(timer);
  }, [phase, itemCount]);

  useEffect(() => {
    if (phase !== 'path-flash' || round.type !== 'path') return;
    if (flashStep >= round.sequence.length) {
      setPhase('path-recall');
      return;
    }
    const timer = setTimeout(() => setFlashStep((s) => s + 1), FLASH_STEP_MS);
    return () => clearTimeout(timer);
  }, [phase, flashStep, round]);

  const advance = (correct: boolean) => {
    setLastCorrect(correct);
    setLogs((prev) => [...prev, { type: round.type, correct }]);
    const newConsecutive = correct ? consecutiveCorrect + 1 : 0;
    const newItemCount = correct && consecutiveCorrect >= 1 ? Math.min(MAX_ITEMS, itemCount + 1) : correct ? itemCount : Math.max(MIN_ITEMS, itemCount - 1);
    const newPathSpan = correct && consecutiveCorrect >= 1 ? Math.min(MAX_PATH_SPAN, pathSpan + 1) : correct ? pathSpan : Math.max(MIN_PATH_SPAN, pathSpan - 1);
    setItemCount(newItemCount);
    setPathSpan(newPathSpan);
    setConsecutiveCorrect(newConsecutive);
    setPhase('feedback');

    setTimeout(() => {
      const next = roundIndex + 1;
      if (next >= ROUND_ROTATION.length) {
        setPhase('finished');
        return;
      }
      const nextType = ROUND_ROTATION[next];
      setRoundIndex(next);
      setFlashStep(0);
      setPathInput([]);
      setLastCorrect(null);
      if (nextType === 'scene') {
        setRound(generateSceneRound(newItemCount, 100 * (next + 1) + newItemCount));
        setPhase('scene-exposure');
      } else {
        setRound(generatePathRound(newPathSpan, 100 * (next + 1) + newPathSpan));
        setPhase('path-flash');
      }
    }, 900);
  };

  const handleSceneAnswer = (given: string | number) => {
    if (round.type !== 'scene') return;
    advance(given === round.answer);
  };

  const handlePathTap = (cell: number) => {
    if (phase !== 'path-recall' || round.type !== 'path') return;
    const next = [...pathInput, cell];
    setPathInput(next);
    if (next.length === round.sequence.length) {
      const correct = round.sequence.every((c, i) => c === next[i]);
      advance(correct);
    }
  };

  useEffect(() => {
    if (phase !== 'finished') return;
    const seconds = Math.round((Date.now() - startedAt) / 1000);
    const correctCount = logs.filter((l) => l.correct).length;
    const maxItems = Math.max(MIN_ITEMS, itemCount);
    const maxSpan = Math.max(MIN_PATH_SPAN, pathSpan);
    const score = Math.max(0, correctCount * 80 + maxItems * 10 + maxSpan * 10 - Math.floor(seconds / 4));
    const sceneLogs = logs.filter((l) => l.type === 'scene');
    const pathLogs = logs.filter((l) => l.type === 'path');
    setResult({ score });
    submitScore.mutate({
      score,
      durationSeconds: seconds,
      metadata: {
        items_reached: maxItems,
        path_span_reached: maxSpan,
        scene_accuracy: sceneLogs.length ? sceneLogs.filter((l) => l.correct).length / sceneLogs.length : null,
        path_accuracy: pathLogs.length ? pathLogs.filter((l) => l.correct).length / pathLogs.length : null,
        rounds: logs.length,
      },
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase]);

  const reset = () => {
    setRoundIndex(0);
    setItemCount(MIN_ITEMS);
    setPathSpan(MIN_PATH_SPAN);
    setConsecutiveCorrect(0);
    setRound(generateSceneRound(MIN_ITEMS, 1));
    setPhase('scene-exposure');
    setFlashStep(0);
    setPathInput([]);
    setLastCorrect(null);
    setLogs([]);
    setResult(null);
  };

  if (phase === 'finished' && result) {
    return <GameResultCard score={result.score} bestScore={result.bestScore} isNewBest={result.isNewBest} onPlayAgain={reset} />;
  }

  return (
    <div className="mx-auto flex max-w-lg flex-col gap-6">
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span>{t('visualSpatialMemory.round', { current: roundIndex + 1, total: ROUND_ROTATION.length })}</span>
        </div>
        <Progress value={(roundIndex / ROUND_ROTATION.length) * 100} />
      </div>

      <Card>
        <CardContent className="flex flex-col items-center gap-6 p-8">
          {round.type === 'scene' && (phase === 'scene-exposure' || phase === 'scene-question') && (
            <SceneView round={round} phase={phase} onAnswer={handleSceneAnswer} />
          )}

          {round.type === 'path' && (phase === 'path-flash' || phase === 'path-recall') && (
            <PathView round={round} phase={phase} flashStep={flashStep} input={pathInput} onTap={handlePathTap} />
          )}

          {phase === 'feedback' && (
            <p className={`text-lg font-semibold ${lastCorrect ? 'text-emerald-600' : 'text-destructive'}`}>
              {lastCorrect ? t('visualSpatialMemory.correct') : t('visualSpatialMemory.incorrect')}
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function SceneView({
  round,
  phase,
  onAnswer,
}: {
  round: SceneRound;
  phase: Phase;
  onAnswer: (given: string | number) => void;
}) {
  const { t } = useTranslation('games');
  const n = round.grid;
  const countOptions = useMemo(
    () => shuffled([round.answer as number, (round.answer as number) - 1, (round.answer as number) + 1, (round.answer as number) + 2]),
    [round],
  );
  const positionOptions = useMemo(() => {
    const distractorIcons = round.icons.filter((ic) => ic !== round.answer).slice(0, 3);
    return shuffled([round.answer as string, ...distractorIcons]);
  }, [round]);

  if (phase === 'scene-exposure') {
    return (
      <div className="flex flex-col items-center gap-3">
        <p className="text-sm text-muted-foreground">{t('visualSpatialMemory.memorize')}</p>
        <div className="grid gap-1.5" style={{ gridTemplateColumns: `repeat(${n}, minmax(0, 1fr))` }}>
          {Array.from({ length: n * n }, (_, i) => {
            const idx = round.cells.indexOf(i);
            return (
              <div key={i} className="flex h-14 w-14 items-center justify-center rounded-md border border-border bg-muted/40 text-2xl">
                {idx >= 0 ? round.icons[idx] : ''}
              </div>
            );
          })}
        </div>
      </div>
    );
  }

  if (round.question === 'count') {
    const options = countOptions;
    return (
      <div className="flex flex-col items-center gap-4">
        <p className="text-sm">{t('visualSpatialMemory.howMany')}</p>
        <div className="flex gap-2">
          {options.map((o, i) => (
            <Button key={i} variant="outline" onClick={() => onAnswer(o)}>
              {o}
            </Button>
          ))}
        </div>
      </div>
    );
  }

  if (round.question === 'positionIcon') {
    const options = positionOptions;
    return (
      <div className="flex flex-col items-center gap-4">
        <p className="text-sm">{t('visualSpatialMemory.whichIconWasAt')}</p>
        <div className="grid gap-1.5" style={{ gridTemplateColumns: `repeat(${n}, minmax(0, 1fr))` }}>
          {Array.from({ length: n * n }, (_, i) => (
            <div
              key={i}
              className={`flex h-10 w-10 items-center justify-center rounded-md border text-xl ${
                i === round.targetCell ? 'border-primary bg-primary/20' : 'border-border'
              }`}
            >
              {i === round.targetCell ? '?' : ''}
            </div>
          ))}
        </div>
        <div className="flex gap-2">
          {options.map((o, i) => (
            <Button key={i} variant="outline" onClick={() => onAnswer(o)} className="text-xl">
              {o}
            </Button>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col items-center gap-4">
      <p className="text-sm">{t('visualSpatialMemory.whichNotShown')}</p>
      <div className="flex gap-2">
        {(round.optionIcons ?? []).map((o, i) => (
          <Button key={i} variant="outline" onClick={() => onAnswer(o)} className="text-xl">
            {o}
          </Button>
        ))}
      </div>
    </div>
  );
}

function PathView({
  round,
  phase,
  flashStep,
  input,
  onTap,
}: {
  round: PathRound;
  phase: Phase;
  flashStep: number;
  input: number[];
  onTap: (cell: number) => void;
}) {
  const { t } = useTranslation('games');
  const n = round.grid;
  const activeCell = phase === 'path-flash' && flashStep < round.sequence.length ? round.sequence[flashStep] : null;

  return (
    <div className="flex flex-col items-center gap-3">
      <p className="text-sm text-muted-foreground">
        {phase === 'path-flash' ? t('visualSpatialMemory.watchPath') : t('visualSpatialMemory.tapPath')}
      </p>
      <div className="grid gap-1.5" style={{ gridTemplateColumns: `repeat(${n}, minmax(0, 1fr))` }}>
        {Array.from({ length: n * n }, (_, i) => {
          const isActive = i === activeCell;
          const tappedIdx = input.indexOf(i);
          return (
            <button
              key={i}
              type="button"
              disabled={phase !== 'path-recall'}
              onClick={() => onTap(i)}
              className={`flex h-14 w-14 items-center justify-center rounded-md border text-xs font-semibold transition-colors ${
                isActive
                  ? 'border-primary bg-primary text-primary-foreground'
                  : tappedIdx >= 0
                    ? 'border-primary bg-primary/20'
                    : 'border-border hover:bg-muted'
              }`}
            >
              {tappedIdx >= 0 ? tappedIdx + 1 : ''}
            </button>
          );
        })}
      </div>
    </div>
  );
}
