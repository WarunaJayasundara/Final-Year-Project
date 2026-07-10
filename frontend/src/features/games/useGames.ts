import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchGames, submitGameScore } from './api';
import { showRewardToast } from '@/features/gamification/rewardToast';

export function useGames() {
  return useQuery({ queryKey: ['games'], queryFn: fetchGames, staleTime: 5 * 60_000 });
}

export function useSubmitGameScore(code: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      score,
      durationSeconds,
      metadata,
    }: {
      score: number;
      durationSeconds: number;
      metadata?: Record<string, unknown>;
    }) => submitGameScore(code, score, durationSeconds, metadata),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['gamification'] });
      showRewardToast(result.rewards);
    },
  });
}
