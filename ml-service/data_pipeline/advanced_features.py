"""
The 18 advanced behavioural/learning features requested for the research
upgrade, each with an exact mathematical definition below. Every feature
falls into one of two groups (see docs/ML_RESEARCH_METHODOLOGY.md Sec 4 for
the full table with worked examples):

REAL-DERIVABLE from OULAD's day-level VLE clickstream and dated assessment
records (rolling_avg_score, weekly_trend, monthly_trend, learning_velocity,
knowledge_gain_rate, consistency_index, engagement_score, practice_intensity,
difficulty_progression*, question_diversity_score, time_management_score,
revision_frequency) - computed for real in process_oulad.py from the
genuine per-date VLE/assessment tables (*difficulty_progression is a proxy
via score trend, since OULAD has no item-difficulty concept).

PLATFORM-ONLY (fatigue_score, retention_score, error_recovery_rate,
category_mastery, confidence_trend, reaction_speed_trend,
adaptive_learning_gain) require MindRise-specific constructs no public
dataset records at item level (per-question correctness sequences,
per-category IRT ability, per-question response time, repeat-exposure
tracking). These are generated for training data from the same latent
(theta, motivation, consistency) structural model as the original 24
features, and computed genuinely from real event data by Laravel's
FeatureExtractionService at live inference time for actual MindRise users -
exactly the same "platform-only" pattern already used for theta,
memory_score, etc.

All functions here are the canonical mathematical specification; the
Laravel mirror (FeatureExtractionService::extractAdvanced()) implements the
identical formulas against MindRise's own session_answers/game_scores/
checkins tables.
"""
import numpy as np
import pandas as pd

ADVANCED_FEATURE_ORDER = [
    "rolling_avg_score",
    "weekly_trend",
    "monthly_trend",
    "learning_velocity",
    "knowledge_gain_rate",
    "consistency_index",
    "fatigue_score",
    "retention_score",
    "engagement_score",
    "practice_intensity",
    "error_recovery_rate",
    "category_mastery",
    "confidence_trend",
    "reaction_speed_trend",
    "adaptive_learning_gain",
    "difficulty_progression",
    "question_diversity_score",
    "time_management_score",
    "revision_frequency",
]


# ---------------------------------------------------------------------------
# Mathematical definitions (also implemented against real per-date arrays in
# process_oulad.py, and against MindRise's own tables in FeatureExtractionService.php)
# ---------------------------------------------------------------------------
#
# 1. rolling_avg_score   RA_w = (1/w) * sum_{i=t-w+1}^{t} score_i
#    Mean of the last w=5 assessment/session scores. Smooths single-session
#    noise out of the raw avg_test_score feature.
#
# 2. weekly_trend        beta_1 from OLS: score_w = beta_0 + beta_1 * week + eps
#    Slope of assessment score regressed on calendar week index, fit over
#    the last 8 weeks of activity. Units: score points per week.
#
# 3. monthly_trend       Same OLS, grouped by calendar month over the last
#    6 months. Captures slower-moving trend the noisier weekly_trend misses.
#
# 4. learning_velocity   LV = (theta_now - theta_(t-k)) / k
#    Rate of IRT ability (theta) change per week over a k=4-week window -
#    "how fast is this student's measured ability actually increasing."
#
# 5. knowledge_gain_rate KGR = (score_now - score_baseline) / n_sessions
#    Score improvement per completed practice session (per unit *practice
#    volume*, distinct from learning_velocity's per unit *time*).
#
# 6. consistency_index   CI = 100 * (1 - CV),  CV = sigma_scores / mu_scores
#    Coefficient-of-variation-based consistency (scale-invariant, unlike the
#    original consistency_score's raw standard deviation).
#
# 7. fatigue_score       FS = accuracy_first_half_of_session - accuracy_second_half_of_session
#    Averaged across recent sessions. Positive = accuracy declines within a
#    session (a real fatigue signature); ~0 = no within-session decay.
#
# 8. retention_score     RS = accuracy on questions re-encountered >= 14 days
#    after their first attempt. Measures whether learning "stuck," not just
#    whether the student is currently practising.
#
# 9. engagement_score    ES = 100 * mean(z(session_frequency), z(active_days),
#    z(avg_session_duration)) rescaled to [0,100] via a logistic squashing -
#    a blended engagement composite distinct from raw practice counts.
#
# 10. practice_intensity  PI = 100 * weekly_practice_count / recommended_weekly_practice_count
#     Ratio to the platform's OWN StudyPlanService target - ties directly
#     into existing architecture rather than an arbitrary reference.
#
# 11. error_recovery_rate ERR = P(correct_(i+1) | incorrect_i)
#     Conditional probability of getting the immediately-following question
#     right after a wrong one - a standard learning-analytics "bounce-back" metric.
#
# 12. category_mastery    mastery_c = 100 * P(correct | theta_c, avg item difficulty in c)
#     via the Rasch model P = 1/(1+e^-(theta_c - b)); reuses the existing IRT
#     engine rather than introducing a second ability metric.
#
# 13. confidence_trend    confidence_t = accuracy_t / normalized_response_time_t;
#     confidence_trend = OLS slope of confidence_t over session index.
#
# 14. reaction_speed_trend OLS slope of avg_response_time_sec over session
#     index (negative = getting faster = increasing fluency).
#
# 15. adaptive_learning_gain ALG = (theta_after_daily_sessions - theta_at_placement)
#     / n_daily_sessions_completed - ability gain attributable to daily
#     (IRT-integrated) sessions specifically, isolated from practice sessions.
#
# 16. difficulty_progression OLS slope of avg_difficulty_solved over session
#     index - is the student being routed to (and solving) progressively
#     harder items over time.
#
# 17. question_diversity_score QDS = 100 * distinct_subcategories_attempted
#     / total_subcategories_in_category - breadth of practice coverage
#     against Phase 6's subcategory taxonomy.
#
# 18. time_management_score TMS = 100 * (1 - mean(|actual_session_minutes -
#     target_session_minutes|) / target_session_minutes) - how closely
#     actual session length tracks StudyPlanService's recommended duration.
#
# 19. revision_frequency RF = count(question re-attempts) / count(distinct
#     questions attempted) - how often the student revisits previously-seen
#     material (a "revision" event in QuestionSamplingService's repeat-fallback path).


def synthesize(theta: np.ndarray, motivation_latent: np.ndarray, consistency_latent: np.ndarray,
               rng: np.random.Generator) -> dict:
    """
    Generates plausible values for all 18 advanced features for the
    synthetic-calibrated portion of the hybrid dataset, driven by the same
    three latent traits (theta/motivation/consistency) as every other
    platform-only field - see structural_model.py's module docstring for
    why a single shared generative process matters here.
    """
    n = len(theta)

    def clip(x, lo, hi):
        return np.clip(x, lo, hi)

    base_score = clip(50 + 15 * theta + rng.normal(0, 5, n), 0, 100)

    return {
        "rolling_avg_score": (base_score + rng.normal(0, 2, n)).round(2),
        "weekly_trend": clip(0.6 * consistency_latent + rng.normal(0, 1.2, n), -6, 6).round(3),
        "monthly_trend": clip(1.2 * consistency_latent + rng.normal(0, 2.0, n), -10, 10).round(3),
        "learning_velocity": clip(0.05 * theta + 0.08 * consistency_latent + rng.normal(0, 0.05, n), -0.3, 0.3).round(4),
        "knowledge_gain_rate": clip(0.4 * consistency_latent + rng.normal(0, 0.6, n), -3, 3).round(3),
        "consistency_index": clip(65 + 18 * consistency_latent + rng.normal(0, 5, n), 0, 100).round(2),
        "fatigue_score": clip(8 - 4 * motivation_latent + rng.normal(0, 4, n), -10, 30).round(2),
        "retention_score": clip(55 + 14 * theta + 6 * consistency_latent + rng.normal(0, 8, n), 0, 100).round(2),
        "engagement_score": clip(55 + 12 * motivation_latent + 8 * consistency_latent + rng.normal(0, 7, n), 0, 100).round(2),
        "practice_intensity": clip(80 + 20 * motivation_latent + rng.normal(0, 12, n), 0, 200).round(2),
        "error_recovery_rate": clip(55 + 10 * theta + rng.normal(0, 8, n), 0, 100).round(2),
        "category_mastery": clip(50 + 15 * theta + rng.normal(0, 6, n), 0, 100).round(2),
        "confidence_trend": clip(0.3 * theta + 0.3 * consistency_latent + rng.normal(0, 0.6, n), -3, 3).round(3),
        "reaction_speed_trend": clip(-0.4 * theta + rng.normal(0, 0.5, n), -3, 3).round(3),
        "adaptive_learning_gain": clip(0.04 * theta + 0.05 * consistency_latent + rng.normal(0, 0.04, n), -0.25, 0.25).round(4),
        "difficulty_progression": clip(0.15 * theta + rng.normal(0, 0.2, n), -1, 1).round(3),
        "question_diversity_score": clip(50 + 15 * motivation_latent + rng.normal(0, 10, n), 0, 100).round(2),
        "time_management_score": clip(70 + 15 * consistency_latent + rng.normal(0, 10, n), 0, 100).round(2),
        "revision_frequency": clip(20 - 6 * consistency_latent + rng.normal(0, 8, n), 0, 100).round(2),
    }
