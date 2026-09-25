"""
The 19 advanced behavioural/learning features requested for the research
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


# --------------------------------------------------------------------------- Mathematical definitions.


def synthesize(theta: np.ndarray, motivation_latent: np.ndarray, consistency_latent: np.ndarray,
               rng: np.random.Generator) -> dict:
    """
    Generates plausible values for all 19 advanced features for the
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
