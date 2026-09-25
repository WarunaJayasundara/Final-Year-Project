import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { claimMission, fetchBadges, fetchGamificationSummary, fetchLeaderboard, fetchMissions } from './api';

export function useGamificationSummary() {
  return useQuery({ queryKey: ['gamification', 'summary'], queryFn: fetchGamificationSummary });
}

export function useBadges() {
  return useQuery({ queryKey: ['gamification', 'badges'], queryFn: fetchBadges });
}

export function useMissions() {
  return useQuery({ queryKey: ['gamification', 'missions'], queryFn: fetchMissions });
}

export function useLeaderboard() {
  return useQuery({ queryKey: ['gamification', 'leaderboard'], queryFn: fetchLeaderboard });
}

export function useClaimMission(options?: { onSuccess?: (result: { mission: unknown; summary: unknown }) => void }) {
  const queryClient = useQueryClient();
  return useMutation({
    meta: { toastOnError: true },
    mutationFn: (code: string) => claimMission(code),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['gamification'] });
      options?.onSuccess?.(result);
    },
  });
}
