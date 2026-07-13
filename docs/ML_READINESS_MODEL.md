# ML Readiness Model

Focused reference for what the exam-readiness model actually predicts, on
what inputs, and how well it performs. For the full research-grade
methodology (dataset construction, HPO, bias/fairness analysis, threats to
validity) see [DATASET_METHODOLOGY.md](DATASET_METHODOLOGY.md) and
`ML_RESEARCH_METHODOLOGY.md` (pre-existing, more exhaustive).

## 1. What is predicted

| Output | Type | Ground truth |
|---|---|---|
| Readiness label + percent (`high_risk` / `needs_improvement` / `almost_ready` / `ready`) | Classification | Composite (real where available, calibrated-synthetic elsewhere — see below) |
| Risk of dropping practice (probability + boolean) | Classification | Real (OULAD temporal split) |
| Predicted next assessment score | Regression | Real (OULAD temporal split) |
| Predicted score change | Regression | Real (OULAD temporal split) |
| Time-management readiness percent | Rule-based (not ML) | N/A |

Two outputs the original brief considered — "recommended daily study
hours" and "most effective learning strategy" — are **deliberately not**
built as ML predictions. No available dataset (real or synthetic) provides
valid ground truth for either; they are delivered instead as
`StudyPlanService` rule-based recommendations, explicitly not framed as ML
outputs. This is a documented scope decision, not an oversight — see
CLAUDE.md §17 for the reasoning and the standing instruction not to
reintroduce them as literal ML predictions without genuine new
ground-truth data.

## 2. General vs. exam-specific readiness

The model computes one readiness number regardless of whether a student
has an exam profile — the *distinction* between "general cognitive
readiness" and "readiness for exam X" is applied entirely at the
presentation layer (`ReadinessController::present()`), not by training two
different models:

- If the student has an **active, not-yet-due** exam profile, the number
  is labelled "Readiness for {exam name}".
- Otherwise it's labelled "Overall Cognitive Readiness" — a
  platform-based cognitive-training indicator, not a probability of
  passing any specific exam.
- Once an exam's date passes, the student is prompted (once) for a real
  outcome — attended / passed / score — and the profile moves to a
  "Past Exams" history. Readiness reverts to the general framing until a
  new exam profile is created. The number is never artificially forced to
  a placeholder like 50%; it is always the model's actual output given
  current evidence.

This design means the underlying model and its 43-value feature contract
never change based on exam-profile state — only the label shown to the
student does.

## 3. Input features (43 total)

24 "original" features (theta, IQ, per-category accuracy/theta, session
counts, streak, self-reported study hours/motivation/attendance, etc.) +
19 "advanced" behavioural features (`rolling_avg_score`, `weekly_trend`,
`learning_velocity`, `consistency_index`, `fatigue_score`,
`retention_score`, `engagement_score`, `error_recovery_rate`,
`category_mastery`, `confidence_trend`, `time_management_score`, and
others — each with an exact mathematical definition in
`ml-service/data_pipeline/advanced_features.py`'s module docstring). The
Laravel-side extractor (`FeatureExtractionService::FEATURE_ORDER` +
`::ADVANCED_FEATURE_ORDER`) and the Python-side training pipeline
(`feature_mapping.py::FULL_FEATURE_ORDER`) must stay in exact same-order
sync — verified directly, `len(FULL_FEATURE_ORDER) == 43`.

An additional 9 time-aware features (response-time-derived: exam pace
gap, time efficiency score, etc.) were engineered and are fully computed
end-to-end (`extractTimeAware()`), but are **deliberately not merged**
into the live 43-feature vector — see §4.

## 4. The time-aware ablation result (a negative result, reported honestly)

A dedicated ablation study (`ml-service/ablation_study.py`) compared 6
feature-set variants at a fixed algorithm (XGBoost, the already-selected
winner):

```
A_current_live_baseline        n_features=43  f1_macro=0.6845  <- current live model, HIGHEST
step1_scores_only              n_features= 8  f1_macro=0.5465
step2_plus_irt                 n_features=14  f1_macro=0.5918
B_step3_plus_behaviour         n_features=39  f1_macro=0.6827
C_step4_plus_response_time     n_features=50  f1_macro=0.6764  <- time-aware, WORSE than B
D_step5_full_with_subjective   n_features=52  f1_macro=0.6810  <- time-aware, still below A
```

**No time-aware variant beats the current live 43-feature model.** Adding
the 9 response-time features scores *below* the behaviour-only variant,
and the full model doesn't recover past the baseline either. Per the
brief's own instruction not to claim improved accuracy until evaluation
demonstrates it, the live model was **not** retrained or swapped — this is
reported as a legitimate negative result, not hidden. The 9 time-aware
features remain fully wired end-to-end everywhere except the final
model-swap step, so a future retrain with more real (non-synthesized)
response-time data could revisit this.

## 5. Model selection and evaluation

9 candidate algorithms were screened via 5-fold CV (Random Forest, Extra
Trees, Gradient Boosting, AdaBoost, XGBoost, LightGBM, CatBoost, SVM,
MLP); the top 3 were tuned via Optuna Bayesian HPO under nested 3-fold CV.
TabNet was deliberately excluded (documented rationale: designed for
100K+-row datasets, no proven benefit at this scale, heavy PyTorch
dependency for marginal gain).

**Selected: XGBoost**, optimized macro-F1 = 0.6808 on held-out test data
(training run `20260711054426`, 58,909 train / 14,728 test rows, 43
features). Full evaluation metrics (accuracy, balanced accuracy,
precision/recall/F1 macro & weighted, ROC-AUC, PR-AUC, Matthews
correlation, Cohen's kappa, log loss, Brier score) are computed by
`evaluate.py` and served live at `/evaluation-report`.

**Known discrepancy, not yet reconciled**: the `model.joblib` currently
deployed is stamped version `20260709143659`, an earlier training run than
the `20260711054426` figures quoted here and elsewhere in the docs. Both
runs used the same pipeline and produced comparable macro-F1; this is
flagged rather than silently resolved because model versioning
(`model_registry.py`'s `register_version()`/`promote()`) was never
actually run for either — see §12 of CLAUDE.md for the exact next step.

## 6. Explainability

SHAP (TreeExplainer, primary) + LIME (independent local cross-check) +
permutation importance (model-agnostic) + partial dependence, computed by
`explain.py` and served at `/explainability-report`. Every `/predict`
response includes the top-5 SHAP reasons for that specific prediction plus
a trend-aware plain-English explanation comparing against the student's
previous prediction.

## 7. Continual learning infrastructure

`model_registry.py` implements SHA-256 data-snapshot hashing and a
champion-vs-challenger promotion gate (a challenger must beat the live
model's macro-F1 by ≥0.5 percentage points to be promoted). No automatic
scheduled retraining trigger exists — a deliberate scope decision
(documented as disproportionate infrastructure for a single-VM student
project), not a missing feature.
