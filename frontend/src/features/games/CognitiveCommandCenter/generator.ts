export type TaskType = 'pattern' | 'recall' | 'sort' | 'inhibition' | 'dual';
export type SortRule = 'largest' | 'smallestOdd' | 'largestEven';

export interface PatternRound {
  type: 'pattern';
  sequence: number[];
  answer: number;
  options: number[];
  displayNumber: number; // last sequence value shown, tracked for 'recall' rounds
}

export interface RecallRound {
  type: 'recall';
  targetValue: number; // the displayNumber from 2 rounds ago
  options: number[];
}

export interface SortRound {
  type: 'sort';
  rule: SortRule;
  numbers: number[];
  answer: number;
}

export interface InhibitionRound {
  type: 'inhibition';
  isGo: boolean; // true = green (tap), false = red (withhold)
}

export interface DualRound {
  type: 'dual';
  holdValue: number;
  mathQuestion: string;
  mathOptions: number[];
  mathAnswerIndex: number;
  recallOptions: number[];
}

export type CommandRound = PatternRound | RecallRound | SortRound | InhibitionRound | DualRound;

function mulberry32(seed: number) {
  let a = seed;
  return function () {
    a |= 0;
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/** 12-round rotation covering all 5 task types, with pattern/dual providing "held" values for later recall rounds. */
export const TASK_ROTATION: TaskType[] = [
  'pattern',
  'sort',
  'inhibition',
  'pattern',
  'recall',
  'sort',
  'inhibition',
  'dual',
  'pattern',
  'recall',
  'inhibition',
  'sort',
];

export const SORT_RULE_SCHEDULE: SortRule[] = ['largest', 'smallestOdd', 'largestEven', 'largest', 'smallestOdd'];

export function generatePatternRound(seed: number, difficulty: number): PatternRound {
  const rng = mulberry32(seed);
  const step = Math.floor(rng() * (2 + difficulty)) + 2;
  const start = Math.floor(rng() * 10) + 1;
  const length = 4;
  const sequence = Array.from({ length }, (_, i) => start + i * step);
  const answer = start + length * step;
  const candidateOffsets = [step, -1, 2, 1, -2, step + 1, -step, 3];
  const distractors: number[] = [];
  for (const offset of candidateOffsets) {
    const value = answer + offset;
    if (value !== answer && !distractors.includes(value)) {
      distractors.push(value);
    }
    if (distractors.length === 3) break;
  }
  const options = shuffle([answer, ...distractors], rng);
  return { type: 'pattern', sequence, answer, options, displayNumber: answer };
}

export function generateSortRound(seed: number, rule: SortRule, difficulty: number): SortRound {
  const rng = mulberry32(seed);
  const count = Math.min(6, 4 + Math.floor(difficulty / 2));
  const numbers = Array.from({ length: count }, () => Math.floor(rng() * 50) + 1);

  let answer: number;
  if (rule === 'largest') {
    answer = Math.max(...numbers);
  } else if (rule === 'largestEven') {
    const evens = numbers.filter((n) => n % 2 === 0);
    answer = evens.length ? Math.max(...evens) : Math.max(...numbers);
  } else {
    const odds = numbers.filter((n) => n % 2 !== 0);
    answer = odds.length ? Math.min(...odds) : Math.min(...numbers);
  }

  return { type: 'sort', rule, numbers, answer };
}

export function generateInhibitionRound(seed: number): InhibitionRound {
  const rng = mulberry32(seed);
  return { type: 'inhibition', isGo: rng() < 0.7 };
}

export function generateDualRound(seed: number): DualRound {
  const rng = mulberry32(seed);
  const holdValue = Math.floor(rng() * 9) + 1;
  const a = Math.floor(rng() * 9) + 1;
  const b = Math.floor(rng() * 9) + 1;
  const correct = a + b;
  const wrong = correct + (rng() < 0.5 ? 1 : -1) * (Math.floor(rng() * 3) + 1);
  const mathOptions = shuffle([correct, wrong], rng);
  const recallOptions = shuffle([holdValue, holdValue + 1, holdValue - 1, holdValue + 2].filter((v) => v > 0), rng).slice(0, 4);
  if (!recallOptions.includes(holdValue)) recallOptions[0] = holdValue;

  return {
    type: 'dual',
    holdValue,
    mathQuestion: `${a} + ${b} = ?`,
    mathOptions,
    mathAnswerIndex: mathOptions.indexOf(correct),
    recallOptions,
  };
}

export function generateRecallRound(targetValue: number, seed: number): RecallRound {
  const rng = mulberry32(seed);
  const options = shuffle([targetValue, targetValue + 1, targetValue - 1, targetValue + 3], rng);
  return { type: 'recall', targetValue, options };
}

function shuffle<T>(arr: T[], rng: () => number): T[] {
  const copy = [...arr];
  for (let i = copy.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1));
    [copy[i], copy[j]] = [copy[j], copy[i]];
  }
  return copy;
}
