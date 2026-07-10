import { api } from '@/lib/api';
import type { Badge, GamificationSummary, Leaderboard, Mission } from './types';

export async function fetchGamificationSummary(): Promise<GamificationSummary> {
  const { data } = await api.get<{ data: GamificationSummary }>('/gamification/summary');
  return data.data;
}

export async function fetchBadges(): Promise<Badge[]> {
  const { data } = await api.get<{ data: Badge[] }>('/gamification/badges');
  return data.data;
}

export async function fetchMissions(): Promise<Mission[]> {
  const { data } = await api.get<{ data: Mission[] }>('/gamification/missions');
  return data.data;
}

export async function claimMission(code: string): Promise<{ mission: Mission; summary: GamificationSummary }> {
  const { data } = await api.post<{ data: Mission; summary: GamificationSummary }>(`/gamification/missions/${code}/claim`);
  return { mission: data.data, summary: data.summary };
}

export async function fetchLeaderboard(): Promise<Leaderboard> {
  const { data } = await api.get<{ data: Leaderboard }>('/gamification/leaderboard');
  return data.data;
}
