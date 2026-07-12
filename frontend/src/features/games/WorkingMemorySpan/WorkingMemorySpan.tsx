import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Button } from '@/components/ui/button';
import { GameResultCard } from '../GameResultCard';
import { useSubmitGameScore } from '../useGames';
import {
  generateTrial,
  nextSpan,
  MIN_SPAN,
  TASK_ROTATION,
  type WorkingMemoryTrial,
} from './generator';

type Phase = 'encoding' | 'distractor' | 'recall' | 'nback-stream' | 'trial-feedback' | 'finished';

const ENCODE_MS = 900;
const NBACK_STEP_MS = 1400;

interface TrialLog {
  type: WorkingMemoryTrial['type'];
  correct: boolean;
  spanAtTrial: number;
  encodingMs: number;
}

export function WorkingMemorySpan() {
  const { t } = useTranslation('games');
  const [trialIndex, setTrialIndex] = useState(0);
  const [span, setSpan] = useState(MIN_SPAN);
  const [consecutiveCorrect, setConsecutiveCorrect] = useState(0);
  const [trial, setTrial] = useState<WorkingMemoryTrial>(() => generateTrial(TASK_ROTATION[0], MIN_SPAN, 1));
  const [phase, setPhase] = useState<Phase>('encoding');
  const [encodeStep, setEncodeStep] = useState(0);
  const [recallInput, setRecallInput] = useState<number[]>([]);
  const [distractorAnswered, setDistractorAnswered] = useState(false);
  const [nbackStep, setNbackStep] = useState(0);
  const [nbackResponded, setNbackResponded] = useState(false);
  const [nbackHits, setNbackHits] = useState(0);
  const [nbackJudged, setNbackJudged] = useState(0);
  const [lastCorrect, setLastCorrect] = useState<boolean | null>(null);
  const [logs, setLogs] = useState<TrialLog[]>([]);
  const [startedAt] = useState(Date.now());
  const [result, setResult] = useState<{ score: number; bestScore?: number; isNewBest?: boolean } | null>(null);
  const encodeStartRef = useRef(Date.now());

  const submitScore = useSubmitGameScore('working_memory_span');

  // Encoding: reveal one digit at a time, then move to the next phase.
  useEffect(() => {
    if (phase !== 'encoding') return;
    encodeStartRef.current = Date.now();
    if (encodeStep >= trial.sequence.length) {
      setPhase(trial.type === 'interference' ? 'distractor' : 'recall');
      setEncodeStep(0);
      return;
    }
    const timer = setTimeout(() => setEncodeStep((s) => s + 1), ENCODE_MS);
    return () => clearTimeout(timer);
  }, [phase, encodeStep, trial]);

  // n-back: auto-advance the stream; each step gives a fixed window to respond.
  useEffect(() => {
    if (phase !== 'nback-stream' || !trial.nbackTargets) return;
    if (nbackStep >= trial.sequence.length) {
      const total = trial.nbackTargets.filter((_, i) => i >= 2).length || 1;
      finishTrial(nbackHits / total, trial.type);
      return;
    }
    setNbackResponded(false);
    const timer = setTimeout(() => {
      if (nbackStep >= 2) {
        const wasTarget = trial.nbackTargets![nbackStep];
        setNbackJudged((j) => j + 1);
        if (!wasTarget) {
          setNbackHits((h) => h + 1); // correctly withheld a non-match
        }
      }
      setNbackStep((s) => s + 1);
    }, NBACK_STEP_MS);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase, nbackStep]);

  const handleNbackMatch = () => {
    if (nbackResponded || nbackStep < 2 || !trial.nbackTargets) return;
    setNbackResponded(true);
    const wasTarget = trial.nbackTargets[nbackStep];
    setNbackJudged((j) => j + 1);
    if (wasTarget) {
      setNbackHits((h) => h + 1);
    }
  };

  const finishTrial = (accuracy: number, type: WorkingMemoryTrial['type']) => {
    const correct = accuracy >= 0.6;
    setLastCorrect(correct);
    setLogs((prev) => [...prev, { type, correct, spanAtTrial: span, encodingMs: ENCODE_MS }]);

    const newConsecutive = correct ? consecutiveCorrect + 1 : 0;
    setSpan((s) => nextSpan(s, correct, consecutiveCorrect));
    setConsecutiveCorrect(newConsecutive);
    setPhase('trial-feedback');

    setTimeout(() => {
      const next = trialIndex + 1;
      if (next >= TASK_ROTATION.length) {
        setPhase('finished');
        return;
      }
      const newSpan = nextSpan(span, correct, consecutiveCorrect);
      const nextType = TASK_ROTATION[next];
      setTrialIndex(next);
      setTrial(generateTrial(nextType, newSpan, 1000 * (next + 1) + newSpan));
      setEncodeStep(0);
      setRecallInput([]);
      setDistractorAnswered(false);
      setNbackStep(0);
      setNbackHits(0);
      setNbackJudged(0);
      setLastCorrect(null);
      setPhase(nextType === 'nback' ? 'nback-stream' : 'encoding');
    }, 900);
  };

  const handleDistractorAnswer = () => {
    if (distractorAnswered || !trial.distractor) return;
    setDistractorAnswered(true);
    setTimeout(() => setPhase('recall'), 500);
  };

  const handleRecallTap = (digit: number) => {
    if (phase !== 'recall') return;
    const next = [...recallInput, digit];
    setRecallInput(next);
    if (next.length === trial.sequence.length) {
      const target = trial.type === 'backward' ? [...trial.sequence].reverse() : trial.sequence;
      const correct = target.every((d, i) => d === next[i]);
      finishTrial(correct ? 1 : 0, trial.type);
    }
  };

  // Submit once finished.
  useEffect(() => {
    if (phase !== 'finished') return;
    const seconds = Math.round((Date.now() - startedAt) / 1000);
    const correctCount = logs.filter((l) => l.correct).length;
    const maxSpanReached = Math.max(MIN_SPAN, ...logs.map((l) => l.spanAtTrial));
    const score = Math.max(0, correctCount * 80 + maxSpanReached * 15 - Math.floor(seconds / 4));
    const byType = (type: WorkingMemoryTrial['type']) => {
      const relevant = logs.filter((l) => l.type === type);
      return relevant.length ? relevant.filter((l) => l.correct).length / relevant.length : null;
    };
    submitScore.mutate(
      {
        score,
        durationSeconds: seconds,
        metadata: {
          span_reached: maxSpanReached,
          forward_accuracy: byType('forward'),
          backward_accuracy: byType('backward'),
          nback_accuracy: byType('nback'),
          interference_accuracy: byType('interference'),
          trials: logs.length,
        },
      },
      { onSuccess: (data) => setResult({ score, bestScore: data.best_score, isNewBest: data.is_new_best }) },
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase]);

  const reset = () => {
    setTrialIndex(0);
    setSpan(MIN_SPAN);
    setConsecutiveCorrect(0);
    setTrial(generateTrial(TASK_ROTATION[0], MIN_SPAN, 1));
    setPhase('encoding');
    setEncodeStep(0);
    setRecallInput([]);
    setDistractorAnswered(false);
    setNbackStep(0);
    setNbackHits(0);
    setNbackJudged(0);
    setLastCorrect(null);
    setLogs([]);
    setResult(null);
  };

  if (phase === 'finished' && result) {
    return <GameResultCard score={result.score} bestScore={result.bestScore} isNewBest={result.isNewBest} onPlayAgain={reset} />;
  }

  const taskLabel = t(`workingMemorySpan.task.${trial.type}`);

  return (
    <div className="mx-auto flex max-w-lg flex-col gap-6">
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span>{t('workingMemorySpan.trial', { current: trialIndex + 1, total: TASK_ROTATION.length })}</span>
          <span>{t('workingMemorySpan.span', { span })}</span>
        </div>
        <Progress value={(trialIndex / TASK_ROTATION.length) * 100} />
      </div>

      <Card>
        <CardContent className="flex flex-col items-center gap-6 p-8">
          <p className="text-sm font-medium text-muted-foreground">{taskLabel}</p>

          {phase === 'encoding' && (
            <div className="flex h-24 w-24 items-center justify-center rounded-2xl border-2 border-primary bg-primary/10 text-4xl font-bold">
              {trial.sequence[encodeStep] ?? ''}
            </div>
          )}

          {phase === 'distractor' && trial.distractor && (
            <div className="flex flex-col items-center gap-4">
              <p className="text-xl font-semibold">{trial.distractor.question}</p>
              <div className="flex gap-3">
                {trial.distractor.options.map((opt, i) => (
                  <Button key={i} variant="outline" onClick={handleDistractorAnswer} disabled={distractorAnswered}>
                    {opt}
                  </Button>
                ))}
              </div>
            </div>
          )}

          {phase === 'recall' && (
            <div className="flex flex-col items-center gap-4">
              <p className="text-sm text-muted-foreground">
                {trial.type === 'backward' ? t('workingMemorySpan.recallBackward') : t('workingMemorySpan.recallForward')}
              </p>
              <div className="flex gap-1.5">
                {Array.from({ length: trial.sequence.length }, (_, i) => (
                  <span
                    key={i}
                    className={`flex h-9 w-9 items-center justify-center rounded-md border text-sm ${
                      i < recallInput.length ? 'border-primary bg-primary/10' : 'border-border'
                    }`}
                  >
                    {recallInput[i] ?? ''}
                  </span>
                ))}
              </div>
              <div className="grid grid-cols-5 gap-2">
                {Array.from({ length: 10 }, (_, d) => (
                  <Button key={d} variant="outline" size="sm" onClick={() => handleRecallTap(d)}>
                    {d}
                  </Button>
                ))}
              </div>
            </div>
          )}

          {phase === 'nback-stream' && (
            <div className="flex flex-col items-center gap-4">
              <p className="text-xs text-muted-foreground">{t('workingMemorySpan.nbackPrompt')}</p>
              <div className="flex h-24 w-24 items-center justify-center rounded-2xl border-2 border-primary bg-primary/10 text-4xl font-bold">
                {trial.sequence[nbackStep] ?? ''}
              </div>
              <Button onClick={handleNbackMatch} disabled={nbackResponded || nbackStep < 2}>
                {t('workingMemorySpan.matchButton')}
              </Button>
            </div>
          )}

          {phase === 'trial-feedback' && (
            <p className={`text-lg font-semibold ${lastCorrect ? 'text-emerald-600' : 'text-destructive'}`}>
              {lastCorrect ? t('workingMemorySpan.correct') : t('workingMemorySpan.incorrect')}
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
