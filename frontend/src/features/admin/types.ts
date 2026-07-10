export interface AdminCategory {
  id: number;
  code: string;
  name_en: string;
  name_si: string;
  description_en: string | null;
  description_si: string | null;
  icon: string | null;
}

export interface AdminLevel {
  id: number;
  level_number: number;
  name_en: string;
  name_si: string;
}

export interface QuestionOptionInput {
  key: string;
  text_en: string;
  text_si: string;
}

export interface AdminQuestion {
  id: number;
  category_id: number;
  level_id: number;
  question_type: 'mcq_text' | 'mcq_image';
  question_text_en: string;
  question_text_si: string;
  image_path: string | null;
  options: QuestionOptionInput[];
  correct_option_key: string;
  explanation_en: string | null;
  explanation_si: string | null;
  difficulty_weight: number;
  is_active: boolean;
  category?: AdminCategory;
  level?: AdminLevel;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

export interface AiQuestionDraft {
  id: number;
  category_id: number;
  level_id: number;
  question_type: string;
  question_text_en: string;
  question_text_si: string;
  options: QuestionOptionInput[];
  correct_option_key: string;
  explanation_en: string | null;
  explanation_si: string | null;
  difficulty_weight: number;
  source: 'mock' | 'gemini';
  status: 'pending' | 'approved' | 'rejected';
  created_at: string;
  category?: AdminCategory;
  level?: AdminLevel;
}

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  role: 'super_admin' | 'admin' | 'user';
  auth_provider: 'google' | 'password';
  current_level_id: number | null;
  created_at: string;
}
