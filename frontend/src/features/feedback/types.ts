export interface FeedbackInput {
  overall_rating: number;
  ui_rating?: number | null;
  question_quality_rating?: number | null;
  sinhala_quality_rating?: number | null;
  usefulness_rating?: number | null;
  comment?: string | null;
  suggestion?: string | null;
}

export interface FeedbackEntry {
  id: number;
  user_name?: string | null;
  overall_rating: number;
  ui_rating: number | null;
  question_quality_rating: number | null;
  sinhala_quality_rating: number | null;
  usefulness_rating: number | null;
  comment: string | null;
  suggestion: string | null;
  locale?: 'en' | 'si';
  status?: 'new' | 'reviewed';
  is_demo_feedback?: boolean;
  created_at: string;
}

export interface FeedbackStats {
  total_count: number;
  new_count: number;
  averages: {
    overall_rating: number | null;
    ui_rating: number | null;
    question_quality_rating: number | null;
    sinhala_quality_rating: number | null;
    usefulness_rating: number | null;
  };
  distribution: Record<string, number>;
  top_terms: { term: string; count: number }[];
}

export interface FeedbackListPage {
  data: FeedbackEntry[];
  current_page: number;
  last_page: number;
  total: number;
}

export interface FeedbackListParams {
  page?: number;
  status?: 'new' | 'reviewed';
  min_rating?: number;
  locale?: 'en' | 'si';
  include_demo?: boolean;
}
