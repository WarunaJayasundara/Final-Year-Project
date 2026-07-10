import { api } from '@/lib/api';
import type { AdminCategory, AdminLevel, AdminQuestion, AdminUser, AiQuestionDraft, PaginatedResponse } from './types';

// --- Categories & levels ---
export async function fetchAdminCategories(): Promise<AdminCategory[]> {
  const { data } = await api.get<{ data: AdminCategory[] }>('/admin/categories');
  return data.data;
}

export async function createCategory(payload: Partial<AdminCategory>): Promise<AdminCategory> {
  const { data } = await api.post<{ data: AdminCategory }>('/admin/categories', payload);
  return data.data;
}

export async function updateCategory(id: number, payload: Partial<AdminCategory>): Promise<AdminCategory> {
  const { data } = await api.patch<{ data: AdminCategory }>(`/admin/categories/${id}`, payload);
  return data.data;
}

export async function deleteCategory(id: number): Promise<void> {
  await api.delete(`/admin/categories/${id}`);
}

export async function fetchAdminLevels(): Promise<AdminLevel[]> {
  const { data } = await api.get<{ data: AdminLevel[] }>('/admin/levels');
  return data.data;
}

// --- Questions ---
export interface QuestionFilters {
  category_id?: number;
  level_id?: number;
  page?: number;
}

export async function fetchAdminQuestions(filters: QuestionFilters): Promise<PaginatedResponse<AdminQuestion>> {
  const { data } = await api.get<PaginatedResponse<AdminQuestion>>('/admin/questions', { params: filters });
  return data;
}

export async function fetchAdminQuestion(id: number): Promise<AdminQuestion> {
  const { data } = await api.get<{ data: AdminQuestion }>(`/admin/questions/${id}`);
  return data.data;
}

export type QuestionPayload = Omit<AdminQuestion, 'id' | 'image_path' | 'category' | 'level'>;

export async function createQuestion(payload: QuestionPayload): Promise<AdminQuestion> {
  const { data } = await api.post<{ data: AdminQuestion }>('/admin/questions', payload);
  return data.data;
}

export async function updateQuestion(id: number, payload: Partial<QuestionPayload>): Promise<AdminQuestion> {
  const { data } = await api.patch<{ data: AdminQuestion }>(`/admin/questions/${id}`, payload);
  return data.data;
}

export async function deleteQuestion(id: number): Promise<void> {
  await api.delete(`/admin/questions/${id}`);
}

export async function uploadQuestionImage(id: number, file: File): Promise<AdminQuestion> {
  const formData = new FormData();
  formData.append('image', file);
  const { data } = await api.post<{ data: AdminQuestion }>(`/admin/questions/${id}/image`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data.data;
}

// --- Users ---
export async function fetchAdminUsers(search?: string): Promise<PaginatedResponse<AdminUser>> {
  const { data } = await api.get<PaginatedResponse<AdminUser>>('/admin/users', { params: { search } });
  return data;
}

export async function createAdminUser(payload: {
  name: string;
  email: string;
  password: string;
  role: 'admin' | 'super_admin';
}): Promise<AdminUser> {
  const { data } = await api.post<{ user: AdminUser }>('/admin/users', payload);
  return data.user;
}

export async function updateUserRole(id: number, role: AdminUser['role']): Promise<AdminUser> {
  const { data } = await api.patch<{ user: AdminUser }>(`/admin/users/${id}/role`, { role });
  return data.user;
}

export async function deleteUser(id: number): Promise<void> {
  await api.delete(`/admin/users/${id}`);
}

// --- AI question generation (draft -> human review -> promote) ---
export async function fetchAiQuestionDrafts(status: string): Promise<PaginatedResponse<AiQuestionDraft>> {
  const { data } = await api.get<PaginatedResponse<AiQuestionDraft>>('/admin/ai-questions', { params: { status } });
  return data;
}

export async function generateAiQuestions(payload: {
  category_id: number;
  level_id: number;
  count: number;
  exam_category?: string;
}): Promise<AiQuestionDraft[]> {
  const { data } = await api.post<{ data: AiQuestionDraft[] }>('/admin/ai-questions/generate', payload);
  return data.data;
}

export async function approveAiQuestion(id: number): Promise<void> {
  await api.post(`/admin/ai-questions/${id}/approve`);
}

export async function rejectAiQuestion(id: number): Promise<void> {
  await api.post(`/admin/ai-questions/${id}/reject`);
}
