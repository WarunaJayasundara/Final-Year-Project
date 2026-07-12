"""
Comprehensive evaluation of the model selected by model_comparison.py:
every metric requested for the research upgrade, computed on the SAME
held-out test split model_comparison.py saved (models/test_split.npz), plus
10-fold and repeated stratified CV, bootstrap confidence intervals, learning
curves, validation curves, and an explicit overfitting/underfitting
diagnosis (train vs CV score gap).

Run: python evaluate.py
Output: models/evaluation_report.json
"""
import json
from pathlib import Path

import joblib
import numpy as np
from sklearn.calibration import calibration_curve
from sklearn.metrics import (
    accuracy_score,
    balanced_accuracy_score,
    brier_score_loss,
    cohen_kappa_score,
    f1_score,
    log_loss,
    matthews_corrcoef,
    precision_score,
    recall_score,
    roc_auc_score,
)
from sklearn.model_selection import (
    RepeatedStratifiedKFold,
    StratifiedKFold,
    cross_val_score,
    learning_curve,
    validation_curve,
)

MODELS_DIR = Path(__file__).parent / "models"
LABEL_ORDER = ["high_risk", "needs_improvement", "almost_ready", "ready"]

N_BOOTSTRAP = 1000
BOOTSTRAP_CI = 0.95


def _pr_auc_ovr(y_true: np.ndarray, proba: np.ndarray, n_classes: int) -> float:
    """Macro-averaged one-vs-rest PR AUC (area under precision-recall curve) - more informative
    than ROC AUC on the imbalanced 'ready' class (~10% of the dataset, see data_source label
    distributions in build_hybrid_dataset.py's printed output)."""
    from sklearn.metrics import average_precision_score
    y_bin = np.eye(n_classes)[y_true]
    return float(average_precision_score(y_bin, proba, average="macro"))


def _bootstrap_ci(y_true: np.ndarray, y_pred: np.ndarray, metric_fn, n=N_BOOTSTRAP, ci=BOOTSTRAP_CI):
    rng = np.random.default_rng(42)
    n_samples = len(y_true)
    scores = []
    for _ in range(n):
        idx = rng.integers(0, n_samples, n_samples)
        scores.append(metric_fn(y_true[idx], y_pred[idx]))
    scores = np.array(scores)
    alpha = (1 - ci) / 2
    return {
        "mean": float(scores.mean()),
        "ci_lower": float(np.percentile(scores, 100 * alpha)),
        "ci_upper": float(np.percentile(scores, 100 * (1 - alpha))),
        "ci_level": ci,
        "n_bootstrap": n,
    }


def core_metrics(y_true: np.ndarray, y_pred: np.ndarray, proba: np.ndarray) -> dict:
    n_classes = len(LABEL_ORDER)
    return {
        "accuracy": float(accuracy_score(y_true, y_pred)),
        "balanced_accuracy": float(balanced_accuracy_score(y_true, y_pred)),
        "precision_macro": float(precision_score(y_true, y_pred, average="macro", zero_division=0)),
        "precision_weighted": float(precision_score(y_true, y_pred, average="weighted", zero_division=0)),
        "recall_macro": float(recall_score(y_true, y_pred, average="macro", zero_division=0)),
        "recall_weighted": float(recall_score(y_true, y_pred, average="weighted", zero_division=0)),
        "f1_macro": float(f1_score(y_true, y_pred, average="macro", zero_division=0)),
        "f1_weighted": float(f1_score(y_true, y_pred, average="weighted", zero_division=0)),
        "roc_auc_ovr_macro": float(roc_auc_score(y_true, proba, multi_class="ovr", average="macro")),
        "pr_auc_ovr_macro": _pr_auc_ovr(y_true, proba, n_classes),
        "matthews_corrcoef": float(matthews_corrcoef(y_true, y_pred)),
        "cohen_kappa": float(cohen_kappa_score(y_true, y_pred)),
        "log_loss": float(log_loss(y_true, proba, labels=list(range(n_classes)))),
        "brier_score_ovr_mean": float(np.mean([
            brier_score_loss((y_true == c).astype(int), proba[:, c]) for c in range(n_classes)
        ])),
    }


def calibration_data(y_true: np.ndarray, proba: np.ndarray) -> dict:
    """One-vs-rest calibration curve per class - is a predicted 70% confidence actually
    right ~70% of the time? Central to whether the readiness_percent shown to students
    is a trustworthy probability, not just a ranking score."""
    curves = {}
    for c, label in enumerate(LABEL_ORDER):
        y_bin = (y_true == c).astype(int)
        prob_true, prob_pred = calibration_curve(y_bin, proba[:, c], n_bins=10, strategy="quantile")
        curves[label] = {"prob_true": prob_true.tolist(), "prob_pred": prob_pred.tolist()}
    return curves


def cv_analysis(model, X_train, y_train) -> dict:
    """10-fold and repeated (5x3-fold) stratified CV - the former is the standard reference
    point requested; the latter reduces the variance of the CV estimate itself by averaging
    over multiple random fold assignments, which a single 10-fold split can't do alone."""
    skf10 = StratifiedKFold(n_splits=10, shuffle=True, random_state=42)
    scores_10fold = cross_val_score(model, X_train, y_train, cv=skf10, scoring="f1_macro", n_jobs=-1)

    rskf = RepeatedStratifiedKFold(n_splits=5, n_repeats=3, random_state=42)
    scores_repeated = cross_val_score(model, X_train, y_train, cv=rskf, scoring="f1_macro", n_jobs=-1)

    return {
        "ten_fold_cv": {"mean": float(scores_10fold.mean()), "std": float(scores_10fold.std()), "scores": scores_10fold.tolist()},
        "repeated_cv_5x3": {"mean": float(scores_repeated.mean()), "std": float(scores_repeated.std()), "scores": scores_repeated.tolist()},
    }


def overfitting_diagnosis(model, X_train, y_train) -> dict:
    """
    Learning curve (train vs CV score across increasing training-set sizes) and a direct
    train-vs-CV gap at full size - the standard diagnostic for overfitting (large gap,
    train >> CV) vs underfitting (both scores low and close together).
    """
    train_sizes, train_scores, val_scores = learning_curve(
        model, X_train, y_train, cv=5, scoring="f1_macro", n_jobs=-1,
        train_sizes=np.linspace(0.1, 1.0, 6), random_state=42,
    )
    train_mean = train_scores.mean(axis=1)
    val_mean = val_scores.mean(axis=1)
    gap_at_full_size = float(train_mean[-1] - val_mean[-1])

    if gap_at_full_size > 0.08:
        diagnosis = "overfitting (train score notably exceeds CV score)"
    elif val_mean[-1] < 0.5:
        diagnosis = "possible underfitting (both train and CV scores are low)"
    else:
        diagnosis = "reasonable fit (small train-CV gap, CV score not low)"

    return {
        "train_sizes": train_sizes.tolist(),
        "train_scores_mean": train_mean.tolist(),
        "val_scores_mean": val_mean.tolist(),
        "gap_at_full_training_size": gap_at_full_size,
        "diagnosis": diagnosis,
    }


def validation_curve_analysis(model_name: str, model, X_train, y_train) -> dict | None:
    """Validation curve over each model family's single most impactful hyperparameter -
    shows the bias-variance tradeoff directly (too-simple -> underfits both; too-complex ->
    train score keeps rising while CV score plateaus/falls)."""
    param_grids = {
        "random_forest": ("max_depth", [3, 6, 9, 12, 16, 20, None]),
        "extra_trees": ("max_depth", [3, 6, 9, 12, 16, 20, None]),
        "gradient_boosting": ("max_depth", [1, 2, 3, 4, 5, 6]),
        "xgboost": ("max_depth", [2, 3, 4, 5, 6, 8]),
        "lightgbm": ("max_depth", [2, 3, 5, 7, 10, -1]),
        "catboost": ("depth", [2, 4, 6, 8, 10]),
        "adaboost": ("n_estimators", [25, 50, 100, 200, 400]),
        "mlp": ("alpha", [1e-5, 1e-4, 1e-3, 1e-2, 1e-1]),
    }
    if model_name not in param_grids:
        return None

    param_name, param_range = param_grids[model_name]
    train_scores, val_scores = validation_curve(
        model, X_train, y_train, param_name=param_name, param_range=param_range,
        cv=5, scoring="f1_macro", n_jobs=-1,
    )
    return {
        "param_name": param_name,
        "param_range": [str(p) for p in param_range],
        "train_scores_mean": train_scores.mean(axis=1).tolist(),
        "val_scores_mean": val_scores.mean(axis=1).tolist(),
    }


def per_source_performance(model, X_test, y_test, source_test) -> dict:
    """Test-set performance broken down by data_source (real_oulad / real_uci /
    synthetic_calibrated) - does the model generalize equally well to real students as to
    the synthetic-calibrated rows it was partly trained on, or is it quietly overfitting to
    the easier-to-predict synthetic signal."""
    result = {}
    pred = model.predict(X_test)
    for source in np.unique(source_test):
        mask = source_test == source
        if mask.sum() < 10:
            continue
        result[str(source)] = {
            "n": int(mask.sum()),
            "accuracy": float(accuracy_score(y_test[mask], pred[mask])),
            "f1_macro": float(f1_score(y_test[mask], pred[mask], average="macro", zero_division=0)),
        }
    return result


def main():
    model = joblib.load(MODELS_DIR / "model.joblib")
    metadata = json.loads((MODELS_DIR / "model_comparison_report.json").read_text())
    split = np.load(MODELS_DIR / "test_split.npz", allow_pickle=True)

    X_train, y_train = split["X_train_scaled"], split["y_train"]
    X_test, y_test, source_test = split["X_test_scaled"], split["y_test"], split["source_test"]

    print("Computing core test-set metrics...")
    pred = model.predict(X_test)
    proba = model.predict_proba(X_test)
    metrics = core_metrics(y_test, pred, proba)
    for k, v in metrics.items():
        print(f"  {k}: {v:.4f}")

    print("\nBootstrapping confidence intervals (1000 resamples)...")
    bootstrap = {
        "accuracy": _bootstrap_ci(y_test, pred, accuracy_score),
        "f1_macro": _bootstrap_ci(y_test, pred, lambda a, b: f1_score(a, b, average="macro", zero_division=0)),
    }

    print("Computing calibration curves...")
    calibration = calibration_data(y_test, proba)

    print("Running 10-fold + repeated stratified CV (this may take a while)...")
    cv = cv_analysis(model, X_train, y_train)
    print(f"  10-fold f1_macro: {cv['ten_fold_cv']['mean']:.4f} (+/-{cv['ten_fold_cv']['std']:.4f})")
    print(f"  repeated 5x3 f1_macro: {cv['repeated_cv_5x3']['mean']:.4f} (+/-{cv['repeated_cv_5x3']['std']:.4f})")

    print("Computing learning curve (overfitting/underfitting diagnosis)...")
    overfitting = overfitting_diagnosis(model, X_train, y_train)
    print(f"  Diagnosis: {overfitting['diagnosis']} (gap={overfitting['gap_at_full_training_size']:.4f})")

    print("Computing validation curve...")
    best_name = metadata["best_model"]
    validation = validation_curve_analysis(best_name, model, X_train, y_train)

    print("Computing per-data-source performance breakdown...")
    per_source = per_source_performance(model, X_test, y_test, source_test)
    for source, stats in per_source.items():
        print(f"  {source}: n={stats['n']}, f1_macro={stats['f1_macro']:.4f}")

    report = {
        "best_model": best_name,
        "test_set_size": len(y_test),
        "core_metrics": metrics,
        "bootstrap_confidence_intervals": bootstrap,
        "calibration_curves": calibration,
        "cross_validation": cv,
        "overfitting_diagnosis": overfitting,
        "validation_curve": validation,
        "per_data_source_performance": per_source,
    }

    with open(MODELS_DIR / "evaluation_report.json", "w") as f:
        json.dump(report, f, indent=2)

    print(f"\nWrote {MODELS_DIR / 'evaluation_report.json'}")


if __name__ == "__main__":
    main()
