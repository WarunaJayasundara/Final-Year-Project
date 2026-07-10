import { api } from '@/lib/api';
import type { RewardResult } from '@/features/gamification/types';

export interface GameInfo {
  id: number;
  code: string;
  name_en: string;
  name_si: string;
  description_en: string | null;
  description_si: string | null;
}

export async function fetchGames(): Promise<GameInfo[]> {
  const { data } = await api.get<{ data: GameInfo[] }>('/games');
  return data.data;
}

export async function submitGameScore(
  code: string,
  score: number,
  durationSeconds: number,
  metadata?: Record<string, unknown>,
): Promise<{ best_score: number; is_new_best: boolean; rewards: RewardResult }> {
  const { data } = await api.post<{ data: { best_score: number; is_new_best: boolean; rewards: RewardResult } }>(
    `/games/${code}/score`,
    { score, duration_seconds: durationSeconds, metadata },
  );
  return data.data;
}
