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
  subcategory?: string | null;
  question_text_en: string;
  question_text_si: string;
  image_path: string | null;
  options: QuestionOptionInput[];
  correct_option_key: string;
  explanation_en: string | null;
  explanation_si: string | null;
  difficulty_weight: number;
  exam_tags?: string[] | null;
  solving_time_seconds?: number | null;
  bloom_level?: string | null;
  cognitive_skill?: string | null;
  generation_rule?: string | null;
  transformation_steps?: Record<string, unknown> | null;
  visual_complexity_score?: number | null;
  is_active: boolean;
  category?: AdminCategory;
  level?: AdminLevel;
}

/** Ephemeral preview from the Pattern/Visual Question Generator - not yet
 * persisted; image_path already points to a real written-but-orphanable
 * preview file, reused as-is if the admin saves. */
export interface VisualQuestionPreview {
  image_path: string;
  question_text_en: string;
  question_text_si: string;
  options: QuestionOptionInput[];
  correct_option_key: string;
  explanation_en: string;
  explanation_si: string;
  subcategory: string;
  difficulty_weight: number;
  solving_time_seconds: number;
  bloom_level: string;
  cognitive_skill: string;
  generation_rule: string;
  transformation_steps: Record<string, unknown>;
  visual_complexity_score: number;
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
  source_document_id?: number | null;
  quality_score?: number | null;
  sinhala_review_status?: 'pending' | 'approved' | 'needs_review';
  translation_quality_score?: number | null;
  semantic_equivalence_score?: number | null;
  category?: AdminCategory;
  level?: AdminLevel;
}

export type SourceDocumentType = 'past_paper' | 'iq_book' | 'exam_guide' | 'theory_book' | 'other';
export type SourceDocumentStatus = 'pending' | 'analyzing' | 'analyzed' | 'failed';

export interface MatchedTopic {
  topic: string;
  keyword_matches: number;
  matched_keywords: string[];
}

export interface KnowledgeMapChapter {
  chapter: string;
  topics: MatchedTopic[];
  excerpt_char_count: number;
}

export interface SourceDocument {
  id: number;
  title: string;
  document_type: SourceDocumentType;
  exam_type_tags: string[] | null;
  year: string | null;
  file_path: string;
  analysis_status: SourceDocumentStatus;
  extracted_topics: MatchedTopic[] | null;
  detected_patterns: Record<string, number> | null;
  extracted_theory_concepts: KnowledgeMapChapter[] | null;
  reliability_note: string | null;
  created_at: string;
}

export type StudyNoteStatus = 'draft' | 'published' | 'rejected';

export interface StudyNote {
  id: number;
  source_document_id: number;
  category_id: number | null;
  subcategory: string | null;
  title_en: string;
  title_si: string;
  content_en: string;
  content_si: string;
  key_concepts: string[] | null;
  generation_method: 'mock' | 'gemini';
  status: StudyNoteStatus;
  created_at: string;
  category?: AdminCategory;
  source_document?: SourceDocument;
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
