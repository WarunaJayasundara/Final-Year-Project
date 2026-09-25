export const GRID_SIZE = 4;

export type Cell = [row: number, col: number];

/** Hand-picked chiral shapes: each has four distinct rotations and its mirror image is NOT one of them. */
const BASE_SHAPES: Cell[][] = [
  [[0, 0], [0, 1], [0, 2], [1, 0], [2, 0], [2, 1], [3, 0]],
  [[0, 2], [0, 3], [1, 0], [1, 1], [1, 2], [2, 0], [3, 0]],
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

  // All 4 mirror rotations, deduplicated by shape (verified: every BASE_SHAPES entry's mirror has 4 distinct rotations.
  const mirrored = mirror(base);
  const mirrorRotations: Cell[][] = [];
  const mirrorKeys = new Set<string>();
  for (let turns = 0; turns < 4; turns++) {
    const candidate = rotateBy(mirrored, turns);
    const key = normalize(candidate);
    if (!mirrorKeys.has(key)) {
      mirrorKeys.add(key);
      mirrorRotations.push(candidate);
    }
  }
  const distractors = shuffle(mirrorRotations).slice(0, 3);
  while (distractors.length < 3) {
    // Unreachable with the current BASE_SHAPES (each verified to have 4 distinct mirror
    // rotations above); kept only so a future shape with a degenerate mirror fails safe.
    distractors.push(mirrorRotations[mirrorRotations.length - 1]);
  }

  const options: RotationOption[] = shuffle([
    { id: 0, cells: correct, isCorrect: true },
    ...distractors.map((cells, i) => ({ id: i + 1, cells, isCorrect: false })),
  ]).map((option, index) => ({ ...option, id: index }));

  return { target, options };
}
