"""
Synthetic student-behaviour dataset generator for the exam-readiness model.

Why synthetic: MindRise's real usage volume is far too small (a handful of
students) to train or validate a supervised model, and there is no existing
public dataset that matches this platform's exact feature set (IRT theta,
per-category cognitive scores, adaptive-practice behaviour, self-reported
study habits). This script generates realistic, internally-consistent
feature values from documented distributions, then derives a readiness
label from a documented composite heuristic (see `_composite_score` below)
rather than fabricating labels arbitrarily.

This mirrors the intellectual honesty of the IRT engine's Monte Carlo
validation (docs/IRT_ADAPTIVE_TESTING_EXPLAINED.md, Sec. 6): the model is
validated on how well it recovers a *known, documented* ground-truth
relationship from features alone. Once enough real students have both
platform activity and a known real exam outcome, `label` in this dataset
should be replaced by that real outcome and the model retrained on real
data - the training pipeline (train_model.py) itself does not change.

Usage:
    python generate_dataset.py --rows 80000 --seed 42
"""
import argparse

import numpy as np
import pandas as pd

FEATURE_ORDER = [
    "placement_iq",
    "current_iq",
    "theta",
    "avg_test_score",
    "memory_score",
    "logical_score",
    "numerical_score",
    "attention_score",
    "spatial_score",
    "avg_game_score",
    "daily_practice_count",
    "weekly_practice_count",
    "practice_streak",
    "study_hours",
    "avg_response_time_sec",
    "wrong_answer_percent",
    "avg_difficulty_solved",
    "improvement_trend",
    "consistency_score",
    "attendance_percent",
    "days_until_exam",
    "motivation_score",
    "ai_coach_usage_count",
    "question_completion_rate",
]

LABELS = ["high_risk", "needs_improvement", "almost_ready", "ready"]


def _clip(arr, lo, hi):
    return np.clip(arr, lo, hi)


def generate(n: int, seed: int) -> pd.DataFrame:
    rng = np.random.default_rng(seed)

    # Latent ability (theta) is the root cause of most downstream features,
    # matching the platform's own IRT model where everything derives from theta.
    theta = _clip(rng.normal(0, 1, n), -3, 3)

    # Motivation and consistency are semi-independent latent traits that drive
    # behavioural (not ability) features.
    motivation_latent = _clip(rng.normal(0, 1, n), -2.5, 2.5)
    consistency_latent = _clip(rng.normal(0, 1, n), -2.5, 2.5)

    current_iq = _clip(100 + 15 * theta + rng.normal(0, 3, n), 40, 160)
    # Placement happened earlier in the student's journey; current_iq reflects
    # any improvement since then (improvement_trend below is derived from the gap).
    improvement_raw = _clip(rng.normal(1.5, 4, n) + consistency_latent * 2, -15, 20)
    placement_iq = _clip(current_iq - improvement_raw, 40, 160)

    def category(bias_scale=15):
        bias = rng.normal(0, 1, n)
        return _clip(50 + 15 * theta + bias_scale * bias * 0.4 + rng.normal(0, 6, n), 0, 100)

    memory_score = category()
    logical_score = category()
    numerical_score = category()
    attention_score = category()
    spatial_score = category()

    avg_test_score = _clip(
        (memory_score + logical_score + numerical_score + attention_score + spatial_score) / 5
        + rng.normal(0, 3, n),
        0,
        100,
    )
    wrong_answer_percent = _clip(100 - avg_test_score + rng.normal(0, 2, n), 0, 100)

    avg_game_score = _clip(
        40 + 10 * theta + 8 * consistency_latent + rng.normal(0, 12, n), 0, 100
    )

    motivation_score = np.rint(_clip(5.5 + 1.8 * motivation_latent + rng.normal(0, 0.8, n), 1, 10))
    study_hours = _clip(1.2 + 0.8 * motivation_latent + rng.gamma(2, 0.6, n), 0, 8)
    attendance_percent = _clip(75 + 10 * motivation_latent + rng.normal(0, 8, n), 0, 100)

    practice_intensity = _clip(0.6 * motivation_latent + 0.4 * consistency_latent, -3, 3)
    daily_practice_count = rng.poisson(_clip(2 + practice_intensity, 0.1, None))
    weekly_practice_count = daily_practice_count + rng.poisson(_clip(4 + practice_intensity * 1.5, 0.1, None))
    practice_streak = rng.poisson(_clip(3 + practice_intensity * 2, 0.1, None))
    ai_coach_usage_count = rng.poisson(_clip(1.5 + 0.5 * motivation_latent, 0.05, None))

    avg_response_time_sec = _clip(18 - 3 * theta + rng.normal(0, 4, n), 4, 60)
    avg_difficulty_solved = _clip(theta * 0.8 + rng.normal(0, 0.5, n), -3, 3)
    improvement_trend = _clip(improvement_raw / 3 + rng.normal(0, 1.5, n), -12, 15)
    consistency_score = _clip(60 + 15 * consistency_latent + rng.normal(0, 6, n), 0, 100)
    question_completion_rate = _clip(85 + 8 * motivation_latent + rng.normal(0, 6, n), 30, 100)

    days_until_exam = rng.integers(0, 181, n)

    df = pd.DataFrame(
        {
            "placement_iq": placement_iq.round(1),
            "current_iq": current_iq.round(1),
            "theta": theta.round(3),
            "avg_test_score": avg_test_score.round(2),
            "memory_score": memory_score.round(2),
            "logical_score": logical_score.round(2),
            "numerical_score": numerical_score.round(2),
            "attention_score": attention_score.round(2),
            "spatial_score": spatial_score.round(2),
            "avg_game_score": avg_game_score.round(2),
            "daily_practice_count": daily_practice_count,
            "weekly_practice_count": weekly_practice_count,
            "practice_streak": practice_streak,
            "study_hours": study_hours.round(2),
            "avg_response_time_sec": avg_response_time_sec.round(2),
            "wrong_answer_percent": wrong_answer_percent.round(2),
            "avg_difficulty_solved": avg_difficulty_solved.round(3),
            "improvement_trend": improvement_trend.round(2),
            "consistency_score": consistency_score.round(2),
            "attendance_percent": attendance_percent.round(2),
            "days_until_exam": days_until_exam,
            "motivation_score": motivation_score.astype(int),
            "ai_coach_usage_count": ai_coach_usage_count,
            "question_completion_rate": question_completion_rate.round(2),
        }
    )

    df["readiness_percent"], df["label"] = _composite_score(df, rng)
    df.insert(0, "student_id", np.arange(1, n + 1))

    return df[["student_id", *FEATURE_ORDER, "readiness_percent", "label"]]


def _composite_score(df: pd.DataFrame, rng: np.random.Generator):
    """
    Documented ground-truth heuristic (NOT a trained model): a weighted,
    z-scored combination of features that a domain expert would consider
    "exam readiness", plus an urgency interaction (low practice + little time
    left = extra risk) and Gaussian noise so the label isn't perfectly
    deterministic from the features (real outcomes never are). The ML models
    trained in train_model.py never see this function - they only see the
    resulting label/percent and the raw features, exactly like a real
    supervised-learning setup.
    """

    def z(col, invert=False):
        s = df[col]
        z_score = (s - s.mean()) / (s.std() + 1e-9)
        return -z_score if invert else z_score

    weights = {
        "theta": 0.14,
        "avg_test_score": 0.14,
        "memory_score": 0.02,
        "logical_score": 0.02,
        "numerical_score": 0.02,
        "attention_score": 0.02,
        "spatial_score": 0.02,
        "practice_streak": 0.08,
        "weekly_practice_count": 0.07,
        "study_hours": 0.06,
        "consistency_score": 0.08,
        "attendance_percent": 0.05,
        "motivation_score": 0.05,
        "improvement_trend": 0.06,
        "question_completion_rate": 0.05,
        "ai_coach_usage_count": 0.03,
    }
    inverted = {"wrong_answer_percent": 0.05, "avg_response_time_sec": 0.03}

    score = np.zeros(len(df))
    for col, w in weights.items():
        score += w * z(col)
    for col, w in inverted.items():
        score += w * z(col, invert=True)

    # Urgency interaction: an exam within 2 weeks amplifies the penalty for
    # low practice/consistency rather than just adding a flat term - this is
    # the interaction structure a tree-based model should be able to recover.
    urgent = df["days_until_exam"] < 14
    under_prepared = z("practice_streak") + z("consistency_score") < 0
    score = score - np.where(urgent & under_prepared, 0.6, 0.0)

    score += rng.normal(0, 0.35, len(df))

    # Many of the z-scored inputs above are themselves correlated (most derive
    # from the same latent theta/motivation/consistency traits), so the raw
    # weighted sum's actual spread is much narrower than "sum of independent
    # unit-variance terms" would suggest. Re-standardizing the composite
    # itself (rather than assuming a fixed multiplier) is what makes the
    # resulting label distribution span all four classes instead of
    # collapsing onto one - the multiplier is a deliberate design choice
    # (mean 55, SD 20) for a readable spread, not a further data assumption.
    score_z = (score - score.mean()) / (score.std() + 1e-9)
    percent = 55 + score_z * 20
    percent = np.clip(percent, 1, 99)

    label = pd.cut(
        percent,
        bins=[0, 40, 60, 80, 100],
        labels=["high_risk", "needs_improvement", "almost_ready", "ready"],
    )

    return percent.round(1), label.astype(str)


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--rows", type=int, default=80000)
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--out", type=str, default="data/synthetic_student_dataset.csv")
    args = parser.parse_args()

    dataset = generate(args.rows, args.seed)
    dataset.to_csv(args.out, index=False)

    print(f"Wrote {len(dataset):,} rows to {args.out}")
    print(dataset["label"].value_counts(normalize=True).round(3))
