"""
Maps OULAD and UCI Student Performance's real, processed columns onto
MindRise's 24-feature schema (App\\Services\\Ml\\FeatureExtractionService::
FEATURE_ORDER). Every mapping decision is documented inline; the full table
is repeated in docs/ML_RESEARCH_METHODOLOGY.md Sec 3.

Two categories of feature, handled differently:

1. Real-measured (avg_test_score, improvement_trend, consistency_score,
   weekly/daily_practice_count, practice_streak, attendance_percent,
   question_completion_rate, wrong_answer_percent): derived directly from a
   real measurement in the source dataset. Where no direct analogue exists
   (motivation_score, study_hours in OULAD), a clearly-documented proxy or
   neutral default is used instead of a fabricated value.

2. Platform-only (theta, placement_iq, current_iq, the 5 per-category
   scores, avg_game_score, avg_response_time_sec, avg_difficulty_solved,
   ai_coach_usage_count): no public dataset measures IRT ability, MindRise's
   specific cognitive categories, or its mini-games. These are generated
   from a *pseudo-theta derived from the student's real assessment
   performance* via structural_model.py - the same equations the pure
   synthetic generator trusts - rather than fabricated independently of the
   real data. See docs/ML_RESEARCH_METHODOLOGY.md Sec 3.3 for the full
   justification and its limitations.

Every row keeps a `label` taken DIRECTLY from the real outcome (OULAD
final_result / UCI final grade band) - real rows are never passed through
generate_dataset.py's synthetic composite-score heuristic, since they
already have a genuine ground-truth outcome.
"""
from pathlib import Path

import numpy as np
import pandas as pd

from data_pipeline import advanced_features as af
from data_pipeline import structural_model as sm
from data_pipeline import time_features as tf

PROCESSED = Path(__file__).resolve().parent.parent / "data" / "processed"

LABEL_ORDER = ["high_risk", "needs_improvement", "almost_ready", "ready"]

FEATURE_ORDER = [
    "placement_iq", "current_iq", "theta", "avg_test_score",
    "memory_score", "logical_score", "numerical_score", "attention_score", "spatial_score",
    "avg_game_score", "daily_practice_count", "weekly_practice_count", "practice_streak",
    "study_hours", "avg_response_time_sec", "wrong_answer_percent", "avg_difficulty_solved",
    "improvement_trend", "consistency_score", "attendance_percent", "days_until_exam",
    "motivation_score", "ai_coach_usage_count", "question_completion_rate",
]

FULL_FEATURE_ORDER = FEATURE_ORDER + af.ADVANCED_FEATURE_ORDER

# Superset used only by the new time-aware ablation variants (Model C/D in
# ablation_study.py) - kept separate from FULL_FEATURE_ORDER so the
# currently-deployed model's serving contract (app.py, which defines its own
# FULL_FEATURE_ORDER independently of this module) is completely unaffected
# by this addition.
FULL_FEATURE_ORDER_TIME_AWARE = FULL_FEATURE_ORDER + tf.TIME_AWARE_FEATURE_ORDER

# Both real sources are end-of-course retrospective records (the assessments/
# grades already happened) rather than a live "N days before the exam"
# snapshot - treated as a final-revision-phase snapshot, a documented
# simplification rather than a measured value.
_RETROSPECTIVE_DAYS_UNTIL_EXAM = 7

# Neither source records a self-reported motivation rating - imputed at the
# scale midpoint (matching generate_dataset.py's neutral motivation_latent=0
# case) rather than fabricated per-row.
_NEUTRAL_MOTIVATION = 5.0

RNG_SEED_FOR_PLATFORM_ONLY_FIELDS = 20260710


def _pseudo_theta_and_latents(avg_test_score: pd.Series, consistency_score: pd.Series) -> tuple[np.ndarray, np.ndarray, np.ndarray]:
    """
    Derives (theta, consistency_latent, motivation_latent) from real,
    measured outcome data so the platform-only fields below are generated
    *conditioned on real performance*, not independently of it.

    theta is inverted from generate_dataset.py's own avg_test_score equation
    (avg_test_score ~= 50 + 15*theta + noise => theta ~= (score-50)/15),
    clipped to the same [-3, 3] range the synthetic generator uses.
    consistency_latent is likewise inverted from consistency_score's
    equation (score ~= 60 + 15*latent => latent ~= (score-60)/15).
    motivation_latent has no real analogue in either source dataset, so it
    is fixed at 0 (neutral) - see _NEUTRAL_MOTIVATION above for the same
    reasoning applied to the directly-exposed motivation_score feature.
    """
    theta = np.clip((avg_test_score.to_numpy() - 50) / 15, -3, 3)
    consistency_latent = np.clip((consistency_score.to_numpy() - 60) / 15, -2.5, 2.5)
    motivation_latent = np.zeros(len(avg_test_score))
    return theta, consistency_latent, motivation_latent


def _fill_platform_only_fields(df: pd.DataFrame) -> pd.DataFrame:
    rng = np.random.default_rng(RNG_SEED_FOR_PLATFORM_ONLY_FIELDS)
    theta, consistency_latent, motivation_latent = _pseudo_theta_and_latents(
        df["avg_test_score"], df["consistency_score"]
    )

    df["theta"] = theta.round(3)
    df["current_iq"] = sm.iq_from_theta(theta, rng).round(1)
    df["placement_iq"] = df["current_iq"]  # no real placement-time snapshot exists for these rows
    df["memory_score"] = sm.category_score(theta, rng).round(2)
    df["logical_score"] = sm.category_score(theta, rng).round(2)
    df["numerical_score"] = sm.category_score(theta, rng).round(2)
    df["attention_score"] = sm.category_score(theta, rng).round(2)
    df["spatial_score"] = sm.category_score(theta, rng).round(2)
    df["avg_game_score"] = sm.game_score(theta, consistency_latent, rng).round(2)
    df["avg_response_time_sec"] = sm.response_time_sec(theta, rng).round(2)
    df["avg_difficulty_solved"] = sm.difficulty_solved(theta, rng).round(3)
    df["ai_coach_usage_count"] = sm.ai_coach_usage(motivation_latent, rng)

    # The 7 advanced features with no possible real-data analogue (need
    # item-level correctness sequences, per-category IRT ability, or
    # per-question response times that neither OULAD nor UCI record) - see
    # advanced_features.py's module docstring for the full "platform-only"
    # list and rationale. Generated from the same real-derived pseudo-theta
    # as everything else in this function, via the identical structural
    # model the pure-synthetic rows use.
    platform_only_advanced = af.synthesize(theta, motivation_latent, consistency_latent, rng)
    for feature in ["fatigue_score", "retention_score", "error_recovery_rate",
                     "category_mastery", "confidence_trend", "reaction_speed_trend",
                     "adaptive_learning_gain"]:
        df[feature] = platform_only_advanced[feature]

    # The 9 time-aware features (see time_features.py's module docstring):
    # no public dataset records per-item response times, so these are
    # synthesized from the same real-derived pseudo-theta/consistency_latent
    # as the platform-only advanced features above - documented as synthetic
    # even for otherwise-real rows, not measured.
    time_aware = tf.synthesize(theta, motivation_latent, consistency_latent, rng)
    for feature in tf.TIME_AWARE_FEATURE_ORDER:
        df[feature] = time_aware[feature]

    return df


def map_oulad() -> pd.DataFrame:
    raw = pd.read_csv(PROCESSED / "oulad_features.csv")

    out = pd.DataFrame(index=raw.index)
    out["avg_test_score"] = raw["avg_assessment_score"].round(2)
    out["wrong_answer_percent"] = (100 - out["avg_test_score"]).round(2)
    # Real trend is already on the 0-100 assessment-score scale (second-half
    # minus first-half average); clipped to the synthetic generator's range
    # for comparability, not rescaled - both are seen as a raw feature value
    # by the (scale-invariant) tree-based models this trains.
    out["improvement_trend"] = raw["assessment_score_trend"].clip(-12, 15).round(2)
    # std of 5-25 typical for OULAD's 0-100 assessment scores; *2 maps that
    # onto a 50-100 consistency band comparable to the synthetic generator's
    # typical 60-90 output range (documented scaling choice, not a measurement).
    out["consistency_score"] = (100 - raw["assessment_score_std"] * 2).clip(0, 100).round(2)
    out["weekly_practice_count"] = raw["vle_weekly_active_rate"].round(1)
    out["daily_practice_count"] = (raw["vle_weekly_active_rate"] / 7).round(1)
    out["practice_streak"] = raw["vle_longest_streak"]
    out["attendance_percent"] = (raw["vle_active_days"] / raw["module_weeks"] / 7 * 100).clip(0, 100).round(2)
    out["question_completion_rate"] = raw["question_completion_rate"]
    # OULAD records no self-reported study hours - vle_total_clicks (log-
    # scaled, then mapped onto MindRise's 0-8h range by percentile) is used
    # as a documented proxy for engagement intensity, not a measurement.
    log_clicks = np.log1p(raw["vle_total_clicks"])
    out["study_hours"] = (log_clicks / (log_clicks.quantile(0.95) + 1e-9) * 6).clip(0, 8).round(2)
    out["motivation_score"] = _NEUTRAL_MOTIVATION
    out["days_until_exam"] = _RETROSPECTIVE_DAYS_UNTIL_EXAM

    # Real-derivable advanced features - computed in process_oulad.py
    # directly from OULAD's dated assessment/VLE records (see that file for
    # the exact formulas); copied through unchanged here.
    for feature in ["rolling_avg_score", "weekly_trend", "monthly_trend", "learning_velocity",
                     "knowledge_gain_rate", "engagement_score", "practice_intensity",
                     "difficulty_progression", "question_diversity_score",
                     "time_management_score", "revision_frequency"]:
        out[feature] = raw[feature]
    # consistency_index is CV-based (scale-invariant) vs consistency_score's
    # raw-std-based measure - already computed identically in process_oulad.py.
    out["consistency_index"] = raw["consistency_index"]

    out = _fill_platform_only_fields(out)

    out["label"] = raw["label"]
    out["data_source"] = "real_oulad"
    # Kept for the bias/fairness analysis only - not part of FEATURE_ORDER,
    # dropped before any model ever sees this dataframe.
    out["_demographic_gender"] = raw["gender"]
    out["_demographic_disability"] = raw["disability"]
    out["_demographic_age_band"] = raw["age_band"]
    out["_demographic_imd_midpoint"] = raw["imd_midpoint"]

    return out[FULL_FEATURE_ORDER_TIME_AWARE + ["label", "data_source"] + [c for c in out.columns if c.startswith("_demographic")]]


def map_uci() -> pd.DataFrame:
    raw = pd.read_csv(PROCESSED / "uci_features.csv")

    grades = raw[["G1", "G2", "G3"]]
    grade_std = grades.std(axis=1, ddof=0) * 5  # 0-20 scale -> 0-100

    out = pd.DataFrame(index=raw.index)
    out["avg_test_score"] = (raw["avg_grade"] * 5).round(2)
    out["wrong_answer_percent"] = (100 - out["avg_test_score"]).round(2)
    out["improvement_trend"] = (raw["grade_trend"] * 5).clip(-12, 15).round(2)
    out["consistency_score"] = (100 - grade_std * 2).clip(0, 100).round(2)

    # "studytime" is a weekly-hours category (1: <2h, 2: 2-5h, 3: 5-10h, 4: >10h),
    # not a session count - mapped to an approximate weekly practice-session
    # equivalent using the category midpoints, a documented approximation.
    studytime_to_weekly_sessions = {1: 2, 2: 5, 3: 9, 4: 14}
    out["weekly_practice_count"] = raw["studytime"].map(studytime_to_weekly_sessions)
    out["daily_practice_count"] = (out["weekly_practice_count"] / 7).round(1)
    # No real streak signal exists in UCI (only aggregate absence count, not
    # dated attendance records) - approximated inversely from absences,
    # documented as a proxy rather than measured.
    out["practice_streak"] = (7 - raw["absences"].clip(0, 70) / 10).clip(0, 7).round(0)

    studytime_to_daily_hours = {1: 1 / 7, 2: 3.5 / 7, 3: 7.5 / 7, 4: 12 / 7}
    out["study_hours"] = raw["studytime"].map(studytime_to_daily_hours).round(2)
    # absences is a termly count (0-93 in this dataset); normalized against
    # a documented reference ceiling of 30 (roughly a school term) rather
    # than the dataset's own max, so a single extreme outlier doesn't
    # compress everyone else's attendance_percent toward 100.
    out["attendance_percent"] = (100 * (1 - raw["absences"].clip(0, 30) / 30)).round(2)
    # UCI has no partial-submission records (grades are all-or-nothing per
    # term) - assumed fully completed, documented as an assumption.
    out["question_completion_rate"] = 100.0
    out["motivation_score"] = _NEUTRAL_MOTIVATION
    out["days_until_exam"] = _RETROSPECTIVE_DAYS_UNTIL_EXAM

    # UCI only has 3 static grades per student (no dated event log), so the
    # real-derivable advanced features reduce to simple 3-point arithmetic
    # rather than a true rolling/OLS-trend computation - still genuinely
    # derived from real grades, just with far less signal than OULAD's
    # day-level data affords (documented limitation).
    out["rolling_avg_score"] = out["avg_test_score"]  # only 3 points exist; the mean IS the "rolling" average
    out["weekly_trend"] = (raw["grade_trend"] * 5 / 4).clip(-6, 6).round(3)  # G1->G2 approx spans one grading period (~4 weeks)
    out["monthly_trend"] = (raw["grade_trend"] * 5).clip(-10, 10).round(3)
    out["knowledge_gain_rate"] = (raw["grade_trend"] * 5 / 2).round(3)  # per grading transition, /2 transitions available
    out["consistency_index"] = out["consistency_score"]  # same CV-based measure, already computed above

    # No real signal at all for these five in UCI (no clickstream, no
    # per-item difficulty, no subcategory taxonomy) - filled from the same
    # structural model as the platform-only fields below, using the
    # pseudo-theta/consistency_latent this function derives from real
    # grades, documented as synthetic rather than left unmarked.
    rng = np.random.default_rng(RNG_SEED_FOR_PLATFORM_ONLY_FIELDS + 1)
    theta, consistency_latent, motivation_latent = _pseudo_theta_and_latents(out["avg_test_score"], out["consistency_score"])
    proxy_advanced = af.synthesize(theta, motivation_latent, consistency_latent, rng)
    for feature in ["engagement_score", "practice_intensity", "difficulty_progression",
                     "question_diversity_score", "time_management_score", "revision_frequency",
                     "learning_velocity"]:
        out[feature] = proxy_advanced[feature]

    out = _fill_platform_only_fields(out)

    out["label"] = raw["label"]
    out["data_source"] = "real_uci"
    out["_demographic_gender"] = raw["sex"]
    out["_demographic_age_band"] = pd.cut(raw["age"], bins=[0, 16, 18, 30], labels=["<=16", "17-18", "19+"])

    return out[FULL_FEATURE_ORDER_TIME_AWARE + ["label", "data_source"] + [c for c in out.columns if c.startswith("_demographic")]]
