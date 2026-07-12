"""
Expanded explainability for the selected model: global SHAP importance,
per-instance SHAP explanations, SHAP feature-interaction values, an
independent LIME cross-check on a sample of instances, permutation feature
importance (model-agnostic, unlike SHAP's model-specific TreeExplainer),
and partial dependence data for the top features - each computed via a
genuinely different method so they can be triangulated against each other
rather than relying on a single explanation technique.

Run: python explain.py
Output: models/explainability_report.json
"""
import json
from pathlib import Path

import joblib
import numpy as np
import shap
from lime.lime_tabular import LimeTabularExplainer
from sklearn.inspection import partial_dependence, permutation_importance

from data_pipeline.feature_mapping import FULL_FEATURE_ORDER as FEATURE_ORDER

MODELS_DIR = Path(__file__).parent / "models"
LABEL_ORDER = ["high_risk", "needs_improvement", "almost_ready", "ready"]

SHAP_SAMPLE_SIZE = 2000
LIME_SAMPLE_SIZE = 30
PDP_TOP_N_FEATURES = 6


def _get_shap_values(explainer, X):
    """Normalizes SHAP's return shape across model families (list-of-arrays for some,
    a single 3D array for others) into a consistent (n_samples, n_features, n_classes) array."""
    raw = explainer.shap_values(X)
    if isinstance(raw, list):
        return np.stack(raw, axis=-1)
    if raw.ndim == 3:
        return raw
    return raw[..., np.newaxis]


def global_shap_importance(shap_values: np.ndarray) -> list:
    mean_abs = np.abs(shap_values).mean(axis=(0, 2))
    return sorted(zip(FEATURE_ORDER, mean_abs.tolist()), key=lambda t: t[1], reverse=True)


def shap_interactions(explainer, X_sample: np.ndarray, top_features: list) -> dict:
    """
    Pairwise SHAP interaction values for the top 5 globally-important
    features - captures whether two features' combined effect on the
    prediction is more/less than the sum of their individual effects (e.g.
    "low weekly_practice_count matters MORE when days_until_exam is also
    low" is an interaction, not just two independent main effects).
    """
    top_idx = [FEATURE_ORDER.index(f) for f, _ in top_features[:5]]
    try:
        interaction_values = explainer.shap_interaction_values(X_sample[:min(300, len(X_sample))])
    except Exception as e:  # noqa: BLE001 - some model/SHAP combinations don't support interactions
        return {"available": False, "reason": str(e)}

    if isinstance(interaction_values, list):
        interaction_values = np.stack(interaction_values, axis=-1)
    # Average over the "ready" class and samples for a single interaction matrix.
    ready_idx = LABEL_ORDER.index("ready")
    if interaction_values.ndim == 4:
        matrix = np.abs(interaction_values[:, :, :, ready_idx]).mean(axis=0)
    else:
        matrix = np.abs(interaction_values).mean(axis=0)

    pairs = []
    for i in top_idx:
        for j in top_idx:
            if i < j:
                pairs.append({"feature_a": FEATURE_ORDER[i], "feature_b": FEATURE_ORDER[j], "interaction_strength": float(matrix[i, j])})
    pairs.sort(key=lambda p: p["interaction_strength"], reverse=True)

    return {"available": True, "top_pairs": pairs}


def lime_cross_check(model, X_train: np.ndarray, X_sample: np.ndarray, shap_values: np.ndarray) -> dict:
    """
    LIME (Ribeiro et al. 2016) explains individual predictions by fitting a local linear
    surrogate model around a perturbed neighbourhood of that instance - a fundamentally
    different mechanism from SHAP's game-theoretic Shapley values. Agreement between the two
    methods' top-ranked features for the same instances is evidence the explanation reflects
    a real model behaviour rather than an artifact of one specific XAI technique.
    """
    explainer = LimeTabularExplainer(
        X_train, feature_names=FEATURE_ORDER, class_names=LABEL_ORDER, mode="classification", random_state=42,
    )

    ready_idx = LABEL_ORDER.index("ready")
    agreements = []
    n_check = min(LIME_SAMPLE_SIZE, len(X_sample))

    for i in range(n_check):
        lime_exp = explainer.explain_instance(X_sample[i], model.predict_proba, num_features=5, labels=[ready_idx])
        lime_top = {FEATURE_ORDER[int(idx)] for idx, _ in lime_exp.as_map()[ready_idx][:5]} if lime_exp.as_map().get(ready_idx) else set()

        shap_top = {FEATURE_ORDER[j] for j in np.argsort(-np.abs(shap_values[i, :, ready_idx]))[:5]}

        overlap = len(lime_top & shap_top) / max(len(lime_top | shap_top), 1)
        agreements.append(overlap)

    return {
        "n_instances_checked": n_check,
        "mean_top5_feature_overlap_with_shap": float(np.mean(agreements)) if agreements else None,
        "note": "Overlap = |intersection|/|union| of each method's top-5 features for the 'ready' class, averaged across instances.",
    }


def permutation_importance_report(model, X_test: np.ndarray, y_test: np.ndarray) -> list:
    """
    Permutation importance (Breiman 2001, as implemented in sklearn): shuffle one feature
    column at a time and measure the drop in held-out F1 - a third, fully model-agnostic
    importance measure (unlike SHAP/LIME, it doesn't need to understand the model's
    internals at all, just its predict function), useful for triangulating against SHAP's
    global importance.
    """
    result = permutation_importance(model, X_test, y_test, scoring="f1_macro", n_repeats=10, random_state=42, n_jobs=-1)
    ranked = sorted(zip(FEATURE_ORDER, result.importances_mean.tolist(), result.importances_std.tolist()),
                     key=lambda t: t[1], reverse=True)
    return [{"feature": f, "importance_mean": m, "importance_std": s} for f, m, s in ranked]


def partial_dependence_report(model, X_train: np.ndarray, top_features: list) -> dict:
    """Partial dependence: holding all other features at their observed distribution, how does
    the predicted probability of 'ready' change as ONE feature varies - shows the shape (linear?
    threshold? saturating?) of each top feature's effect, which a bare importance score can't."""
    ready_idx = LABEL_ORDER.index("ready")
    report = {}
    for feature, _ in top_features[:PDP_TOP_N_FEATURES]:
        idx = FEATURE_ORDER.index(feature)
        try:
            pd_result = partial_dependence(model, X_train, features=[idx], kind="average")
            report[feature] = {
                "grid_values": pd_result["grid_values"][0].tolist(),
                "average_prediction": pd_result["average"][ready_idx if pd_result["average"].shape[0] > 1 else 0].tolist(),
            }
        except Exception as e:  # noqa: BLE001
            report[feature] = {"available": False, "reason": str(e)}
    return report


def plain_english_explanation(feature_deltas: dict, top_n: int = 3) -> str:
    """
    Turns the top-N SHAP-ranked feature *changes* (current vs a previous prediction, both
    supplied by the caller - see FastAPI's /explain endpoint) into a single trend-aware
    sentence, e.g. "Your readiness decreased because your weekly practice dropped by 45%,
    your numerical reasoning score declined, and your study consistency was low." Falls
    back to a static (non-trend) phrasing when no previous prediction exists yet.
    """
    ranked = sorted(feature_deltas.items(), key=lambda kv: abs(kv[1]["shap_impact"]), reverse=True)[:top_n]
    clauses = []
    for feature, info in ranked:
        direction_word = "increased" if info.get("pct_change", 0) > 5 else "dropped" if info.get("pct_change", 0) < -5 else None
        label = FEATURE_LABELS.get(feature, feature)
        if direction_word and info.get("pct_change") is not None:
            clauses.append(f"your {label} {direction_word} by {abs(info['pct_change']):.0f}%")
        else:
            clauses.append(f"your {label} was {'a strength' if info['shap_impact'] >= 0 else 'a weak point'}")

    if not clauses:
        return "Not enough data yet to explain this prediction's biggest drivers."

    joined = ", ".join(clauses[:-1]) + (f", and {clauses[-1]}" if len(clauses) > 1 else clauses[0])
    return f"Your readiness estimate changed because {joined}."


FEATURE_LABELS = {
    "weekly_practice_count": "weekly practice volume",
    "avg_test_score": "average test score",
    "numerical_score": "numerical reasoning score",
    "logical_score": "logical reasoning score",
    "memory_score": "memory score",
    "attention_score": "attention score",
    "spatial_score": "spatial reasoning score",
    "consistency_score": "study consistency",
    "consistency_index": "study consistency",
    "study_hours": "daily study hours",
    "practice_streak": "practice streak",
    "engagement_score": "overall engagement",
    "fatigue_score": "session fatigue",
    "retention_score": "knowledge retention",
    "learning_velocity": "learning speed",
    "revision_frequency": "revision frequency",
}


def main():
    model = joblib.load(MODELS_DIR / "model.joblib")
    metadata = json.loads((MODELS_DIR / "model_comparison_report.json").read_text())
    split = np.load(MODELS_DIR / "test_split.npz", allow_pickle=True)
    X_train, X_test, y_test = split["X_train_scaled"], split["X_test_scaled"], split["y_test"]

    best_name = metadata["best_model"]
    print(f"Explaining model: {best_name}")

    sample_size = min(SHAP_SAMPLE_SIZE, len(X_test))
    X_sample = X_test[:sample_size]

    print("Computing SHAP values...")
    if best_name in ("svm", "mlp"):
        background = shap.sample(X_train, 100, random_state=42)
        explainer = shap.KernelExplainer(model.predict_proba, background)
        shap_values = _get_shap_values(explainer, X_sample[:200])  # KernelExplainer is much slower - smaller sample
        X_sample = X_sample[:200]
    else:
        explainer = shap.TreeExplainer(model)
        shap_values = _get_shap_values(explainer, X_sample)

    global_importance = global_shap_importance(shap_values)
    print("Top 8 globally important features (mean |SHAP|):")
    for feat, val in global_importance[:8]:
        print(f"  {feat}: {val:.4f}")

    print("\nComputing SHAP interaction values (tree models only)...")
    interactions = shap_interactions(explainer, X_sample, global_importance) if best_name not in ("svm", "mlp") else {"available": False, "reason": "not supported for non-tree models"}

    print("\nRunning LIME cross-check...")
    lime_report = lime_cross_check(model, X_train, X_sample, shap_values)
    print(f"  Mean SHAP/LIME top-5 feature overlap: {lime_report['mean_top5_feature_overlap_with_shap']:.2f}")

    print("\nComputing permutation importance...")
    perm_importance = permutation_importance_report(model, X_test, y_test)
    print("Top 8 by permutation importance:")
    for entry in perm_importance[:8]:
        print(f"  {entry['feature']}: {entry['importance_mean']:.4f}")

    print("\nComputing partial dependence for top features...")
    pdp = partial_dependence_report(model, X_train, global_importance)

    report = {
        "best_model": best_name,
        "global_shap_importance": global_importance,
        "shap_interactions": interactions,
        "lime_cross_check": lime_report,
        "permutation_importance": perm_importance,
        "partial_dependence": pdp,
    }

    with open(MODELS_DIR / "explainability_report.json", "w") as f:
        json.dump(report, f, indent=2)

    print(f"\nWrote {MODELS_DIR / 'explainability_report.json'}")


if __name__ == "__main__":
    main()
