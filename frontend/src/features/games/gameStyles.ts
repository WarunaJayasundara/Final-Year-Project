import { Calculator, Eye, Grid3x3, Layers, Puzzle, RotateCw, ScanEye, Zap, type LucideIcon } from 'lucide-react';

export const GAME_ICONS: Record<string, LucideIcon> = {
  memory_match: Grid3x3,
  sequence_puzzle: Puzzle,
  math_rush: Calculator,
  mental_rotation: RotateCw,
  selective_attention: Eye,
  working_memory_span: Layers,
  visual_spatial_memory: ScanEye,
  cognitive_command_center: Zap,
};

const CHART_ACCENTS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];
const GAME_ORDER = Object.keys(GAME_ICONS);

/** Each game gets its own accent cycling through the app's 5-color chart palette. */
export function gameAccent(code: string): string {
  const index = GAME_ORDER.indexOf(code);
  return CHART_ACCENTS[index >= 0 ? index % CHART_ACCENTS.length : 0];
}

export const GAME_ROUTES: Record<string, string> = {
  memory_match: '/games/memory-match',
  sequence_puzzle: '/games/sequence-puzzle',
  math_rush: '/games/math-rush',
  mental_rotation: '/games/mental-rotation',
  selective_attention: '/games/selective-attention',
  working_memory_span: '/games/working-memory-span',
  visual_spatial_memory: '/games/visual-spatial-memory',
  cognitive_command_center: '/games/cognitive-command-center',
};
