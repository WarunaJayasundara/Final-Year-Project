export interface AttentionRound {
  gridSize: number;
  targetIndex: number;
  baseRotationDeg: number;
  targetRotationDeg: number;
}

/** Grid size (and therefore distractor count) scales with round number - a standard visual-search difficulty manipulation. */
export function generateRound(round: number): AttentionRound {
  const gridSize = round <= 2 ? 4 : round <= 5 ? 5 : 6;
  const cellCount = gridSize * gridSize;
  const targetIndex = Math.floor(Math.random() * cellCount);

  const baseRotationDeg = [0, 90, 180, 270][Math.floor(Math.random() * 4)];
  const offsets = [90, 180, 270];
  const targetRotationDeg = (baseRotationDeg + offsets[Math.floor(Math.random() * offsets.length)]) % 360;

  return { gridSize, targetIndex, baseRotationDeg, targetRotationDeg };
}
