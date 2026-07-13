export type ReadinessLabel = 'ready' | 'almost_ready' | 'needs_improvement' | 'high_risk';

export interface ReadinessReason {
  feature: string;
  message: string;
  direction: 'positive' | 'negative';
  impact: number;
}

export interface RiskOfDroppingPractice {
  probability: number;
  at_risk: boolean;
}

export interface PredictedScoreRange {
  low: number;
  high: number;
}

export type ReadinessType = 'general' | 'exam_specific';

export interface ReadinessPrediction {
  readiness_percent: number;
  readiness_label: ReadinessLabel;
  // Which kind of readiness this number represents - see ReadinessController::present().
  // Optional so a prediction fetched before this field existed still renders (defaults to 'general' framing).
  readiness_type?: ReadinessType;
  exam_name?: string | null;
  reasons: ReadinessReason[];
  model_version: string;
  predicted_at: string;
  // Research-grade upgrade fields - all optional so a prediction made before
  // this upgrade (or served by an older deployed model) still renders fine.
  plain_english_explanation?: string | null;
  risk_of_dropping_practice?: RiskOfDroppingPractice | null;
  predicted_next_assessment_score?: number | null;
  predicted_score_change?: number | null;
  // Time-aware upgrade fields - optional for the same reason as above.
  time_management_readiness_percent?: number | null;
  predicted_score_range?: PredictedScoreRange | null;
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
