export type ReadinessLabel = 'ready' | 'almost_ready' | 'needs_improvement' | 'high_risk';

export interface ReadinessReason {
  feature: string;
  message: string;
  direction: 'positive' | 'negative';
  impact: number;
}

export interface ReadinessPrediction {
  readiness_percent: number;
  readiness_label: ReadinessLabel;
  reasons: ReadinessReason[];
  model_version: string;
  predicted_at: string;
}

export interface ReadinessHistoryPoint {
  predicted_at: string;
  readiness_percent: number;
  readiness_label: ReadinessLabel;
}

export interface DailyCheckin {
  id: number;
  checkin_date: string;
  study_hours: number;
  motivation_score: number;
  attended: boolean;
}

export interface CheckinInput {
  study_hours: number;
  motivation_score: number;
  attended: boolean;
}
