import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchExamCategories, fetchExamProfile, fetchStudyPlan, saveExamProfile } from './api';
import type { ExamProfile, ExamProfileInput } from './types';
import { showRewardToast } from '@/features/gamification/rewardToast';

export function useExamCategories() {
  return useQuery({ queryKey: ['exam-profile', 'categories'], queryFn: fetchExamCategories, staleTime: Infinity });
}

export function useExamProfile() {
  return useQuery({ queryKey: ['exam-profile'], queryFn: fetchExamProfile });
}

export function useStudyPlan() {
  return useQuery({ queryKey: ['exam-profile', 'study-plan'], queryFn: fetchStudyPlan });
}

// Hook-level onSuccess/onError so callbacks reliably fire under Strict Mode.
export function useSaveExamProfile(options?: { onSuccess?: (profile: ExamProfile) => void }) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: ExamProfileInput) => saveExamProfile(input),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['exam-profile'] });
      queryClient.invalidateQueries({ queryKey: ['gamification'] });
      showRewardToast({ xp: 0, coins: 0, new_badges: result.newBadges });
      options?.onSuccess?.(result.profile);
    },
  });
}
