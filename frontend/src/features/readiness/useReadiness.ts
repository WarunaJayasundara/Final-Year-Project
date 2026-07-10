import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchLatestReadiness, fetchReadinessHistory, fetchTodayCheckin, runReadinessPrediction, submitCheckin } from './api';
import type { CheckinInput } from './types';
import { showRewardToast } from '@/features/gamification/rewardToast';

export function useLatestReadiness() {
  return useQuery({ queryKey: ['readiness', 'latest'], queryFn: fetchLatestReadiness });
}

export function useReadinessHistory() {
  return useQuery({ queryKey: ['readiness', 'history'], queryFn: fetchReadinessHistory });
}

// Hook-level onSuccess/onError (not per-call .mutate() callbacks) so they
// reliably fire under React Strict Mode - see LessonsLearned in the IRT work.
export function useRunReadinessPrediction(options?: { onSuccess?: () => void; onError?: () => void }) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: runReadinessPrediction,
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['readiness'] });
      queryClient.invalidateQueries({ queryKey: ['gamification'] });
      showRewardToast({ xp: 0, coins: 0, new_badges: result.newBadges });
      options?.onSuccess?.();
    },
    onError: options?.onError,
  });
}

export function useTodayCheckin() {
  return useQuery({ queryKey: ['checkins', 'today'], queryFn: fetchTodayCheckin });
}

export function useSubmitCheckin(options?: { onSuccess?: () => void }) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: CheckinInput) => submitCheckin(input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['checkins', 'today'] });
      options?.onSuccess?.();
    },
  });
}
