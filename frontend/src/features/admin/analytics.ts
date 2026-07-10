import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';

export interface CohortOverview {
  total_students: number;
  placement_completed: number;
  sessions_completed: number;
  average_score_percent: number | null;
  level_distribution: { level_number: number; total: number }[];
  category_accuracy: { category_code: string; category_name: string; accuracy_percent: string; answers_count: number }[];
}

async function fetchOverview(): Promise<CohortOverview> {
  const { data } = await api.get<{ data: CohortOverview }>('/admin/analytics/overview');
  return data.data;
}

export function useCohortOverview() {
  return useQuery({ queryKey: ['admin', 'analytics', 'overview'], queryFn: fetchOverview });
}

export function pairedScoresCsvUrl(): string {
  return '/api/admin/analytics/paired-scores.csv';
}

export interface PsychometricsSummary {
  total_items: number;
  calibrated_items: number;
  cohort_size: number;
  theta_mean: number | null;
  theta_sd: number | null;
  mean_se: number | null;
  marginal_reliability: number | null;
}

export interface CategoryDifficulty {
  name_en: string;
  name_si: string;
  calibrated_count: number;
  mean_difficulty: number;
  min_difficulty: number;
  max_difficulty: number;
}

export interface ItemDiscriminationEntry {
  question_id: number;
  question_text_en: string;
  responses: number;
  discrimination: number;
}

export interface Psychometrics {
  summary: PsychometricsSummary;
  category_difficulty: CategoryDifficulty[];
  discrimination: {
    top: ItemDiscriminationEntry[];
    bottom: ItemDiscriminationEntry[];
    items_analyzed: number;
  };
}

async function fetchPsychometrics(): Promise<Psychometrics> {
  const { data } = await api.get<{ data: Psychometrics }>('/admin/analytics/psychometrics');
  return data.data;
}

export function usePsychometrics() {
  return useQuery({ queryKey: ['admin', 'analytics', 'psychometrics'], queryFn: fetchPsychometrics });
}

async function recalibrate(): Promise<{ calibrated_items: number; total_responses: number; skipped_low_data: number }> {
  const { data } = await api.post<{ data: { calibrated_items: number; total_responses: number; skipped_low_data: number } }>(
    '/admin/analytics/recalibrate',
  );
  return data.data;
}

export function useRecalibrate(options?: {
  onSuccess?: (result: { calibrated_items: number; total_responses: number; skipped_low_data: number }) => void;
}) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: recalibrate,
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'analytics', 'psychometrics'] });
      options?.onSuccess?.(result);
    },
  });
}

export interface QuestionBankStats {
  total_active: number;
  total_retired: number;
  by_type: Record<string, number>;
  by_category: Record<string, { name_en: string; total: number; by_level: Record<string, number> }>;
  by_subcategory: Record<string, Record<string, number>>;
  by_bloom_level: Record<string, number>;
  untagged_count: number;
}

async function fetchQuestionBankStats(): Promise<QuestionBankStats> {
  const { data } = await api.get<{ data: QuestionBankStats }>('/admin/analytics/question-bank');
  return data.data;
}

export function useQuestionBankStats() {
  return useQuery({ queryKey: ['admin', 'analytics', 'question-bank'], queryFn: fetchQuestionBankStats });
}
