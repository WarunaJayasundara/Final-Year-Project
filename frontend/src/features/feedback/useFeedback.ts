import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchAdminFeedback, fetchFeedbackStats, fetchMyFeedback, markFeedbackReviewed, submitFeedback } from './api';
import type { FeedbackInput, FeedbackListParams } from './types';

export function useSubmitFeedback(options?: { onSuccess?: () => void; onError?: () => void }) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: FeedbackInput) => submitFeedback(input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['feedback', 'mine'] });
      options?.onSuccess?.();
    },
    onError: options?.onError,
  });
}

export function useMyFeedback() {
  return useQuery({ queryKey: ['feedback', 'mine'], queryFn: fetchMyFeedback });
}

export function useAdminFeedback(params: FeedbackListParams) {
  return useQuery({ queryKey: ['admin', 'feedback', params], queryFn: () => fetchAdminFeedback(params) });
}

export function useFeedbackStats(includeDemo: boolean) {
  return useQuery({ queryKey: ['admin', 'feedback', 'stats', includeDemo], queryFn: () => fetchFeedbackStats(includeDemo) });
}

export function useMarkFeedbackReviewed() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => markFeedbackReviewed(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'feedback'] });
    },
  });
}
