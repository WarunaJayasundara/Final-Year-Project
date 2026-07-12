import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  fetchDueToday,
  fetchPracticeQuestions,
  fetchRecommendation,
  fetchStudyNote,
  fetchStudyNotes,
  submitReview,
} from './api';

export function useStudyNotes(categoryId?: number) {
  return useQuery({
    queryKey: ['study-notes', categoryId ?? 'all'],
    queryFn: () => fetchStudyNotes(categoryId),
  });
}

export function useStudyNote(id: number | undefined) {
  return useQuery({
    queryKey: ['study-notes', 'detail', id],
    queryFn: () => fetchStudyNote(id as number),
    enabled: id !== undefined,
  });
}

export function useDueToday() {
  return useQuery({ queryKey: ['study-notes', 'due-today'], queryFn: fetchDueToday });
}

export function useStudyNoteRecommendation() {
  return useQuery({ queryKey: ['study-notes', 'recommendation'], queryFn: fetchRecommendation });
}

export function usePracticeQuestions(studyNoteId: number | undefined) {
  return useQuery({
    queryKey: ['study-notes', 'practice', studyNoteId],
    queryFn: () => fetchPracticeQuestions(studyNoteId as number),
    enabled: studyNoteId !== undefined,
  });
}

export function useSubmitReview() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ studyNoteId, result }: { studyNoteId: number; result: 'again' | 'hard' | 'good' | 'easy' }) =>
      submitReview(studyNoteId, result),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['study-notes', 'due-today'] });
    },
  });
}
