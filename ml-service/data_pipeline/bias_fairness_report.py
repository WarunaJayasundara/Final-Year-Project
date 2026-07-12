"""
Bias/fairness analysis backing docs/ML_RESEARCH_METHODOLOGY.md Sec 12.2:
does the real OULAD outcome (`label`) or the deployed model's accuracy on
that data differ meaningfully across gender, disability, age band, or
deprivation (imd_band)? These demographic fields are kept ONLY for this
analysis - map_oulad()'s `_demographic_*` columns are never part of
FULL_FEATURE_ORDER and are never seen by any trained model (see
feature_mapping.py's module docstring and Sec 12.3 of the methodology doc).

Run: python -m data_pipeline.bias_fairness_report
Output: models/bias_fairness_report.json
"""
import json
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.metrics import accuracy_score, f1_score

from data_pipeline.feature_mapping import FULL_FEATURE_ORDER, LABEL_ORDER, map_oulad

MODELS_DIR = Path(__file__).resolve().parent.parent / "models"
OUT_PATH = MODELS_DIR / "bias_fairness_report.json"

DEMOGRAPHIC_GROUPS = {
    "gender": "_demographic_gender",
    "disability": "_demographic_disability",
    "age_band": "_demographic_age_band",
}

# imd_band midpoint is continuous - bucketed into terciles for group
# comparison. UK IMD convention: "0-10%" denotes the MOST deprived decile,
# so ascending midpoint runs most-deprived -> least-deprived (the opposite
# of what an unexamined reading of "0-10%, ..., 90-100%" might suggest) -
# these labels are ordered to match that, not simple ascending-number order.
IMD_TERCILE_LABELS = ["most_deprived_third", "mid_deprivation_third", "least_deprived_third"]


def _outcome_distribution_by_group(df: pd.DataFrame, group_col: str) -> dict:
    result = {}
    for group_value, sub in df.groupby(group_col, observed=True):
        if len(sub) < 30:
            continue
        result[str(group_value)] = {
            "n": int(len(sub)),
            "label_distribution": sub["label"].value_counts(normalize=True).round(3).to_dict(),
        }
    return result


def _model_accuracy_by_group(df: pd.DataFrame, group_col: str, model, scaler) -> dict:
    X = scaler.transform(df[FULL_FEATURE_ORDER].values)
    y_true = df["label"].map({label: i for i, label in enumerate(LABEL_ORDER)}).values
    y_pred = model.predict(X)

    result = {}
    for group_value, sub_idx in df.groupby(group_col, observed=True).indices.items():
        if len(sub_idx) < 30:
            continue
        result[str(group_value)] = {
            "n": int(len(sub_idx)),
            "accuracy": float(accuracy_score(y_true[sub_idx], y_pred[sub_idx])),
            "f1_macro": float(f1_score(y_true[sub_idx], y_pred[sub_idx], average="macro", zero_division=0)),
        }
    return result


def build() -> dict:
    df = map_oulad()
    df["imd_tercile"] = pd.qcut(df["_demographic_imd_midpoint"], q=3, labels=IMD_TERCILE_LABELS, duplicates="drop")

    model_path = MODELS_DIR / "model.joblib"
    scaler_path = MODELS_DIR / "scaler.joblib"
    have_model = model_path.exists() and scaler_path.exists()
    model = joblib.load(model_path) if have_model else None
    scaler = joblib.load(scaler_path) if have_model else None

    # Guards against running this against a stale model.joblib trained on
    # the pre-upgrade 24-feature schema (model_comparison.py not yet run) -
    # the outcome-distribution analysis below is still meaningful on its
    # own; only the model-accuracy breakdown needs a schema-matched model.
    if have_model and getattr(scaler, "n_features_in_", None) != len(FULL_FEATURE_ORDER):
        print(f"WARNING: deployed scaler expects {getattr(scaler, 'n_features_in_', '?')} features, "
              f"but FULL_FEATURE_ORDER has {len(FULL_FEATURE_ORDER)} - skipping model-accuracy-by-group "
              f"(run model_comparison.py first to deploy a schema-matched model).")
        have_model = False

    report = {"n_students": len(df), "groups_analyzed": list(DEMOGRAPHIC_GROUPS.keys()) + ["imd_tercile"], "outcome_distribution": {}, "model_accuracy_by_group": {} if have_model else None}

    for label, col in {**DEMOGRAPHIC_GROUPS, "imd_tercile": "imd_tercile"}.items():
        report["outcome_distribution"][label] = _outcome_distribution_by_group(df, col)
        if have_model:
            report["model_accuracy_by_group"][label] = _model_accuracy_by_group(df, col, model, scaler)

    OUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    with open(OUT_PATH, "w") as f:
        json.dump(report, f, indent=2)

    print(f"Wrote {OUT_PATH}")
    print("\nOutcome ('ready' share) by group:")
    for group_label, groups in report["outcome_distribution"].items():
        print(f"  {group_label}:")
        for value, stats in groups.items():
            ready_share = stats["label_distribution"].get("ready", 0.0)
            print(f"    {value}: n={stats['n']}, ready_share={ready_share:.3f}")

    return report


if __name__ == "__main__":
    build()
