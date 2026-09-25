import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchGames, submitGameScore } from './api';
import { showRewardToast } from '@/features/gamification/rewardToast';

export function useGames() {
  return useQuery({ queryKey: ['games'], queryFn: fetchGames, staleTime: 5 * 60_000 });
}

type SubmitGameScoreResult = Awaited<ReturnType<typeof submitGameScore>>;

/** onSuccess must be registered here (hook-level), never as a per-call `.mutate(vars, { onSuccess })` argument. */
export function useSubmitGameScore(code: string, options?: { onSuccess?: (result: SubmitGameScoreResult) => void }) {
  const queryClient = useQueryClient();
  return useMutation({
    meta: { toastOnError: true },
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
      options?.onSuccess?.(result);
    },
  });
}
