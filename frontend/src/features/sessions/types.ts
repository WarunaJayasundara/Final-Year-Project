export type SessionType = 'placement' | 'daily' | 'practice' | 'mock';

export interface QuestionOption {
  key: string;
  text: string;
  image_path: string | null;
}

export interface SessionQuestion {
  id: number;
  category_id: number;
  level_id: number;
  question_type: 'mcq_text' | 'mcq_image';
  question_text: string;
  image_path: string | null;
  options: QuestionOption[];
  answer_id: number;
  answered: boolean;
  expected_time_seconds: number;
  /** Only ever present on completed-session report answers - live/unanswered
   * question payloads never include this (see Question::toClientArray()). */
  explanation?: string | null;
}

export interface SessionData {
  id: number;
  session_type: SessionType;
  is_adaptive: false;
  category_id: number | null;
  level_id: number;
  total_questions: number;
  completed_at: string | null;
  questions: SessionQuestion[];
  // Set for mock exams only (a real requested duration) - null/absent for
  // placement/daily/practice sessions, which have no wall-clock time limit.
  time_limit_seconds?: number | null;
}

export interface MockExamRequest {
  total_questions?: number;
  duration_minutes?: number;
  scope?: 'full_syllabus' | 'selected_categories';
  category_ids?: number[];
  difficulty_mode?: 'standard' | 'adaptive';
}

/**
 * The placement test is delivered as a real computerized adaptive test (CAT):
 * one item at a time, each chosen to maximize information at the student's
 * current ability estimate (theta), re-estimated after every answer. See
 * backend App\Http\Controllers\Sessions\TestSessionController for the
 * Rasch-model item selection + MLE ability estimation this drives.
 */
export interface AdaptiveSessionData {
  id: number;
  session_type: 'placement';
  is_adaptive: true;
  max_items: number;
  min_items: number;
  items_answered: number;
  completed_at: string | null;
  current_question: SessionQuestion;
}

export interface SubmitAnswerResult {
  question_id: number;
  is_correct: boolean;
  correct_option_key: string;
  // Present only when answering within an adaptive (placement) session.
  items_answered?: number;
  theta?: number;
  theta_se?: number;
  ready_to_complete?: boolean;
  next_question?: SessionQuestion | null;
}

export interface ReportAnswer {
  answer_id: number;
  question: SessionQuestion;
  correct_option_key: string;
  selected_option_key: string | null;
  is_correct: boolean;
  ai_feedback_text: string | null;
}

export interface SessionReport {
  id: number;
  session_type: SessionType;
  total_questions: number;
  correct_count: number;
  score_percent: number;
  level_before_id: number | null;
  level_after_id: number | null;
  completed_at: string;
  answers: ReportAnswer[];
  // Present only on the response from the completion call itself, not on
  // later GETs of the same (already-completed) report.
  rewards?: import('@/features/gamification/types').RewardResult;
}

export interface Category {
  id: number;
  code: string;
  name_en: string;
  name_si: string;
  description_en: string | null;
  description_si: string | null;
  icon: string | null;
}
