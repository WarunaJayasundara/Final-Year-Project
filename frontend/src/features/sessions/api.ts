import { api } from '@/lib/api';
import type { AdaptiveSessionData, Category, MockExamRequest, SessionData, SessionReport, SubmitAnswerResult } from './types';

export async function fetchCategories(): Promise<Category[]> {
  const { data } = await api.get<{ data: Category[] }>('/categories');
  return data.data;
}

export async function startPlacement(): Promise<AdaptiveSessionData> {
  const { data } = await api.post<{ data: AdaptiveSessionData }>('/sessions/placement/start');
  return data.data;
}

export async function startDaily(totalQuestions?: number): Promise<SessionData> {
  const { data } = await api.post<{ data: SessionData }>('/sessions/daily/start', {
    total_questions: totalQuestions,
  });
  return data.data;
}

export async function startPractice(categoryId: number, totalQuestions?: number): Promise<SessionData> {
  const { data } = await api.post<{ data: SessionData }>('/sessions/practice/start', {
    category_id: categoryId,
    total_questions: totalQuestions,
  });
  return data.data;
}

export async function startMockExam(request: MockExamRequest): Promise<SessionData> {
  const { data } = await api.post<{ data: SessionData }>('/mock-exams', request);
  return data.data;
}

export async function getSession(sessionId: number): Promise<SessionData> {
  const { data } = await api.get<{ data: SessionData }>(`/sessions/${sessionId}`);
  return data.data;
}

export async function submitAnswer(
  sessionId: number,
  questionId: number,
  selectedOptionKey: string,
  responseTimeMs?: number,
): Promise<SubmitAnswerResult> {
  const { data } = await api.post<{ data: SubmitAnswerResult }>(
    `/sessions/${sessionId}/answers`,
    { question_id: questionId, selected_option_key: selectedOptionKey, response_time_ms: responseTimeMs },
  );
  return data.data;
}

export async function completeSession(sessionId: number): Promise<SessionReport> {
  const { data } = await api.post<{ data: SessionReport }>(`/sessions/${sessionId}/complete`);
  return data.data;
}

export async function getReport(sessionId: number): Promise<SessionReport> {
  const { data } = await api.get<{ data: SessionReport }>(`/sessions/${sessionId}/report`);
  return data.data;
}

export async function explainAnswer(sessionId: number, answerId: number): Promise<string> {
  const { data } = await api.post<{ data: { ai_feedback_text: string } }>(
    `/sessions/${sessionId}/answers/${answerId}/explain`,
  );
  return data.data.ai_feedback_text;
}
