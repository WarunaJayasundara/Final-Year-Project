export interface GamificationSummary {
  xp: number;
  coins: number;
  level: number;
  level_title: string;
  xp_into_level: number;
  xp_for_next_level: number;
  progress_percent: number;
  streak_days: number;
  badges_earned: number;
  badges_total: number;
}

export interface BadgeReward {
  code: string;
  name_en: string;
  name_si: string;
  icon: string;
}

export interface RewardResult {
  xp: number;
  coins: number;
  new_badges: BadgeReward[];
}

export interface Badge {
  code: string;
  name_en: string;
  name_si: string;
  description_en: string;
  description_si: string;
  icon: string;
  xp_reward: number;
  coin_reward: number;
  earned_at: string | null;
}

export type MissionType = 'daily' | 'weekly';

export interface Mission {
  code: string;
  type: MissionType;
  period_key: string;
  progress: number;
  target: number;
  completed: boolean;
  claimed: boolean;
  xp_reward: number;
  coin_reward: number;
}

export interface LeaderboardEntry {
  rank: number;
  user_id: number;
  name: string;
  xp: number;
  level: number;
  is_you: boolean;
}

export interface Leaderboard {
  top: LeaderboardEntry[];
  your_rank: number | null;
}
