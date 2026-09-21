import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Button } from '@/components/ui/button';
import { GameResultCard } from '../GameResultCard';
import { useSubmitGameScore } from '../useGames';
import {
  TASK_ROTATION,
  SORT_RULE_SCHEDULE,
  generatePatternRound,
  generateSortRound,
  generateInhibitionRound,
  generateDualRound,
  generateRecallRound,
  type CommandRound,
  type TaskType,
} from './generator';

const INHIBITION_WINDOW_MS = 1400;
/** How long the number to hold is visible in a dual-task round before it is hidden for the arithmetic. */
const DUAL_HOLD_MS = 1600;

interface RoundLog {
  type: TaskType;
  correct: boolean;
  reactionMs: number;
  ruleChanged?: boolean;
}

export function CognitiveCommandCenter() {
  const { t } = useTranslation('games');
  const [roundIndex, setRoundIndex] = useState(0);
  const [difficulty, setDifficulty] = useState(1);
  const [sortRuleIndex, setSortRuleIndex] = useState(0);
  const [lastSortRule, setLastSortRule] = useState<string | null>(null);
  // The number each pattern/dual round showed, keyed by round index, so a recall round can ask about round (index - 2).
  const [shownByRound, setShownByRound] = useState<Record<number, number>>({});
  const [round, setRound] = useState<CommandRound>(() => generatePatternRound(1, 1));
  const [answered, setAnswered] = useState(false);
  const [lastCorrect, setLastCorrect] = useState<boolean | null>(null);
  const [dualMathDone, setDualMathDone] = useState(false);
  const [dualMathCorrect, setDualMathCorrect] = useState(false);
  const [holdHidden, setHoldHidden] = useState(false);
  const [logs, setLogs] = useState<RoundLog[]>([]);
  const [startedAt, setStartedAt] = useState(Date.now);
  const [result, setResult] = useState<{ score: number; bestScore?: number; isNewBest?: boolean } | null>(null);
  const roundStartRef = useRef(Date.now());
  const inhibitionTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const submitScore = useSubmitGameScore('cognitive_command_center', {
    onSuccess: (data) => setResult((prev) => (prev ? { ...prev, bestScore: data.best_score, isNewBest: data.is_new_best } : prev)),
  });

  useEffect(() => {
    roundStartRef.current = Date.now();
    if (round.type === 'inhibition') {
      // Both circles are timed: a green one not tapped in time is a miss, a red one not tapped is a correct withhold.
      inhibitionTimerRef.current = setTimeout(() => {
        recordAnswer(!round.isGo);
      }, INHIBITION_WINDOW_MS);
      return () => {
        if (inhibitionTimerRef.current) clearTimeout(inhibitionTimerRef.current);
      };
    }
    if (round.type === 'dual') {
      const timer = setTimeout(() => {
        setHoldHidden(true);
        roundStartRef.current = Date.now();
      }, DUAL_HOLD_MS);
      return () => clearTimeout(timer);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [round]);

  const recordAnswer = (correct: boolean, ruleChanged?: boolean) => {
    if (inhibitionTimerRef.current) {
      clearTimeout(inhibitionTimerRef.current);
      inhibitionTimerRef.current = null;
    }
    const reactionMs = Date.now() - roundStartRef.current;
    setAnswered(true);
    setLastCorrect(correct);
    setLogs((prev) => [...prev, { type: round.type, correct, reactionMs, ruleChanged }]);
    if (round.type === 'sort') {
      // Record AFTER ruleChangedThisRound (computed against the previous
      // value) was already captured into the log line above - updating
      // this any earlier would make every sort round compare its rule
      // against itself and always read as "unchanged".
      setLastSortRule(round.rule);
    }

    const newDifficulty = correct ? Math.min(5, difficulty + 1) : Math.max(1, difficulty - 1);
    setDifficulty(newDifficulty);

    let newShown = shownByRound;
    if (round.type === 'pattern' || round.type === 'dual') {
      newShown = { ...shownByRound, [roundIndex]: round.type === 'pattern' ? round.displayNumber : round.holdValue };
      setShownByRound(newShown);
    }

    setTimeout(() => {
      const next = roundIndex + 1;
      if (next >= TASK_ROTATION.length) {
        setResult(null);
        setRoundIndex(next);
        return;
      }
      const nextType = TASK_ROTATION[next];
      setAnswered(false);
      setDualMathDone(false);
      setDualMathCorrect(false);
      setHoldHidden(false);
      setRoundIndex(next);

      if (nextType === 'pattern') {
        setRound(generatePatternRound(100 * next + newDifficulty, newDifficulty));
      } else if (nextType === 'sort') {
        const rule = SORT_RULE_SCHEDULE[sortRuleIndex % SORT_RULE_SCHEDULE.length];
        setSortRuleIndex((i) => i + 1);
        setRound(generateSortRound(100 * next + newDifficulty, rule, newDifficulty));
      } else if (nextType === 'inhibition') {
        setRound(generateInhibitionRound(100 * next + newDifficulty));
      } else if (nextType === 'dual') {
        setRound(generateDualRound(100 * next + newDifficulty));
      } else {
        const shown = Object.values(newShown);
        const targetValue = newShown[next - 2] ?? (shown.length ? shown[shown.length - 1] : 5);
        setRound(generateRecallRound(targetValue, 100 * next + newDifficulty));
      }
    }, 700);
  };

  useEffect(() => {
    if (roundIndex < TASK_ROTATION.length) return;
    const seconds = Math.round((Date.now() - startedAt) / 1000);
    const correctCount = logs.filter((l) => l.correct).length;

    const sortLogs = logs.filter((l) => l.type === 'sort');
    const changedRts = sortLogs.filter((l) => l.ruleChanged).map((l) => l.reactionMs);
    const steadyRts = sortLogs.filter((l) => !l.ruleChanged).map((l) => l.reactionMs);
    const avg = (arr: number[]) => (arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : null);
    const switchCost = avg(changedRts) !== null && avg(steadyRts) !== null ? Math.round((avg(changedRts) as number) - (avg(steadyRts) as number)) : null;

    const score = Math.max(0, correctCount * 60 - Math.floor(seconds / 3));
    const byType = (type: TaskType) => {
      const relevant = logs.filter((l) => l.type === type);
      return relevant.length ? relevant.filter((l) => l.correct).length / relevant.length : null;
    };

    setResult({ score });
    submitScore.mutate({
      score,
      durationSeconds: seconds,
      metadata: {
        pattern_accuracy: byType('pattern'),
        recall_accuracy: byType('recall'),
        sort_accuracy: byType('sort'),
        inhibition_accuracy: byType('inhibition'),
        dual_accuracy: byType('dual'),
        cognitive_switching_cost_ms: switchCost,
        rounds: logs.length,
      },
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [roundIndex]);

  const reset = () => {
    setStartedAt(Date.now()); // a replay is timed from its own start, not from the first game
    setRoundIndex(0);
    setDifficulty(1);
    setSortRuleIndex(0);
    setLastSortRule(null);
    setShownByRound({});
    setRound(generatePatternRound(1, 1));
    setAnswered(false);
    setLastCorrect(null);
    setDualMathDone(false);
    setDualMathCorrect(false);
    setHoldHidden(false);
    setLogs([]);
    setResult(null);
  };

  if (roundIndex >= TASK_ROTATION.length && result) {
    return <GameResultCard score={result.score} bestScore={result.bestScore} isNewBest={result.isNewBest} onPlayAgain={reset} />;
  }
  if (roundIndex >= TASK_ROTATION.length) {
    return null;
  }

  const ruleChangedThisRound = round.type === 'sort' && lastSortRule !== null && round.rule !== lastSortRule;

  return (
    <div className="mx-auto flex max-w-lg flex-col gap-6">
      <div className="flex flex-col gap-2">
        <span className="text-sm text-muted-foreground">
          {t('cognitiveCommandCenter.round', { current: roundIndex + 1, total: TASK_ROTATION.length })}
        </span>
        <Progress value={(roundIndex / TASK_ROTATION.length) * 100} />
      </div>

      <Card>
        <CardContent className="flex flex-col items-center gap-6 p-8">
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
            {t(`cognitiveCommandCenter.task.${round.type}`)}
          </p>

          {round.type === 'pattern' && !answered && (
            <div className="flex flex-col items-center gap-4">
              <p className="text-2xl font-semibold tracking-wide">{round.sequence.join('  ,  ')}  ,  ?</p>
              <div className="flex gap-2">
                {round.options.map((opt, i) => (
                  <Button key={i} variant="outline" onClick={() => recordAnswer(opt === round.answer)}>
                    {opt}
                  </Button>
                ))}
              </div>
            </div>
          )}

          {round.type === 'recall' && !answered && (
            <div className="flex flex-col items-center gap-4">
              <p className="text-sm">{t('cognitiveCommandCenter.recallPrompt')}</p>
              <div className="flex gap-2">
                {round.options.map((opt, i) => (
                  <Button key={i} variant="outline" onClick={() => recordAnswer(opt === round.targetValue)}>
                    {opt}
                  </Button>
                ))}
              </div>
            </div>
          )}

          {round.type === 'sort' && !answered && (
            <div className="flex flex-col items-center gap-4">
              <p className="text-sm font-medium">{t(`cognitiveCommandCenter.rules.${round.rule}`)}</p>
              <div className="flex flex-wrap justify-center gap-2">
                {round.numbers.map((n, i) => (
                  <Button key={i} variant="outline" onClick={() => recordAnswer(n === round.answer, ruleChangedThisRound)}>
                    {n}
                  </Button>
                ))}
              </div>
            </div>
          )}

          {round.type === 'inhibition' && !answered && (
            <div className="flex flex-col items-center gap-4">
              <p className="text-sm">{t('cognitiveCommandCenter.goPrompt')}</p>
              <button
                type="button"
                onClick={() => recordAnswer(round.isGo)}
                className={`h-20 w-20 rounded-full ${round.isGo ? 'bg-success' : 'bg-destructive'}`}
                aria-label={round.isGo ? 'go' : 'no-go'}
              />
            </div>
          )}

          {round.type === 'dual' && !answered && !holdHidden && (
            <div className="flex flex-col items-center gap-2">
              <p className="text-sm text-muted-foreground">{t('cognitiveCommandCenter.task.dual')}</p>
              <p className="text-5xl font-bold">{round.holdValue}</p>
            </div>
          )}

          {round.type === 'dual' && !answered && holdHidden && !dualMathDone && (
            <div className="flex flex-col items-center gap-4">
              <p className="text-xl font-semibold">{round.mathQuestion}</p>
              <div className="flex gap-3">
                {round.mathOptions.map((opt, i) => (
                  <Button
                    key={i}
                    variant="outline"
                    onClick={() => {
                      setDualMathCorrect(i === round.mathAnswerIndex);
                      setDualMathDone(true);
                    }}
                  >
                    {opt}
                  </Button>
                ))}
              </div>
            </div>
          )}

          {round.type === 'dual' && !answered && holdHidden && dualMathDone && (
            <div className="flex flex-col items-center gap-4">
              <p className="text-sm">{t('cognitiveCommandCenter.recallPrompt')}</p>
              <div className="flex gap-2">
                {round.recallOptions.map((opt, i) => (
                  <Button key={i} variant="outline" onClick={() => recordAnswer(opt === round.holdValue && dualMathCorrect)}>
                    {opt}
                  </Button>
                ))}
              </div>
            </div>
          )}

          {answered && (
            <p className={`text-lg font-semibold ${lastCorrect ? 'text-success' : 'text-destructive'}`}>
              {lastCorrect ? t('cognitiveCommandCenter.correct') : t('cognitiveCommandCenter.incorrect')}
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
