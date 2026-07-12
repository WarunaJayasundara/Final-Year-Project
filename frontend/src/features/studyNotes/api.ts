import { api } from '@/lib/api';
import type {
  PaginatedStudyNotes,
  PracticeQuestion,
  StudyNote,
  StudyNoteRecommendation,
  StudyNoteReview,
} from './types';

export async function fetchStudyNotes(categoryId?: number): Promise<PaginatedStudyNotes> {
  const { data } = await api.get<PaginatedStudyNotes>('/study-notes', {
    params: categoryId ? { category_id: categoryId } : undefined,
  });
  return data;
}

export async function fetchStudyNote(id: number): Promise<StudyNote> {
  const { data } = await api.get<{ data: StudyNote }>(`/study-notes/${id}`);
  return data.data;
}

export async function fetchDueToday(): Promise<StudyNoteReview[]> {
  const { data } = await api.get<{ data: StudyNoteReview[] }>('/study-notes/due-today');
  return data.data;
}

export async function fetchRecommendation(): Promise<StudyNoteRecommendation | null> {
  const { data } = await api.get<{ data: StudyNoteRecommendation | null }>('/study-notes/recommendation');
  return data.data;
}

export async function fetchPracticeQuestions(studyNoteId: number): Promise<PracticeQuestion[]> {
  const { data } = await api.get<{ data: PracticeQuestion[] }>(`/study-notes/${studyNoteId}/practice-questions`);
  return data.data;
}

export async function submitReview(studyNoteId: number, result: 'again' | 'hard' | 'good' | 'easy'): Promise<StudyNoteReview> {
  const { data } = await api.post<{ data: StudyNoteReview }>(`/study-notes/${studyNoteId}/review`, { result });
  return data.data;
}
