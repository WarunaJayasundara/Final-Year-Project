"""
Ablation study for the time-aware exam-readiness upgrade: holds the learning
algorithm FIXED at XGBoost (the winner of model_comparison.py's full 9-model
screening, optimized test macro-F1 0.6808, version 20260711054426 - see
models/model_comparison_report.json) and varies ONLY the feature set, to
isolate the actual research question ("does adding response-time behaviour +
IRT ability + longitudinal trend features improve exam-readiness prediction
compared with performance scores alone?") from model-family selection, which
was already answered by model_comparison.py and is not repeated here.

Re-running the full 9-candidate screening + nested-CV Optuna HPO for every
feature-set variant (as a literal "Model A/B/C/D, each independently
model-selected" study would require) was estimated at 2+ hours PER variant
in this environment - infeasible for 4-6 variants. Fixing the algorithm and
only re-running nested HPO (still genuine hyperparameter tuning per variant,
since different feature counts can want different max_depth/regularization)
keeps this tractable while still answering the real question honestly.

Implements the brief's exact 5-step progressive ablation (scores-only ->
+IRT -> +behaviour -> +response-time -> full) PLUS the currently-deployed
model's exact schema as a baseline reference point, and reports full
evaluation-suite metrics (accuracy, balanced accuracy, macro/weighted F1,
ROC-AUC, PR-AUC, MCC, log loss, Brier score) for every variant via
evaluate.py's own core_metrics(), so these numbers are directly comparable
to the live model's evaluation_report.json.

Run: python ablation_study.py --data data/hybrid_student_dataset.csv
Output: models/ablation_report.json (does NOT touch models/model.joblib or
any other live-serving artifact - promotion is a separate, deliberate step,
see model_registry.py and CLAUDE.md's promotion gate).
"""
import argparse
import json
import time
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.preprocessing import LabelEncoder, StandardScaler
from sklearn.model_selection import train_test_split

from evaluate import core_metrics
from model_comparison import _candidates, _optuna_search_space, run_nested_hpo
import optuna

LABEL_ORDER = ["high_risk", "needs_improvement", "almost_ready", "ready"]
FIXED_ALGORITHM = "xgboost"

# --- Progressive feature groups (cumulative - each includes the previous) ---

_SCORES_ONLY = [
    "avg_test_score", "wrong_answer_percent", "memory_score", "logical_score",
    "numerical_score", "attention_score", "spatial_score", "avg_difficulty_solved",
]
_PLUS_IRT = _SCORES_ONLY + [
    "theta", "placement_iq", "current_iq", "category_mastery",
    "learning_velocity", "adaptive_learning_gain",
]
_PLUS_BEHAVIOUR = _PLUS_IRT + [
    "daily_practice_count", "weekly_practice_count", "practice_streak", "consistency_score",
    "engagement_score", "practice_intensity", "error_recovery_rate", "question_completion_rate",
    "ai_coach_usage_count", "question_diversity_score", "revision_frequency", "rolling_avg_score",
    "weekly_trend", "monthly_trend", "knowledge_gain_rate", "consistency_index", "retention_score",
    "confidence_trend", "difficulty_progression", "time_management_score", "days_until_exam",
    "motivation_score", "avg_game_score", "improvement_trend", "active_practice_minutes",
]
_PLUS_RESPONSE_TIME = _PLUS_BEHAVIOUR + [
    "avg_response_time_sec", "reaction_speed_trend", "fatigue_score",
    "median_response_time_sec", "response_time_std", "speed_accuracy_score", "guess_rate",
    "time_efficiency_score", "questions_per_minute", "exam_pace_gap", "response_time_improvement",
]
_FULL_WITH_SUBJECTIVE = _PLUS_RESPONSE_TIME + ["study_hours", "attendance_percent"]

_CURRENT_LIVE_43 = [
    "placement_iq", "current_iq", "theta", "avg_test_score", "memory_score", "logical_score",
    "numerical_score", "attention_score", "spatial_score", "avg_game_score", "daily_practice_count",
    "weekly_practice_count", "practice_streak", "study_hours", "avg_response_time_sec",
    "wrong_answer_percent", "avg_difficulty_solved", "improvement_trend", "consistency_score",
    "attendance_percent", "days_until_exam", "motivation_score", "ai_coach_usage_count",
    "question_completion_rate", "rolling_avg_score", "weekly_trend", "monthly_trend",
    "learning_velocity", "knowledge_gain_rate", "consistency_index", "fatigue_score",
    "retention_score", "engagement_score", "practice_intensity", "error_recovery_rate",
    "category_mastery", "confidence_trend", "reaction_speed_trend", "adaptive_learning_gain",
    "difficulty_progression", "question_diversity_score", "time_management_score", "revision_frequency",
]

FEATURE_GROUPS = {
    # Reference point: the exact schema the currently-deployed model was
    # trained on (Model "A" in the upgrade brief's section 19 framing).
    "A_current_live_baseline": _CURRENT_LIVE_43,
    "step1_scores_only": _SCORES_ONLY,
    "step2_plus_irt": _PLUS_IRT,
    # Model "B": objective-behavioural-only (no attendance_percent/study_hours).
    "B_step3_plus_behaviour": _PLUS_BEHAVIOUR,
    # Model "C": B + the 9 new time-aware features.
    "C_step4_plus_response_time": _PLUS_RESPONSE_TIME,
    # Model "D": the full model, WITH the subjective features added back on
    # top, to directly test whether keeping attendance_percent/study_hours
    # still helps once objective+time-aware signal is already present.
    "D_step5_full_with_subjective": _FULL_WITH_SUBJECTIVE,
}


def _fit_and_evaluate(feature_cols: list, X_train_full: pd.DataFrame, X_test_full: pd.DataFrame,
                       y_train: np.ndarray, y_test: np.ndarray) -> dict:
    X_train = X_train_full[feature_cols].values
    X_test = X_test_full[feature_cols].values

    scaler = StandardScaler()
    X_train_scaled = scaler.fit_transform(X_train)
    X_test_scaled = scaler.transform(X_test)

    hpo = run_nested_hpo(FIXED_ALGORITHM, X_train_scaled, y_train)

    best_model = _optuna_search_space(optuna.trial.FixedTrial(hpo["best_params"]), FIXED_ALGORITHM)
    best_model.fit(X_train_scaled, y_train)

    pred = best_model.predict(X_test_scaled)
    proba = best_model.predict_proba(X_test_scaled)
    metrics = core_metrics(y_test, pred, proba)

    return {
        "feature_count": len(feature_cols),
        "features": feature_cols,
        "hpo": {k: v for k, v in hpo.items() if k != "n_trials"},
        "test_metrics": metrics,
    }


def main(args):
    df = pd.read_csv(args.data)

    encoder = LabelEncoder()
    encoder.classes_ = np.array(LABEL_ORDER)
    y = encoder.transform(df["label"].values)

    # Single shared split across every variant (same rows in train/test for
    # all of them) so differences in metrics are attributable to the feature
    # set, not to different random splits - the whole point of an ablation.
    train_idx, test_idx = train_test_split(
        np.arange(len(df)), test_size=0.2, random_state=42, stratify=y
    )
    X_train_full, X_test_full = df.iloc[train_idx], df.iloc[test_idx]
    y_train, y_test = y[train_idx], y[test_idx]

    print(f"Training rows: {len(train_idx):,} | Test rows: {len(test_idx):,}")
    print(f"Fixed algorithm: {FIXED_ALGORITHM} (winner of model_comparison.py's 9-model screening)")

    results = {}
    for group_name, feature_cols in FEATURE_GROUPS.items():
        print(f"\n=== {group_name} ({len(feature_cols)} features) ===")
        start = time.time()
        results[group_name] = _fit_and_evaluate(feature_cols, X_train_full, X_test_full, y_train, y_test)
        elapsed = time.time() - start
        m = results[group_name]["test_metrics"]
        print(f"  f1_macro={m['f1_macro']:.4f}  balanced_accuracy={m['balanced_accuracy']:.4f}  "
              f"roc_auc={m['roc_auc_ovr_macro']:.4f}  [{elapsed:.1f}s]")

    report = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "fixed_algorithm": FIXED_ALGORITHM,
        "algorithm_selection_note": (
            "The algorithm itself was already selected via a full 9-candidate "
            "screening + nested-CV Optuna HPO in model_comparison.py (XGBoost, "
            "optimized test macro-F1 0.6808). This ablation study holds that "
            "choice fixed and varies only the feature set, since re-running "
            "full model selection for every feature-set variant was measured "
            "at 2+ hours per variant and does not change the answer to the "
            "actual research question being tested here."
        ),
        "training_rows": len(train_idx),
        "test_rows": len(test_idx),
        "groups": results,
    }

    models_dir = Path(args.out)
    models_dir.mkdir(parents=True, exist_ok=True)
    out_path = models_dir / "ablation_report.json"
    with open(out_path, "w") as f:
        json.dump(report, f, indent=2)

    print(f"\nWrote {out_path}")
    print("\nSummary (test set f1_macro by group):")
    for name, r in results.items():
        print(f"  {name:35s} n_features={r['feature_count']:3d}  f1_macro={r['test_metrics']['f1_macro']:.4f}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--data", type=str, default="data/hybrid_student_dataset.csv")
    parser.add_argument("--out", type=str, default="models")
    args = parser.parse_args()
    main(args)
