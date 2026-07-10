export interface CategoryStrength {
  category_id: number;
  code: string;
  name_en: string;
  name_si: string;
  accuracy_percent: number | null;
}

export interface RecentSession {
  id: number;
  session_type: 'placement' | 'daily' | 'practice';
  category_name: string | null;
  score_percent: number;
  completed_at: string;
}

export interface GameScoreSummary {
  game_code: string;
  game_name: string;
  best_score: number;
  plays: number;
}

export interface IqEstimate {
  iq_score: number;
  method: 'irt_theta';
  theta: number;
  theta_se: number | null;
}

export interface DashboardSummary {
  current_level: { id: number; level_number: number; name_en: string; name_si: string } | null;
  placement_completed_at: string | null;
  streak_days: number;
  category_strengths: CategoryStrength[];
  recent_sessions: RecentSession[];
  game_scores: GameScoreSummary[];
  iq_estimate: IqEstimate | null;
}

export interface ProgressHistory {
  level_history: { date: string; level_number: number }[];
  accuracy_history: { date: string; accuracy_percent: number }[];
}
