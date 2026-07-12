"""
Trains the 3 additional prediction targets that have genuine, temporally-
non-leaky ground truth (see data_pipeline/process_oulad_temporal.py for the
first-half/second-half split that makes this honest):

  risk_of_dropping_practice  binary classifier (XGBoost)
  next_assessment_score      regressor (XGBoost) - predicted 0-100 score
  score_change                regressor (XGBoost) - predicted improvement/decline

Two requested outputs are deliberately NOT trained as supervised models
here - see docs/ML_RESEARCH_METHODOLOGY.md Sec 6 for the full reasoning:

  "Recommended Daily Study Hours" has no ground truth in any dataset (no
  record of what the *optimal* study duration would have been) - remains a
  StudyPlanService RULE, now informed by this model's risk_of_dropping_practice
  output as an input signal (see Laravel integration), not an ML prediction.

  "Most Effective Learning Strategy" would require interventional/causal
  data (what happens if the SAME student tries strategy A vs B) that no
  observational dataset - real or MindRise's own - provides. Framed as a
  rule-based suggestion, never as a supervised prediction.

Trained on OULAD only (see process_oulad_temporal.py's docstring for why
UCI is excluded from this specific pipeline) - a genuinely smaller dataset
than the main classifier's, documented as a real limitation, not padded
with synthetic rows that would need to fabricate a "first half of a
module" event structure MindRise doesn't have a public-data analogue for.

Run: python train_multioutput.py
Output: models/risk_model.joblib, models/next_score_model.joblib,
        models/score_change_model.joblib, models/multioutput_scaler.joblib,
        models/multioutput_metadata.json
"""
import json
from datetime import datetime, timezone
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.metrics import (
    f1_score,
    mean_absolute_error,
    r2_score,
    roc_auc_score,
    root_mean_squared_error,
)
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from xgboost import XGBClassifier, XGBRegressor

PROCESSED = Path(__file__).parent / "data" / "processed"
MODELS_DIR = Path(__file__).parent / "models"

# A SUBSET of FULL_FEATURE_ORDER (see data_pipeline/feature_mapping.py) -
# using the platform's own canonical feature names/derivations (computed
# here on first-half-only OULAD data, see process_oulad_temporal.py) means
# the exact same live feature vector Laravel sends for the main readiness
# classifier can feed these multi-output models too, with no second,
# incompatible feature contract at inference time. Using anything from the
# second half here would leak the very information these targets exist to
# predict.
MULTIOUTPUT_FEATURES = [
    "avg_test_score", "weekly_practice_count", "question_completion_rate",
    "engagement_score", "practice_intensity",
]


def _prep():
    df = pd.read_csv(PROCESSED / "oulad_temporal.csv")
    return df, MULTIOUTPUT_FEATURES


def train_risk_classifier(df: pd.DataFrame, features: list, scaler: StandardScaler) -> dict:
    X = scaler.transform(df[features].fillna(df[features].median()).values)
    y = df["risk_of_dropping_practice"].values

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)
    model = XGBClassifier(n_estimators=200, max_depth=4, learning_rate=0.08, random_state=42, eval_metric="logloss")
    model.fit(X_train, y_train)

    proba = model.predict_proba(X_test)[:, 1]
    pred = model.predict(X_test)
    metrics = {
        "f1": float(f1_score(y_test, pred)),
        "roc_auc": float(roc_auc_score(y_test, proba)),
        "positive_rate_train": float(y_train.mean()),
        "positive_rate_test": float(y_test.mean()),
        "n_train": len(y_train),
        "n_test": len(y_test),
    }
    joblib.dump(model, MODELS_DIR / "risk_model.joblib")
    return metrics


def train_regressor(df: pd.DataFrame, features: list, target: str, scaler: StandardScaler, out_name: str) -> dict:
    sub = df.dropna(subset=[target])
    X = scaler.transform(sub[features].fillna(sub[features].median()).values)
    y = sub[target].values

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
    model = XGBRegressor(n_estimators=200, max_depth=4, learning_rate=0.08, random_state=42)
    model.fit(X_train, y_train)

    pred = model.predict(X_test)
    metrics = {
        "mae": float(mean_absolute_error(y_test, pred)),
        "rmse": float(root_mean_squared_error(y_test, pred)),
        "r2": float(r2_score(y_test, pred)),
        "target_mean": float(y.mean()),
        "target_std": float(y.std()),
        "n_train": len(y_train),
        "n_test": len(y_test),
    }
    joblib.dump(model, MODELS_DIR / out_name)
    return metrics


def main():
    df, features = _prep()

    scaler = StandardScaler()
    scaler.fit(df[features].fillna(df[features].median()).values)
    joblib.dump(scaler, MODELS_DIR / "multioutput_scaler.joblib")

    print("Training risk_of_dropping_practice classifier...")
    risk_metrics = train_risk_classifier(df, features, scaler)
    print(f"  f1={risk_metrics['f1']:.4f}, roc_auc={risk_metrics['roc_auc']:.4f}")

    print("Training next_assessment_score regressor...")
    next_score_metrics = train_regressor(df, features, "next_assessment_score", scaler, "next_score_model.joblib")
    print(f"  MAE={next_score_metrics['mae']:.2f}, RMSE={next_score_metrics['rmse']:.2f}, R2={next_score_metrics['r2']:.4f}")

    print("Training score_change regressor...")
    score_change_metrics = train_regressor(df, features, "score_change", scaler, "score_change_model.joblib")
    print(f"  MAE={score_change_metrics['mae']:.2f}, RMSE={score_change_metrics['rmse']:.2f}, R2={score_change_metrics['r2']:.4f}")

    metadata = {
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "features": features,
        "targets": {
            "risk_of_dropping_practice": risk_metrics,
            "next_assessment_score": next_score_metrics,
            "score_change": score_change_metrics,
        },
        "training_data": "OULAD only, temporally split (first half = features, second half = target) - see process_oulad_temporal.py",
        "excluded_outputs": {
            "recommended_daily_study_hours": "No ground truth exists in any dataset for 'optimal' study hours - delivered as a StudyPlanService rule, informed by risk_of_dropping_practice, not an ML prediction.",
            "most_effective_learning_strategy": "Requires interventional/causal data (same student under strategy A vs B) no observational dataset provides - delivered as a rule-based suggestion.",
        },
    }
    with open(MODELS_DIR / "multioutput_metadata.json", "w") as f:
        json.dump(metadata, f, indent=2)

    print(f"\nSaved multi-output models + metadata to {MODELS_DIR}/")


if __name__ == "__main__":
    main()
