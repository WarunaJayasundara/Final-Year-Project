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

async function fetchOverview(includeDemo: boolean): Promise<CohortOverview> {
  const { data } = await api.get<{ data: CohortOverview }>('/admin/analytics/overview', { params: { include_demo: includeDemo } });
  return data.data;
}

export function useCohortOverview(includeDemo = false) {
  return useQuery({ queryKey: ['admin', 'analytics', 'overview', includeDemo], queryFn: () => fetchOverview(includeDemo) });
}

/**
 * Downloads via the authenticated axios client (responseType: 'blob') rather
 * than a plain <a href> pointed at the API URL - a raw browser navigation to
 * an API route doesn't reliably carry the same Accept/session context as an
 * XHR request, and previously produced a fatal 500 (see Handler::unauthenticated
 * and Authenticate::redirectTo for the matching backend fix). The blob is
 * turned into a temporary object URL only to trigger the browser's native
 * save-file dialog, then immediately revoked.
 */
async function downloadPairedScoresCsv(includeDemo: boolean): Promise<void> {
  const response = await api.get('/admin/analytics/paired-scores.csv', {
    responseType: 'blob',
    params: { include_demo: includeDemo },
  });
  const url = window.URL.createObjectURL(new Blob([response.data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'paired-scores.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}

export function useDownloadPairedScoresCsv(options?: { onError?: () => void }) {
  return useMutation({
    mutationFn: (includeDemo: boolean) => downloadPairedScoresCsv(includeDemo),
    onError: () => options?.onError?.(),
  });
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

export interface MlOverview {
  students_with_prediction: number;
  average_readiness_percent: number | null;
  label_distribution: Record<string, number>;
  model: Record<string, unknown> | null;
}

async function fetchMlOverview(includeDemo: boolean): Promise<MlOverview> {
  const { data } = await api.get<{ data: MlOverview }>('/admin/analytics/ml-overview', { params: { include_demo: includeDemo } });
  return data.data;
}

export function useMlOverview(includeDemo = false) {
  return useQuery({ queryKey: ['admin', 'analytics', 'ml-overview', includeDemo], queryFn: () => fetchMlOverview(includeDemo) });
}

export interface MlResearchReports {
  evaluation: Record<string, unknown> | null;
  explainability: Record<string, unknown> | null;
  registry: { versions: Array<Record<string, unknown>>; live_version: string | null } | null;
}

async function fetchMlResearchReports(): Promise<MlResearchReports> {
  const { data } = await api.get<{ data: MlResearchReports }>('/admin/analytics/ml-research-reports');
  return data.data;
}

export function useMlResearchReports() {
  return useQuery({ queryKey: ['admin', 'analytics', 'ml-research-reports'], queryFn: fetchMlResearchReports });
}
