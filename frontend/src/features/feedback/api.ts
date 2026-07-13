import { api } from '@/lib/api';
import type { FeedbackEntry, FeedbackInput, FeedbackListPage, FeedbackListParams, FeedbackStats } from './types';

export async function submitFeedback(input: FeedbackInput): Promise<FeedbackEntry> {
  const { data } = await api.post<{ data: FeedbackEntry }>('/feedback', input);
  return data.data;
}

export async function fetchMyFeedback(): Promise<FeedbackEntry[]> {
  const { data } = await api.get<{ data: FeedbackEntry[] }>('/feedback/mine');
  return data.data;
}

export async function fetchAdminFeedback(params: FeedbackListParams): Promise<FeedbackListPage> {
  const { data } = await api.get<FeedbackListPage>('/admin/feedback', { params });
  return data;
}

export async function fetchFeedbackStats(includeDemo: boolean): Promise<FeedbackStats> {
  const { data } = await api.get<{ data: FeedbackStats }>('/admin/feedback/stats', {
    params: { include_demo: includeDemo },
  });
  return data.data;
}

export async function markFeedbackReviewed(id: number): Promise<FeedbackEntry> {
  const { data } = await api.post<{ data: FeedbackEntry }>(`/admin/feedback/${id}/review`);
  return data.data;
}
