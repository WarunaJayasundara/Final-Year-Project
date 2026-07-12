import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  analyzeSourceDocument,
  approveAiQuestion,
  bulkApproveAiQuestions,
  createAdminUser,
  createCategory,
  createQuestion,
  deleteCategory,
  deleteQuestion,
  deleteSourceDocument,
  deleteUser,
  fetchAdminCategories,
  fetchAdminLevels,
  fetchAdminQuestion,
  fetchAdminQuestions,
  fetchAdminStudyNotes,
  fetchAdminUsers,
  fetchAiQuestionDrafts,
  fetchSourceDocuments,
  generateAiQuestions,
  generateStudyNote,
  generateVisualQuestionPreview,
  publishStudyNote,
  rejectAiQuestion,
  rejectStudyNote,
  updateCategory,
  updateQuestion,
  updateUserRole,
  uploadQuestionImage,
  uploadSourceDocument,
  type QuestionFilters,
  type QuestionPayload,
} from './api';
import type { AdminCategory, AdminUser } from './types';

export function useAdminCategories() {
  return useQuery({ queryKey: ['admin', 'categories'], queryFn: fetchAdminCategories });
}

export function useAdminLevels() {
  return useQuery({ queryKey: ['admin', 'levels'], queryFn: fetchAdminLevels });
}

export function useCreateCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<AdminCategory>) => createCategory(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'categories'] }),
  });
}

export function useUpdateCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<AdminCategory> }) => updateCategory(id, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'categories'] }),
  });
}

export function useDeleteCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteCategory(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'categories'] }),
  });
}

export function useAdminQuestions(filters: QuestionFilters) {
  return useQuery({ queryKey: ['admin', 'questions', filters], queryFn: () => fetchAdminQuestions(filters) });
}

export function useAdminQuestion(id: number | undefined) {
  return useQuery({
    queryKey: ['admin', 'questions', id],
    queryFn: () => fetchAdminQuestion(id as number),
    enabled: id !== undefined,
  });
}

export function useCreateQuestion() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: QuestionPayload) => createQuestion(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'questions'] }),
  });
}

export function useUpdateQuestion() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<QuestionPayload> }) => updateQuestion(id, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'questions'] }),
  });
}

export function useDeleteQuestion() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteQuestion(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'questions'] }),
  });
}

export function useGenerateVisualQuestionPreview() {
  return useMutation({ mutationFn: generateVisualQuestionPreview });
}

export function useUploadQuestionImage() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, file }: { id: number; file: File }) => uploadQuestionImage(id, file),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'questions'] }),
  });
}

export function useAdminUsers(search?: string) {
  return useQuery({ queryKey: ['admin', 'users', search], queryFn: () => fetchAdminUsers(search) });
}

export function useCreateAdminUser() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createAdminUser,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'users'] }),
  });
}

export function useUpdateUserRole() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, role }: { id: number; role: AdminUser['role'] }) => updateUserRole(id, role),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'users'] }),
  });
}

export function useDeleteUser() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteUser(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'users'] }),
  });
}

export function useAiQuestionDrafts(status: string) {
  return useQuery({ queryKey: ['admin', 'ai-questions', status], queryFn: () => fetchAiQuestionDrafts(status) });
}

export function useGenerateAiQuestions() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: generateAiQuestions,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'ai-questions'] }),
  });
}

export function useApproveAiQuestion() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => approveAiQuestion(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'ai-questions'] }),
  });
}

export function useRejectAiQuestion() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => rejectAiQuestion(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'ai-questions'] }),
  });
}

export function useBulkApproveAiQuestions() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (ids: number[]) => bulkApproveAiQuestions(ids),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'ai-questions'] }),
  });
}

export function useSourceDocuments() {
  return useQuery({ queryKey: ['admin', 'source-documents'], queryFn: fetchSourceDocuments });
}

export function useUploadSourceDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: uploadSourceDocument,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'source-documents'] }),
  });
}

export function useAnalyzeSourceDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => analyzeSourceDocument(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'source-documents'] }),
  });
}

export function useDeleteSourceDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteSourceDocument(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'source-documents'] }),
  });
}

export function useAdminStudyNotes(status: string) {
  return useQuery({ queryKey: ['admin', 'study-notes', status], queryFn: () => fetchAdminStudyNotes(status) });
}

export function useGenerateStudyNote() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: generateStudyNote,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'study-notes'] }),
  });
}

export function usePublishStudyNote() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => publishStudyNote(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'study-notes'] }),
  });
}

export function useRejectStudyNote() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => rejectStudyNote(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'study-notes'] }),
  });
}
