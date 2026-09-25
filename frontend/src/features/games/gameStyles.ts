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

// One distinct accent per game. The 5 category chart colors alone wrap around for an 8-game hub
// (games 6-8 silently repeated games 1-3's color on the same grid) - brand-gold plus two more hues
// (--game-accent-6/7) fill that out to 8 genuinely different colors, no repeats.
const GAME_ACCENTS = [
  'var(--chart-1)',
  'var(--chart-2)',
  'var(--chart-3)',
  'var(--chart-4)',
  'var(--chart-5)',
  'var(--brand-gold)',
  'var(--game-accent-6)',
  'var(--game-accent-7)',
];
const GAME_ORDER = Object.keys(GAME_ICONS);

/** Each game gets its own distinct accent - see GAME_ACCENTS above. */
export function gameAccent(code: string): string {
  const index = GAME_ORDER.indexOf(code);
  return GAME_ACCENTS[index >= 0 ? index % GAME_ACCENTS.length : 0];
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
