export const GRID_SIZE = 4;

export type Cell = [row: number, col: number];

/**
 * Hand-picked asymmetric shapes (no rotational or reflective symmetry), so
 * every rotation and every mirror image is visually distinct - required for
 * the puzzle to have exactly one unambiguous correct answer.
 */
const BASE_SHAPES: Cell[][] = [
  [[0, 0], [0, 1], [0, 2], [1, 0], [2, 0], [2, 1], [3, 0]],
  [[0, 2], [0, 3], [1, 1], [1, 2], [2, 0], [2, 1], [3, 0]],
  [[0, 0], [1, 0], [1, 1], [1, 2], [2, 2], [2, 3], [3, 3]],
];

function rotate90(cells: Cell[]): Cell[] {
  return cells.map(([r, c]) => [c, GRID_SIZE - 1 - r] as Cell);
}

function mirror(cells: Cell[]): Cell[] {
  return cells.map(([r, c]) => [r, GRID_SIZE - 1 - c] as Cell);
}

function rotateBy(cells: Cell[], turns: number): Cell[] {
  let result = cells;
  for (let i = 0; i < turns; i++) {
    result = rotate90(result);
  }
  return result;
}

function normalize(cells: Cell[]): string {
  return [...cells]
    .sort((a, b) => a[0] - b[0] || a[1] - b[1])
    .map(([r, c]) => `${r},${c}`)
    .join(';');
}

function shuffle<T>(items: T[]): T[] {
  const arr = [...items];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

export interface RotationOption {
  id: number;
  cells: Cell[];
  isCorrect: boolean;
}

export interface RotationRound {
  target: Cell[];
  options: RotationOption[];
}

/** One round: a target shape, and 4 options where exactly one is a true rotation (not a mirror) of the target. */
export function generateRound(): RotationRound {
  const base = BASE_SHAPES[Math.floor(Math.random() * BASE_SHAPES.length)];
  const targetTurns = Math.floor(Math.random() * 4);
  const target = rotateBy(base, targetTurns);

  const correctTurns = Math.floor(Math.random() * 4);
  const correct = rotateBy(base, correctTurns);

  const mirrored = mirror(base);
  const targetKey = normalize(target);
  const distractors: Cell[][] = [];
  const seen = new Set<string>([normalize(correct)]);

  let attempts = 0;
  while (distractors.length < 3 && attempts < 20) {
    attempts++;
    const turns = Math.floor(Math.random() * 4);
    const candidate = rotateBy(mirrored, turns);
    const key = normalize(candidate);
    if (key !== targetKey && !seen.has(key)) {
      seen.add(key);
      distractors.push(candidate);
    }
  }
  // Extremely unlikely fallback if a small base shape ran out of distinct mirror rotations.
  while (distractors.length < 3) {
    distractors.push(rotateBy(mirrored, distractors.length));
  }

  const options: RotationOption[] = shuffle([
    { id: 0, cells: correct, isCorrect: true },
    ...distractors.map((cells, i) => ({ id: i + 1, cells, isCorrect: false })),
  ]).map((option, index) => ({ ...option, id: index }));

  return { target, options };
}
