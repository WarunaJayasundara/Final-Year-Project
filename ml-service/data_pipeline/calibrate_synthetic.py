"""
Fits an empirical multinomial logistic regression of OULAD's real
final_result label on the subset of MindRise features that have a genuine
OULAD analogue (avg_test_score, improvement_trend, consistency_score,
weekly_practice_count, attendance_percent - see feature_mapping.py for how
each is derived), then converts the fitted coefficients into the same
positive/inverted weight format generate_dataset.py's `_composite_score`
already uses, so the synthetic generator's *documented* domain-expert
weights (see that function's docstring) can be replaced by *empirically
observed* ones from real student outcomes wherever real data actually
covers the feature.

This is the paper-defensible way to blend real data into a structural
simulation: the simulation's causal graph and platform-only variables
(theta, cognitive-game scores, etc.) are unchanged, but the coefficients
governing how the *real-world-measurable* variables affect outcome are no
longer hand-picked - they are fit from 32,593 real students.

Run: python -m data_pipeline.calibrate_synthetic
Output: data/processed/calibration_report.json
"""
import json
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.linear_model import LogisticRegression
from sklearn.preprocessing import StandardScaler

from data_pipeline.feature_mapping import LABEL_ORDER, map_oulad

PROCESSED = Path(__file__).resolve().parent.parent / "data" / "processed"

# Only features with a genuine, directly-measured real-data analogue are
# calibrated - platform-only features (theta, memory_score, ...) keep their
# documented hand-picked weight in generate_dataset.py regardless of this
# report (see _load_calibrated_weights there).
CALIBRATABLE_POSITIVE = ["avg_test_score", "improvement_trend", "consistency_score", "weekly_practice_count", "attendance_percent"]
CALIBRATABLE_INVERTED: list[str] = []


def calibrate() -> dict:
    mapped = map_oulad()

    X_cols = CALIBRATABLE_POSITIVE + CALIBRATABLE_INVERTED
    X = mapped[X_cols].fillna(mapped[X_cols].mean())
    y = mapped["label"].map({label: i for i, label in enumerate(LABEL_ORDER)})

    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X)

    # multinomial logistic regression: one coefficient vector per class,
    # ordered high_risk < needs_improvement < almost_ready < ready - we want
    # the direction/magnitude of each feature's association with *higher*
    # readiness, so we take the coefficient on the "ready" class as the
    # feature's overall positive-direction weight (an ordinal outcome, so
    # the top class's coefficients are the cleanest single summary).
    model = LogisticRegression(max_iter=2000)
    model.fit(X_scaled, y)

    ready_idx = LABEL_ORDER.index("ready")
    raw_coefs = model.coef_[ready_idx]

    # Renormalize so the calibrated weights sum to the same total mass as
    # the hand-picked weights they replace (0.14+0.06+0.08+0.07+0.05=0.40),
    # preserving the relative importance *pattern* observed in real data
    # while keeping the composite score's overall scale unchanged - the
    # weights other than these five (theta, category scores, etc.) still
    # sum to 0.60, so overwriting these five's relative sizes doesn't
    # silently make the whole composite dominated by one group.
    positive_mass = 0.14 + 0.06 + 0.08 + 0.07 + 0.05
    abs_coefs = np.abs(raw_coefs)
    normalized = abs_coefs / abs_coefs.sum() * positive_mass

    positive_weights = dict(zip(X_cols, normalized.tolist()))

    report = {
        "source": "OULAD (Kuzilek et al. 2017), n=%d real students" % len(mapped),
        "method": "Multinomial logistic regression of final_result on real-measured features; "
                  "coefficients on the 'ready' (Distinction) class, L1-normalized to the original "
                  "hand-picked weight mass for the same features.",
        "calibrated_features": X_cols,
        "raw_coefficients_ready_class": dict(zip(X_cols, raw_coefs.tolist())),
        "positive_weights": positive_weights,
        "inverted_weights": {},
        "model_train_accuracy": float(model.score(X_scaled, y)),
    }

    PROCESSED.mkdir(parents=True, exist_ok=True)
    with open(PROCESSED / "calibration_report.json", "w") as f:
        json.dump(report, f, indent=2)

    print("Calibrated weights (replacing hand-picked defaults for these 5 features):")
    for feat, w in positive_weights.items():
        print(f"  {feat}: {w:.4f}")
    print(f"\nWrote {PROCESSED / 'calibration_report.json'}")

    return report


if __name__ == "__main__":
    calibrate()
