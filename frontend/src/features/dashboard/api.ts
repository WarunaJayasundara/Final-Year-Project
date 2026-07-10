import { api } from '@/lib/api';
import type { DashboardSummary, ProgressHistory } from './types';

export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  const { data } = await api.get<{ data: DashboardSummary }>('/dashboard/summary');
  return data.data;
}

export async function fetchProgressHistory(): Promise<ProgressHistory> {
  const { data } = await api.get<{ data: ProgressHistory }>('/dashboard/progress-history');
  return data.data;
}
