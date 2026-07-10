import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  approveAiQuestion,
  createAdminUser,
  createCategory,
  createQuestion,
  deleteCategory,
  deleteQuestion,
  deleteUser,
  fetchAdminCategories,
  fetchAdminLevels,
  fetchAdminQuestion,
  fetchAdminQuestions,
  fetchAdminUsers,
  fetchAiQuestionDrafts,
  generateAiQuestions,
  rejectAiQuestion,
  updateCategory,
  updateQuestion,
  updateUserRole,
  uploadQuestionImage,
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
