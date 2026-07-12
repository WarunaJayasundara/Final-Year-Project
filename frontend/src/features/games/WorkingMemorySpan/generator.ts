export type WorkingMemoryTaskType = 'forward' | 'backward' | 'nback' | 'interference';

export interface WorkingMemoryTrial {
  type: WorkingMemoryTaskType;
  sequence: number[];
  distractor?: { question: string; options: number[]; answerIndex: number };
  /** n-back only: whether sequence[i] matches sequence[i-2], aligned to sequence. */
  nbackTargets?: boolean[];
}

/** Deterministic PRNG (mulberry32) so a given seed always reproduces the same trial. */
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

/** Fixed rotation of task types across an 8-trial session. */
export const TASK_ROTATION: WorkingMemoryTaskType[] = [
  'forward',
  'backward',
  'forward',
  'nback',
  'backward',
  'interference',
  'forward',
  'nback',
];

export function generateTrial(type: WorkingMemoryTaskType, span: number, seed: number): WorkingMemoryTrial {
  const rng = mulberry32(seed);

  if (type === 'nback') {
    const length = span + 4;
    const sequence: number[] = [];
    const nbackTargets: boolean[] = [];
    for (let i = 0; i < length; i++) {
      const forceMatch = i >= 2 && rng() < 0.35;
      let digit: number;
      if (forceMatch) {
        digit = sequence[i - 2];
      } else {
        do {
          digit = Math.floor(rng() * 10);
        } while (i >= 2 && digit === sequence[i - 2]);
      }
      sequence.push(digit);
      nbackTargets.push(i >= 2 ? sequence[i] === sequence[i - 2] : false);
    }
    return { type, sequence, nbackTargets };
  }

  const sequence = Array.from({ length: span }, () => Math.floor(rng() * 10));

  if (type === 'interference') {
    const a = Math.floor(rng() * 9) + 1;
    const b = Math.floor(rng() * 9) + 1;
    const correct = a + b;
    const wrong = correct + (rng() < 0.5 ? 1 : -1) * (Math.floor(rng() * 3) + 1);
    const options = rng() < 0.5 ? [correct, wrong] : [wrong, correct];
    return { type, sequence, distractor: { question: `${a} + ${b} = ?`, options, answerIndex: options.indexOf(correct) } };
  }

  return { type, sequence };
}

export const MIN_SPAN = 3;
export const MAX_SPAN = 9;

export function nextSpan(current: number, correct: boolean, consecutiveCorrect: number): number {
  if (correct && consecutiveCorrect >= 1) {
    return Math.min(MAX_SPAN, current + 1);
  }
  if (!correct) {
    return Math.max(MIN_SPAN, current - 1);
  }
  return current;
}
