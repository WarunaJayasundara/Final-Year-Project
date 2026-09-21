"""
Calibration and ordinal-decision study for the readiness model.

The four readiness classes are ORDERED (high_risk < needs_improvement < almost_ready < ready)
and the platform shows a 0-100 readiness percent derived from the class probabilities, so two
properties matter beyond macro-F1:

  1. Calibration: when the model says 70% ready, is it right about 70% of the time?
     Measured with log-loss, multi-class Brier score and expected calibration error (ECE).
  2. Ordinal error: predicting "ready" for a "high_risk" student is worse than predicting
     "almost_ready" for a "ready" one. Measured with quadratic weighted kappa (QWK), mean
     absolute class error (MAE) and adjacent accuracy (within one class).

Candidates are fitted on a validation fold and reported on an untouched, student-disjoint test
fold (same protocol as grouped_split_validation.py):
  - baseline argmax of raw XGBoost probabilities (what is deployed)
  - temperature scaling (one parameter) and per-class isotonic regression
  - ordinal-cost decision: pick the class with the lowest expected |true - predicted|

Output: models/calibration_ordinal_report.json. Nothing the live service loads is modified.
Run: ./venv/Scripts/python.exe calibration_ordinal_study.py
"""
import json
import sys
from pathlib import Path

import numpy as np
from scipy.optimize import minimize_scalar
from sklearn.isotonic import IsotonicRegression
from sklearn.metrics import accuracy_score, cohen_kappa_score, f1_score, log_loss
from sklearn.model_selection import StratifiedGroupKFold
from sklearn.preprocessing import StandardScaler

sys.path.insert(0, str(Path(__file__).resolve().parent))

from data_pipeline.feature_mapping import FULL_FEATURE_ORDER  # noqa: E402
from grouped_split_validation import LABELS, SEED, load_with_groups, make_xgb  # noqa: E402

ROOT = Path(__file__).resolve().parent
COST = np.abs(np.arange(4)[:, None] - np.arange(4)[None, :]).astype(float)  # COST[true, predicted]


def normalise(p: np.ndarray) -> np.ndarray:
    p = np.clip(p, 1e-9, None)
    return p / p.sum(axis=1, keepdims=True)


def ece(y: np.ndarray, p: np.ndarray, bins: int = 10) -> float:
    """Top-label expected calibration error."""
    conf, pred = p.max(axis=1), p.argmax(axis=1)
    hit = (pred == y).astype(float)
    edges = np.linspace(0, 1, bins + 1)
    total = 0.0
    for lo, hi in zip(edges[:-1], edges[1:]):
        m = (conf > lo) & (conf <= hi)
        if m.any():
            total += m.mean() * abs(hit[m].mean() - conf[m].mean())
    return float(total)


def metrics(y: np.ndarray, p: np.ndarray, pred: np.ndarray) -> dict:
    p = normalise(p)
    onehot = np.eye(4)[y]
    return {
        "accuracy": round(float(accuracy_score(y, pred)), 4),
        "f1_macro": round(float(f1_score(y, pred, average="macro")), 4),
        "log_loss": round(float(log_loss(y, p, labels=[0, 1, 2, 3])), 4),
        "brier": round(float(((p - onehot) ** 2).sum(axis=1).mean()), 4),
        "ece_top_label": round(ece(y, p), 4),
        "quadratic_weighted_kappa": round(float(cohen_kappa_score(y, pred, weights="quadratic")), 4),
        "mean_abs_class_error": round(float(np.abs(y - pred).mean()), 4),
        "within_one_class_accuracy": round(float((np.abs(y - pred) <= 1).mean()), 4),
    }


def fit_temperature(p_val: np.ndarray, y_val: np.ndarray) -> float:
    logits = np.log(normalise(p_val))

    def nll(t: float) -> float:
        z = logits / t
        z = z - z.max(axis=1, keepdims=True)
        q = np.exp(z)
        q /= q.sum(axis=1, keepdims=True)
        return float(-np.log(q[np.arange(len(y_val)), y_val]).mean())

    return float(minimize_scalar(nll, bounds=(0.3, 4.0), method="bounded").x)


def apply_temperature(p: np.ndarray, t: float) -> np.ndarray:
    z = np.log(normalise(p)) / t
    z = z - z.max(axis=1, keepdims=True)
    q = np.exp(z)
    return q / q.sum(axis=1, keepdims=True)


def fit_isotonic(p_val: np.ndarray, y_val: np.ndarray) -> list:
    return [IsotonicRegression(out_of_bounds="clip", y_min=0, y_max=1).fit(p_val[:, c], (y_val == c).astype(float)) for c in range(4)]


def apply_isotonic(p: np.ndarray, iso: list) -> np.ndarray:
    return normalise(np.column_stack([iso[c].predict(p[:, c]) for c in range(4)]))


def ordinal_decision(p: np.ndarray) -> np.ndarray:
    """Class with the lowest expected absolute class error under the predicted distribution."""
    return (normalise(p) @ COST).argmin(axis=1)


def main() -> None:
    df = load_with_groups()
    y = df["label"].map({l: i for i, l in enumerate(LABELS)}).to_numpy()
    X = df[FULL_FEATURE_ORDER].to_numpy(dtype=float)
    groups = df["group_id"].to_numpy()

    folds = [f for _, f in StratifiedGroupKFold(n_splits=5, shuffle=True, random_state=SEED).split(X, y, groups)]
    te, va, tr = folds[0], folds[1], np.concatenate(folds[2:])
    sc = StandardScaler().fit(X[tr])
    model = make_xgb().fit(sc.transform(X[tr]), y[tr])
    p_va, p_te = model.predict_proba(sc.transform(X[va])), model.predict_proba(sc.transform(X[te]))

    t = fit_temperature(p_va, y[va])
    iso = fit_isotonic(p_va, y[va])
    p_temp, p_iso = apply_temperature(p_te, t), apply_isotonic(p_te, iso)

    report = {
        "protocol": "student-disjoint 5-fold; fold0 test, fold1 validation (calibrators fitted here), rest train",
        "temperature_fitted_on_validation": round(t, 4),
        "baseline_raw_argmax": metrics(y[te], p_te, p_te.argmax(axis=1)),
        "temperature_scaled_argmax": metrics(y[te], p_temp, p_temp.argmax(axis=1)),
        "isotonic_argmax": metrics(y[te], p_iso, p_iso.argmax(axis=1)),
        "raw_ordinal_cost_decision": metrics(y[te], p_te, ordinal_decision(p_te)),
        "temperature_ordinal_cost_decision": metrics(y[te], p_temp, ordinal_decision(p_temp)),
    }
    (ROOT / "models" / "calibration_ordinal_report.json").write_text(json.dumps(report, indent=2))
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
