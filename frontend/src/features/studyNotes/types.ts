export interface StudyNoteCategory {
  id: number;
  code: string;
  name_en: string;
  name_si: string;
}

export interface StudyNote {
  id: number;
  category_id: number | null;
  subcategory: string | null;
  title_en: string;
  title_si: string;
  learning_objective_en: string | null;
  learning_objective_si: string | null;
  content_en: string;
  content_si: string;
  worked_example_en: string | null;
  worked_example_si: string | null;
  key_technique_en: string | null;
  key_technique_si: string | null;
  common_mistakes_en: string | null;
  common_mistakes_si: string | null;
  key_concepts: string[] | null;
  generation_method: 'mock' | 'gemini';
  created_at: string;
  category?: StudyNoteCategory;
}

export interface PaginatedStudyNotes {
  data: StudyNote[];
  current_page: number;
  last_page: number;
  total: number;
}

export interface StudyNoteReview {
  id: number;
  study_note_id: number;
  ease_factor: number;
  interval_days: number;
  review_count: number;
  last_result: 'again' | 'hard' | 'good' | 'easy' | null;
  next_review_at: string;
  study_note?: StudyNote;
}

export interface StudyNoteRecommendation {
  subcategory: string;
  accuracy: number;
  study_note: StudyNote;
}

export interface PracticeQuestionOption {
  key: string;
  text: string;
}

export interface PracticeQuestion {
  id: number;
  question_text: string;
  image_path: string | null;
  options: PracticeQuestionOption[];
  correct_option_key: string;
  explanation: string | null;
}
