export interface ExamCategoryOption {
  code: string;
  label: string;
}

export interface ExamProfile {
  exam_category: string;
  exam_category_label: string;
  exam_name: string | null;
  exam_date: string | null;
  daily_study_hours_target: number;
  target_score: number | null;
  days_remaining: number | null;
  prep_progress_percent: number | null;
}

export interface ExamProfileInput {
  exam_category: string;
  exam_name: string | null;
  exam_date: string | null;
  daily_study_hours_target: number;
  target_score: number | null;
}

export interface CategoryRef {
  category_id: number;
  code: string;
  name_en: string;
  name_si: string;
  accuracy_percent: number;
}

export type StudyPlanPhase = 'foundation' | 'practice' | 'intensive' | 'final_revision' | 'exam_day';

export interface DailyPlanBlock {
  activity: string;
  category: CategoryRef | null;
  minutes: number | null;
}

export type WeeklyDayFocus = 'weak_1' | 'weak_2' | 'mixed' | 'mock' | 'rest' | 'rest_light';

export interface WeeklyScheduleDay {
  day: string;
  focus: WeeklyDayFocus;
  category: CategoryRef | null;
}

export interface PhaseTimelineEntry {
  phase: StudyPlanPhase;
  label_en: string;
  label_si: string;
  from_days_remaining: number | null;
  to_days_remaining: number | null;
  is_current: boolean;
}

export interface StudyPlan {
  phase: StudyPlanPhase;
  exam_category: string | null;
  exam_name: string | null;
  days_remaining: number | null;
  weeks_remaining: number | null;
  weak_categories: CategoryRef[];
  strongest_category: CategoryRef | null;
  recommended_daily_questions: number;
  recommended_weekly_mock_tests: number;
  daily_plan: DailyPlanBlock[];
  weekly_schedule: WeeklyScheduleDay[];
  phase_timeline: PhaseTimelineEntry[];
}
