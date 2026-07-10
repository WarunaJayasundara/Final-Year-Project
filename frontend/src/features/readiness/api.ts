import { api } from '@/lib/api';
import type { BadgeReward } from '@/features/gamification/types';
import type { CheckinInput, DailyCheckin, ReadinessHistoryPoint, ReadinessPrediction } from './types';

export async function fetchLatestReadiness(): Promise<ReadinessPrediction | null> {
  const { data } = await api.get<{ data: ReadinessPrediction | null }>('/readiness/latest');
  return data.data;
}

export async function runReadinessPrediction(): Promise<{ prediction: ReadinessPrediction; newBadges: BadgeReward[] }> {
  const { data } = await api.post<{ data: ReadinessPrediction; new_badges: BadgeReward[] }>('/readiness/predict');
  return { prediction: data.data, newBadges: data.new_badges };
}

export async function fetchReadinessHistory(): Promise<ReadinessHistoryPoint[]> {
  const { data } = await api.get<{ data: ReadinessHistoryPoint[] }>('/readiness/history');
  return data.data;
}

export async function fetchTodayCheckin(): Promise<DailyCheckin | null> {
  const { data } = await api.get<{ data: DailyCheckin | null }>('/checkins/today');
  return data.data;
}

export async function submitCheckin(input: CheckinInput): Promise<DailyCheckin> {
  const { data } = await api.post<{ data: DailyCheckin }>('/checkins', input);
  return data.data;
}
