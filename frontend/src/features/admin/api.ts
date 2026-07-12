import { api } from '@/lib/api';
import type {
  AdminCategory,
  AdminLevel,
  AdminQuestion,
  AdminUser,
  AiQuestionDraft,
  PaginatedResponse,
  SourceDocument,
  SourceDocumentType,
  StudyNote,
  VisualQuestionPreview,
} from './types';

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

export type QuestionPayload = Omit<AdminQuestion, 'id' | 'image_path' | 'category' | 'level'> & {
  /** Only set by the Visual Question Generator flow - ordinary text/image
   * questions get their image via the separate uploadQuestionImage() call
   * after creation instead. */
  image_path?: string | null;
};

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

export async function generateVisualQuestionPreview(payload: {
  pattern_type: 'shape_rotation';
  level_id: number;
}): Promise<VisualQuestionPreview> {
  const { data } = await api.post<{ data: VisualQuestionPreview }>('/admin/questions/generate-visual-preview', payload);
  return data.data;
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
  source_document_id?: number;
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

export async function bulkApproveAiQuestions(ids: number[]): Promise<{ approved_count: number; skipped_ids: number[] }> {
  const { data } = await api.post<{ data: { approved_count: number; skipped_ids: number[] } }>('/admin/ai-questions/bulk-approve', { ids });
  return data.data;
}

// --- Knowledge & Question Source Library (PDF ingestion) ---
export async function fetchSourceDocuments(): Promise<PaginatedResponse<SourceDocument>> {
  const { data } = await api.get<PaginatedResponse<SourceDocument>>('/admin/source-documents');
  return data;
}

export async function uploadSourceDocument(payload: {
  file: File;
  title: string;
  document_type: SourceDocumentType;
  year?: string;
}): Promise<SourceDocument> {
  const formData = new FormData();
  formData.append('file', payload.file);
  formData.append('title', payload.title);
  formData.append('document_type', payload.document_type);
  if (payload.year) formData.append('year', payload.year);
  const { data } = await api.post<{ data: SourceDocument }>('/admin/source-documents', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data.data;
}

export async function analyzeSourceDocument(id: number): Promise<SourceDocument> {
  const { data } = await api.post<{ data: SourceDocument }>(`/admin/source-documents/${id}/analyze`);
  return data.data;
}

export async function deleteSourceDocument(id: number): Promise<void> {
  await api.delete(`/admin/source-documents/${id}`);
}

// --- Study notes (theory-book -> teaching notes, draft -> human review -> publish) ---
export async function fetchAdminStudyNotes(status: string): Promise<PaginatedResponse<StudyNote>> {
  const { data } = await api.get<PaginatedResponse<StudyNote>>('/admin/study-notes', { params: { status } });
  return data;
}

export async function generateStudyNote(payload: { source_document_id: number; category_id?: number }): Promise<StudyNote> {
  const { data } = await api.post<{ data: StudyNote }>('/admin/study-notes/generate', payload);
  return data.data;
}

export async function publishStudyNote(id: number): Promise<StudyNote> {
  const { data } = await api.post<{ data: StudyNote }>(`/admin/study-notes/${id}/publish`);
  return data.data;
}

export async function rejectStudyNote(id: number): Promise<StudyNote> {
  const { data } = await api.post<{ data: StudyNote }>(`/admin/study-notes/${id}/reject`);
  return data.data;
}
