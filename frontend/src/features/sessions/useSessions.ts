import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  completeSession,
  explainAnswer,
  fetchCategories,
  getReport,
  startDaily,
  startMockExam,
  startPlacement,
  startPractice,
  submitAnswer,
} from './api';
import type { AdaptiveSessionData, MockExamRequest, SessionData } from './types';
import { AUTH_QUERY_KEY } from '@/features/auth/useAuth';
import { showRewardToast } from '@/features/gamification/rewardToast';

export function useCategories() {
  return useQuery({ queryKey: ['categories'], queryFn: fetchCategories, staleTime: 5 * 60_000 });
}

// onSuccess/onError are wired as hook-level useMutation callbacks.
export function useStartPlacement(options?: { onSuccess?: (data: AdaptiveSessionData) => void; onError?: (error: unknown) => void }) {
  return useMutation({ mutationFn: startPlacement, ...options });
}

export function useStartDaily(options?: { onSuccess?: (data: SessionData) => void; onError?: (error: unknown) => void }) {
  return useMutation({ mutationFn: (totalQuestions?: number) => startDaily(totalQuestions), ...options });
}

export function useStartPractice(options?: { onSuccess?: (data: SessionData) => void; onError?: (error: unknown) => void }) {
  return useMutation({
    mutationFn: ({ categoryId, totalQuestions }: { categoryId: number; totalQuestions?: number }) =>
      startPractice(categoryId, totalQuestions),
    ...options,
  });
}

export function useStartMockExam(options?: { onSuccess?: (data: SessionData) => void; onError?: (error: unknown) => void }) {
  return useMutation({ mutationFn: (request: MockExamRequest) => startMockExam(request), ...options });
}

export function useSubmitAnswer(sessionId: number) {
  return useMutation({
    mutationFn: ({
      questionId,
      selectedOptionKey,
      responseTimeMs,
    }: {
      questionId: number;
      selectedOptionKey: string;
      responseTimeMs?: number;
    }) => submitAnswer(sessionId, questionId, selectedOptionKey, responseTimeMs),
  });
}

export function useCompleteSession(sessionId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => completeSession(sessionId),
    onSuccess: (report) => {
      // Placement/daily completion can change current_level_id and placement_completed_at.
      queryClient.invalidateQueries({ queryKey: AUTH_QUERY_KEY });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['gamification'] });
      showRewardToast(report.rewards);
    },
  });
}

export function useReport(sessionId: number) {
  return useQuery({ queryKey: ['sessions', sessionId, 'report'], queryFn: () => getReport(sessionId) });
}

export function useExplainAnswer(sessionId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (answerId: number) => explainAnswer(sessionId, answerId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sessions', sessionId, 'report'] });
    },
  });
}
