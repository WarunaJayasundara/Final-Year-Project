"""
The 9 objective time-aware features added by the response-time upgrade -
mirrors advanced_features.py's structure exactly (TIME_AWARE_FEATURE_ORDER
here matches FeatureExtractionService::TIME_AWARE_FEATURE_ORDER on the PHP
side, name-for-name and order-for-order).

Every one of these is genuinely measurable from session_answers.response_time_ms
once real MindRise usage accumulates (see FeatureExtractionService::extractTimeAware()
for the live-inference formulas against real tables); for training data today
there is no public dataset with per-item response times (OULAD/UCI record
neither), so all 9 are synthesized here from the same three latent traits
(theta, motivation, consistency) the rest of the platform-only features
already use - not fabricated independently, but not "real" either. This
honest limitation is documented in ML_RESEARCH_METHODOLOGY.md alongside the
existing platform-only-feature caveat for fatigue_score/retention_score/etc.

All functions pure and vectorized (numpy arrays in, numpy arrays out).
"""
import numpy as np

TIME_AWARE_FEATURE_ORDER = [
    "median_response_time_sec",
    "response_time_std",
    "speed_accuracy_score",
    "guess_rate",
    "time_efficiency_score",
    "questions_per_minute",
    "exam_pace_gap",
    "response_time_improvement",
    "active_practice_minutes",
]


def synthesize(theta: np.ndarray, motivation_latent: np.ndarray, consistency_latent: np.ndarray,
               rng: np.random.Generator) -> dict:
    n = len(theta)

    def clip(x, lo, hi):
        return np.clip(x, lo, hi)

    # Median is typically slightly below the mean for a right-skewed response-time
    # distribution - same theta relationship as structural_model.response_time_sec,
    # scaled down ~8% rather than duplicating an independent equation.
    median_response_time_sec = clip(16.5 - 2.7 * theta + rng.normal(0, 3.5, n), 3, 55)
    response_time_std = clip(6 - 1.6 * consistency_latent + rng.normal(0, 1.5, n), 0.5, 15)
    speed_accuracy_score = clip(50 + 20 * theta + 10 * consistency_latent + rng.normal(0, 8, n), 0, 100)
    guess_rate = clip(0.15 - 0.05 * consistency_latent - 0.03 * motivation_latent + rng.normal(0, 0.05, n), 0, 0.6)
    time_efficiency_score = clip(60 + 15 * theta + 10 * consistency_latent + rng.normal(0, 8, n), 0, 100)
    questions_per_minute = clip(1.0 + 0.3 * theta + 0.1 * motivation_latent + rng.normal(0, 0.25, n), 0.15, 5)
    exam_pace_gap = clip(5 * theta + rng.normal(0, 8, n), -60, 60)
    response_time_improvement = clip(
        -0.3 * consistency_latent - 0.2 * motivation_latent + rng.normal(0, 0.4, n), -3, 3
    )
    active_practice_minutes = clip(200 + 80 * motivation_latent + 40 * consistency_latent + rng.normal(0, 60, n), 0, 1200)

    return {
        "median_response_time_sec": median_response_time_sec.round(2),
        "response_time_std": response_time_std.round(2),
        "speed_accuracy_score": speed_accuracy_score.round(2),
        "guess_rate": guess_rate.round(3),
        "time_efficiency_score": time_efficiency_score.round(2),
        "questions_per_minute": questions_per_minute.round(3),
        "exam_pace_gap": exam_pace_gap.round(2),
        "response_time_improvement": response_time_improvement.round(3),
        "active_practice_minutes": active_practice_minutes.round(1),
    }
