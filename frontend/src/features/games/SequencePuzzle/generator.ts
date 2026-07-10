export interface SequenceRound {
  sequence: number[];
  answer: number;
  options: number[];
}

function shuffle<T>(items: T[]): T[] {
  const arr = [...items];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

/** Generates a sequence puzzle round; `round` (1-10) scales difficulty. */
export function generateRound(round: number): SequenceRound {
  const difficulty = Math.min(round, 10);
  const kind = Math.random();

  let sequence: number[];
  let answer: number;

  if (kind < 0.5) {
    // Arithmetic progression
    const step = Math.floor(Math.random() * (2 + difficulty)) + 1;
    const start = Math.floor(Math.random() * 10) + 1;
    sequence = [0, 1, 2, 3].map((i) => start + i * step);
    answer = start + 4 * step;
  } else {
    // Geometric-ish (doubling/tripling), kept small for higher rounds
    const ratio = difficulty > 6 ? 3 : 2;
    const start = Math.floor(Math.random() * 3) + 1;
    sequence = [0, 1, 2, 3].map((i) => start * ratio ** i);
    answer = start * ratio ** 4;
  }

  const distractors = new Set<number>();
  while (distractors.size < 3) {
    const delta = Math.floor(Math.random() * (answer > 20 ? 10 : 5)) + 1;
    const candidate = Math.random() > 0.5 ? answer + delta : answer - delta;
    if (candidate !== answer && candidate > 0) {
      distractors.add(candidate);
    }
  }

  return { sequence, answer, options: shuffle([answer, ...distractors]) };
}
