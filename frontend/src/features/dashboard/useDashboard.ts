import { useQuery } from '@tanstack/react-query';
import { fetchDashboardSummary, fetchProgressHistory } from './api';

export function useDashboardSummary() {
  return useQuery({ queryKey: ['dashboard', 'summary'], queryFn: fetchDashboardSummary });
}

export function useProgressHistory() {
  return useQuery({ queryKey: ['dashboard', 'progress-history'], queryFn: fetchProgressHistory });
}
