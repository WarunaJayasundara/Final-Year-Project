export type RoundType = 'scene' | 'path';

export const ICON_POOL = ['🍎', '🚗', '⭐', '🎈', '🐘', '🎵', '🌙', '⚽', '🔑', '📚', '🌵', '☂️'];

export interface SceneRound {
  type: 'scene';
  grid: number; // n x n
  cells: number[]; // occupied cell indices
  icons: string[]; // parallel to cells
  question: 'count' | 'positionIcon' | 'missingIcon';
  targetCell?: number; // for positionIcon
  optionIcons?: string[]; // for missingIcon: 3 shown + 1 not shown, shuffled
  answer: string | number;
}

export interface PathRound {
  type: 'path';
  grid: number;
  sequence: number[]; // cell indices, in order
}

export type MemoryRound = SceneRound | PathRound;

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

function shuffledIndices(n: number, rng: () => number): number[] {
  const arr = Array.from({ length: n }, (_, i) => i);
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

export function generateSceneRound(itemCount: number, seed: number): SceneRound {
  const rng = mulberry32(seed);
  const grid = 4;
  const cells = shuffledIndices(grid * grid, rng).slice(0, itemCount);
  const icons = shuffledIndices(ICON_POOL.length, rng)
    .slice(0, itemCount)
    .map((i) => ICON_POOL[i]);

  const questionRoll = rng();
  if (questionRoll < 0.34) {
    return { type: 'scene', grid, cells, icons, question: 'count', answer: itemCount };
  }
  if (questionRoll < 0.67) {
    const idx = Math.floor(rng() * itemCount);
    return { type: 'scene', grid, cells, icons, question: 'positionIcon', targetCell: cells[idx], answer: icons[idx] };
  }
  const unusedIcons = ICON_POOL.filter((i) => !icons.includes(i));
  const missing = unusedIcons[Math.floor(rng() * unusedIcons.length)];
  const shownSample = shuffledIndices(icons.length, rng)
    .slice(0, 3)
    .map((i) => icons[i]);
  const optionIcons = shuffledIndices(4, rng).map((_, i) => (i < 3 ? shownSample[i] : missing));
  return { type: 'scene', grid, cells, icons, question: 'missingIcon', optionIcons, answer: missing };
}

export function generatePathRound(span: number, seed: number): PathRound {
  const rng = mulberry32(seed);
  const grid = 4;
  const total = grid * grid;
  const sequence: number[] = [];
  while (sequence.length < span) {
    const cell = Math.floor(rng() * total);
    if (sequence[sequence.length - 1] !== cell) {
      sequence.push(cell);
    }
  }
  return { type: 'path', grid, sequence };
}

export const MIN_ITEMS = 5;
export const MAX_ITEMS = 8;
export const MIN_PATH_SPAN = 3;
export const MAX_PATH_SPAN = 8;
