"""
Trains and compares candidate models for exam-readiness classification,
selects the best performer, computes SHAP-based global feature importance,
and saves a versioned artifact bundle that app.py loads at inference time.

Usage:
    python train_model.py --data data/synthetic_student_dataset.csv
"""
import argparse
import json
from datetime import datetime, timezone
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
import shap
from sklearn.ensemble import GradientBoostingClassifier, RandomForestClassifier
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
    precision_score,
    recall_score,
    roc_auc_score,
)
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder, StandardScaler
from xgboost import XGBClassifier

FEATURE_ORDER = [
    "placement_iq", "current_iq", "theta", "avg_test_score",
    "memory_score", "logical_score", "numerical_score", "attention_score", "spatial_score",
    "avg_game_score", "daily_practice_count", "weekly_practice_count", "practice_streak",
    "study_hours", "avg_response_time_sec", "wrong_answer_percent", "avg_difficulty_solved",
    "improvement_trend", "consistency_score", "attendance_percent", "days_until_exam",
    "motivation_score", "ai_coach_usage_count", "question_completion_rate",
]

LABEL_ORDER = ["high_risk", "needs_improvement", "almost_ready", "ready"]


def evaluate(name, model, X_test, y_test, classes):
    pred = model.predict(X_test)
    proba = model.predict_proba(X_test)

    metrics = {
        "accuracy": accuracy_score(y_test, pred),
        "precision_macro": precision_score(y_test, pred, average="macro", zero_division=0),
        "recall_macro": recall_score(y_test, pred, average="macro", zero_division=0),
        "f1_macro": f1_score(y_test, pred, average="macro", zero_division=0),
        "roc_auc_ovr_macro": roc_auc_score(y_test, proba, multi_class="ovr", average="macro"),
    }
    cm = confusion_matrix(y_test, pred, labels=range(len(classes))).tolist()
    report = classification_report(y_test, pred, target_names=classes, output_dict=True, zero_division=0)

    print(f"\n=== {name} ===")
    for k, v in metrics.items():
        print(f"  {k}: {v:.4f}")

    return metrics, cm, report


def main(args):
    df = pd.read_csv(args.data)

    X = df[FEATURE_ORDER].values
    encoder = LabelEncoder()
    encoder.classes_ = np.array(LABEL_ORDER)
    y = encoder.transform(df["label"].values)

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )

    scaler = StandardScaler()
    X_train_scaled = scaler.fit_transform(X_train)
    X_test_scaled = scaler.transform(X_test)

    candidates = {
        "random_forest": RandomForestClassifier(
            n_estimators=300, max_depth=12, min_samples_leaf=5, random_state=42, n_jobs=-1
        ),
        "gradient_boosting": GradientBoostingClassifier(
            n_estimators=200, max_depth=3, learning_rate=0.08, random_state=42
        ),
        "xgboost": XGBClassifier(
            n_estimators=300,
            max_depth=5,
            learning_rate=0.08,
            subsample=0.9,
            colsample_bytree=0.9,
            objective="multi:softprob",
            num_class=len(LABEL_ORDER),
            eval_metric="mlogloss",
            random_state=42,
            n_jobs=-1,
        ),
    }

    results = {}
    fitted = {}
    for name, model in candidates.items():
        # Tree ensembles don't need scaling, but a consistent input contract
        # (always feed the scaler's output) keeps app.py's inference path
        # identical regardless of which model type wins the comparison.
        model.fit(X_train_scaled, y_train)
        metrics, cm, report = evaluate(name, model, X_test_scaled, y_test, LABEL_ORDER)
        results[name] = {"metrics": metrics, "confusion_matrix": cm, "classification_report": report}
        fitted[name] = model

    best_name = max(results, key=lambda n: results[n]["metrics"]["f1_macro"])
    best_model = fitted[best_name]
    print(f"\nSelected best model: {best_name} (f1_macro={results[best_name]['metrics']['f1_macro']:.4f})")

    # SHAP global feature importance for the winning model - TreeExplainer
    # handles all three candidate model families.
    explainer = shap.TreeExplainer(best_model)
    shap_values = explainer.shap_values(X_test_scaled[:2000])
    if isinstance(shap_values, list):
        mean_abs = np.mean([np.abs(sv).mean(axis=0) for sv in shap_values], axis=0)
    else:
        mean_abs = np.abs(shap_values).mean(axis=(0, 2)) if shap_values.ndim == 3 else np.abs(shap_values).mean(axis=0)

    feature_importance = sorted(
        zip(FEATURE_ORDER, mean_abs.tolist()), key=lambda t: t[1], reverse=True
    )

    models_dir = Path(args.out)
    models_dir.mkdir(parents=True, exist_ok=True)
    version = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")

    joblib.dump(best_model, models_dir / "model.joblib")
    joblib.dump(scaler, models_dir / "scaler.joblib")

    metadata = {
        "version": version,
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "best_model": best_name,
        "feature_order": FEATURE_ORDER,
        "label_order": LABEL_ORDER,
        "training_rows": len(df),
        "model_comparison": {n: r["metrics"] for n, r in results.items()},
        "confusion_matrix": results[best_name]["confusion_matrix"],
        "classification_report": results[best_name]["classification_report"],
        "global_feature_importance": feature_importance,
    }
    with open(models_dir / "metadata.json", "w") as f:
        json.dump(metadata, f, indent=2)

    print(f"\nSaved model artifacts to {models_dir}/ (version {version})")
    print("\nTop 8 globally important features (mean |SHAP value|):")
    for feat, val in feature_importance[:8]:
        print(f"  {feat}: {val:.4f}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--data", type=str, default="data/synthetic_student_dataset.csv")
    parser.add_argument("--out", type=str, default="models")
    args = parser.parse_args()
    main(args)
