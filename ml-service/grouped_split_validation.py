"""
Leakage-aware re-validation of the readiness model.

The hybrid dataset's real OULAD half contains students who appear in more than
one module presentation (22.5% of rows belong to a student with >1 row). A plain
row-level train/test split can therefore put the same student on both sides,
which inflates test scores. This script:

  1. Recovers each row's original OULAD `id_student` (build_hybrid_dataset.py
     shuffles deterministically with a seed, so the permutation is reversible;
     the recovery is self-checked against the known per-source row counts).
  2. Evaluates the deployed XGBoost hyper-parameters under
       (a) the original row-level stratified split, and
       (b) a student-disjoint (grouped) stratified split,
     so the size of any leakage inflation is measured, not assumed.
  3. Tests two candidate improvements on the grouped split ONLY, tuning any
     free parameter on a validation fold and reporting on an untouched test fold:
       - soft-voting ensemble (XGBoost + LightGBM + CatBoost)
       - post-hoc per-class probability re-weighting for macro-F1

Output: models/grouped_validation_report.json (nothing in models/ that the live
service loads is modified).

Run: ./venv/Scripts/python.exe grouped_split_validation.py
"""
import json
import sys
from pathlib import Path

import numpy as np
import pandas as pd
from catboost import CatBoostClassifier
from lightgbm import LGBMClassifier
from sklearn.metrics import accuracy_score, f1_score, roc_auc_score
from sklearn.model_selection import StratifiedGroupKFold, train_test_split
from sklearn.preprocessing import StandardScaler
from xgboost import XGBClassifier

sys.path.insert(0, str(Path(__file__).resolve().parent))

from data_pipeline.feature_mapping import FULL_FEATURE_ORDER  # noqa: E402

ROOT = Path(__file__).resolve().parent
LABELS = ["high_risk", "needs_improvement", "almost_ready", "ready"]
SEED = 42


def load_with_groups() -> pd.DataFrame:
    df = pd.read_csv(ROOT / "data" / "hybrid_student_dataset.csv")
    oulad = pd.read_csv(ROOT / "data" / "processed" / "oulad_features.csv")
    n_oulad = len(oulad)
    n_uci = int((df["data_source"] == "real_uci").sum())

    # Reverse build_hybrid_dataset.build(): concat([oulad, uci, synthetic]) then
    # .sample(frac=1, random_state=SEED). Row k of the shuffled frame is row
    # perm[k] of the concatenated frame.
    perm = pd.Series(np.arange(len(df))).sample(frac=1, random_state=SEED).to_numpy()
    expected_source = np.where(perm < n_oulad, "real_oulad", np.where(perm < n_oulad + n_uci, "real_uci", "synthetic_calibrated"))
    if not np.array_equal(expected_source, df["data_source"].to_numpy()):
        raise RuntimeError("Could not reverse the hybrid shuffle: data_source order mismatch.")

    group = np.where(perm < n_oulad, oulad["id_student"].to_numpy()[np.minimum(perm, n_oulad - 1)], -perm - 1)
    df["group_id"] = group
    return df


def score(y_true, proba) -> dict:
    proba = proba / proba.sum(axis=1, keepdims=True)
    pred = proba.argmax(axis=1)
    return {
        "accuracy": round(float(accuracy_score(y_true, pred)), 4),
        "f1_macro": round(float(f1_score(y_true, pred, average="macro")), 4),
        "roc_auc_ovr_macro": round(float(roc_auc_score(y_true, proba, multi_class="ovr", average="macro")), 4),
    }


def make_xgb() -> XGBClassifier:
    hpo = json.loads((ROOT / "models" / "model_comparison_report.json").read_text())["hpo_results"]["xgboost"]["best_params"]
    return XGBClassifier(objective="multi:softprob", num_class=4, eval_metric="mlogloss", random_state=SEED, n_jobs=-1, **hpo)


def make_lgbm() -> LGBMClassifier:
    hpo = json.loads((ROOT / "models" / "model_comparison_report.json").read_text())["hpo_results"]["lightgbm"]["best_params"]
    return LGBMClassifier(random_state=SEED, n_jobs=-1, verbose=-1, **hpo)


def make_cat() -> CatBoostClassifier:
    hpo = json.loads((ROOT / "models" / "model_comparison_report.json").read_text())["hpo_results"]["catboost"]["best_params"]
    return CatBoostClassifier(loss_function="MultiClass", random_seed=SEED, verbose=0, thread_count=-1, allow_writing_files=False, **hpo)


def fit_predict(model, X_tr, y_tr, X_te):
    model.fit(X_tr, y_tr)
    return model.predict_proba(X_te)


def tune_class_weights(proba_val, y_val) -> np.ndarray:
    """Coordinate ascent on per-class multipliers to maximise validation macro-F1."""
    w = np.ones(proba_val.shape[1])
    grid = np.linspace(0.6, 1.8, 25)
    best = f1_score(y_val, (proba_val * w).argmax(axis=1), average="macro")
    for _ in range(3):
        for c in range(len(w)):
            for g in grid:
                trial = w.copy()
                trial[c] = g
                f = f1_score(y_val, (proba_val * trial).argmax(axis=1), average="macro")
                if f > best + 1e-9:
                    best, w = f, trial
    return w / w.mean()


def main() -> None:
    df = load_with_groups()
    y = df["label"].map({l: i for i, l in enumerate(LABELS)}).to_numpy()
    X = df[FULL_FEATURE_ORDER].to_numpy(dtype=float)
    groups = df["group_id"].to_numpy()
    src = df["data_source"].to_numpy()
    n_repeat_students = int(pd.Series(groups[groups > 0]).duplicated(keep=False).sum())
    report: dict = {
        "rows": int(len(df)),
        "real_oulad_rows_sharing_a_student_with_another_row": n_repeat_students,
        "feature_count": len(FULL_FEATURE_ORDER),
    }

    # (a) original row-level split, same seed/proportions as model_comparison.py
    idx = np.arange(len(df))
    tr, te = train_test_split(idx, test_size=0.2, random_state=SEED, stratify=y)
    sc = StandardScaler().fit(X[tr])
    p = fit_predict(make_xgb(), sc.transform(X[tr]), y[tr], sc.transform(X[te]))
    report["row_level_split_xgboost"] = score(y[te], p)
    report["row_level_split_xgboost_real_oulad_only"] = score(y[te][src[te] == "real_oulad"], p[src[te] == "real_oulad"])

    # (b) grouped split: 5 stratified group folds -> fold0=test, fold1=validation, rest=train
    sgkf = StratifiedGroupKFold(n_splits=5, shuffle=True, random_state=SEED)
    folds = [f for _, f in sgkf.split(X, y, groups)]
    te, va = folds[0], folds[1]
    tr = np.concatenate(folds[2:])
    assert not (set(groups[te][groups[te] > 0]) & set(groups[tr][groups[tr] > 0])), "student leaked across split"
    sc = StandardScaler().fit(X[tr])
    Xtr, Xva, Xte = sc.transform(X[tr]), sc.transform(X[va]), sc.transform(X[te])

    p_xgb_va = fit_predict(make_xgb(), Xtr, y[tr], Xva)
    xgb = make_xgb()
    p_xgb = fit_predict(xgb, Xtr, y[tr], Xte)
    report["grouped_split_xgboost"] = score(y[te], p_xgb)
    report["grouped_split_xgboost_real_oulad_only"] = score(y[te][src[te] == "real_oulad"], p_xgb[src[te] == "real_oulad"])

    lgbm, cat = make_lgbm(), make_cat()
    p_lgbm_va, p_cat_va = fit_predict(make_lgbm(), Xtr, y[tr], Xva), fit_predict(make_cat(), Xtr, y[tr], Xva)
    p_lgbm, p_cat = fit_predict(lgbm, Xtr, y[tr], Xte), fit_predict(cat, Xtr, y[tr], Xte)
    p_ens, p_ens_va = (p_xgb + p_lgbm + p_cat) / 3, (p_xgb_va + p_lgbm_va + p_cat_va) / 3
    report["grouped_split_lightgbm"] = score(y[te], p_lgbm)
    report["grouped_split_catboost"] = score(y[te], p_cat)
    report["grouped_split_soft_voting_ensemble"] = score(y[te], p_ens)

    w = tune_class_weights(p_xgb_va, y[va])
    report["class_weights_tuned_on_validation_fold"] = [round(float(x), 3) for x in w]
    report["grouped_split_xgboost_class_reweighted"] = score(y[te], p_xgb * w)
    w_e = tune_class_weights(p_ens_va, y[va])
    report["grouped_split_ensemble_class_reweighted"] = score(y[te], p_ens * w_e)

    (ROOT / "models" / "grouped_validation_report.json").write_text(json.dumps(report, indent=2))
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
