# How the AI Exam Readiness Predictor Works (Plain-Language Walkthrough)

This document explains the exam-readiness machine learning module added on
top of the existing Rasch/IRT engine (see
[`IRT_ADAPTIVE_TESTING_EXPLAINED.md`](IRT_ADAPTIVE_TESTING_EXPLAINED.md) for
that earlier work). It's written so you can explain the model, its training
data, and its evaluation in a viva without reciting code. For the full
thesis-grade methodology (exact numbers, tables, threats-to-validity/bias/
fairness analysis), see
[`ML_RESEARCH_METHODOLOGY.md`](ML_RESEARCH_METHODOLOGY.md) - this document
is the condensed, plain-language companion to it.

**Research-grade upgrade note:** everything below reflects the current
pipeline - real OULAD/UCI data blended with calibrated synthetic data
(Section 3), a 9-model comparison with Bayesian hyperparameter optimization
(Section 4), and three additional real-data-grounded predictions (Section
6a) - not the original purely-synthetic, 3-model version this module
shipped with initially.

## 1. What it predicts

Given a student's current activity on the platform, the model predicts one
of four exam-readiness classes:

- **Ready** - strong ability, consistent practice, on track.
- **Almost Ready** - close, with a few identifiable gaps.
- **Needs Improvement** - meaningful gaps in ability or practice habits.
- **High Risk** - significant gaps, especially if the exam is close.

Alongside the class, it produces a **readiness percentage** (a smoother,
more informative number than the 4-class label alone), a short list of
**human-readable reasons** - e.g. "Excellent Logical Reasoning", "Low
practice frequency" - and, since the research-grade upgrade, a
**trend-aware plain-English sentence** (e.g. *"Your readiness estimate
changed because your weekly practice volume dropped by 45%..."*) comparing
this prediction against the student's previous one. Three further
predictions - **risk of dropping practice**, **predicted next assessment
score**, and **predicted score change** - are included when available (see
Section 6a); together these make the prediction explainable and
multi-dimensional, not a single black-box number.

## 2. Where the input features come from

The model takes a 42-feature vector per student: the original 24
(`FeatureExtractionService::FEATURE_ORDER`) plus 18 new advanced behavioural
features added by the research-grade upgrade
(`FeatureExtractionService::ADVANCED_FEATURE_ORDER` - rolling/weekly/monthly
score trends, learning velocity, consistency index, fatigue/retention/
engagement scores, error recovery rate, category mastery, and more, each
with an exact mathematical definition documented in
`ml-service/data_pipeline/advanced_features.py`). Most features are
computed directly from data the platform already has:

| Feature | Source |
|---|---|
| `theta`, `current_iq`, `placement_iq` | The existing Rasch/IRT ability estimate (see IRT docs) |
| `avg_test_score`, `wrong_answer_percent` | Average of completed `test_sessions.score_percent` |
| `memory_score`, `logical_score`, `numerical_score`, `attention_score`, `spatial_score` | Latest per-category `user_progress_snapshots` |
| `avg_game_score` | Normalized average across the mini-games |
| `daily_practice_count`, `weekly_practice_count`, `practice_streak` | Session counts/streak over the last 7-30 days |
| `avg_response_time_sec` | Mean gap between consecutive `session_answers.answered_at` timestamps |
| `avg_difficulty_solved` | Mean IRT difficulty of correctly-answered questions |
| `improvement_trend`, `consistency_score` | Trend/variance across the student's recent session scores |
| `question_completion_rate` | Completed vs. started sessions |
| `ai_coach_usage_count` | Count of `ai_coach_logs` rows (added alongside this module) |
| `days_until_exam` | `exam_profiles.exam_date`, set by the student via their Government Exam Profile (see `SYSTEM_DOCUMENTATION.md` §8) |

Three features have **no objective source elsewhere on the platform** -
there's no screen-time instrumentation or physical attendance system - so
rather than fabricate them, they're captured directly from the student via a
short daily check-in form (`user_daily_checkins`): `study_hours`,
`motivation_score` (1-10 self-rating), and `attendance_percent`. If a
student hasn't checked in recently, sensible neutral defaults are used
instead of failing the prediction.

## 3. Why a hybrid real + synthetic training dataset

MindRise's own real usage volume (a handful of students) is nowhere near
enough to train a supervised model on its own. The **original** version of
this module addressed that with 80,000 purely synthetic records. The
**research-grade upgrade** goes further: it integrates two real, publicly
available, citable educational datasets - **OULAD** (32,593 real
university students, real dated assessment scores, a real day-level
engagement clickstream; Kuzilek et al., 2017) and **UCI Student
Performance** (1,044 real secondary-school students with three real
sequential grades; Cortez & Silva, 2008) - mapped onto MindRise's own
feature schema wherever a genuine analogue exists (e.g. `avg_test_score`
from real submitted scores, `practice_streak` from the longest real
consecutive active-day run in the clickstream). Neither dataset measures
MindRise's platform-only constructs (IRT ability, its specific cognitive
categories, its mini-games), so those fields are generated for real rows
from a **pseudo-ability estimate derived from that student's own real test
performance**, passed through the same structural equations the synthetic
generator trusts - not fabricated independently of the real data.

The remaining 40,000 rows are still synthetic, but no longer arbitrary: a
logistic regression fit on the real OULAD outcome data **empirically
calibrates** the composite-score heuristic's weights (replacing several
hand-picked domain-expert weights with observed real coefficients), plus an
**urgency interaction term** (an exam within 14 days amplifies the penalty
for low practice/consistency) and Gaussian noise so the label isn't a
perfectly deterministic function of the features. The final hybrid dataset
is **45.7% grounded in real student outcomes** (up from 0% originally),
with a hard 40% floor enforced in the assembly script so this can't
silently regress. Full dataset-selection rationale (including which
datasets were considered and explicitly excluded, and why) is in
`ML_RESEARCH_METHODOLOGY.md` Section 2.

This mirrors the intellectual honesty of the IRT engine's Monte Carlo
validation: rather than claiming the synthetic portion "recovers real
outcomes," it's validated on how well it recovers a **documented,
now-partially-empirical ground-truth relationship**. The remaining
limitation - discussed transparently, not hidden, in
`ML_RESEARCH_METHODOLOGY.md` Section 12 - is that even 45.7% real is a
*course outcome* from two specific institutions, not MindRise's actual
target domain (Sri Lankan government-exam readiness); closing that gap
fully requires real MindRise outcome data, which the architecture is
already built to accept with no further code changes once enough exists.

## 4. Training and model comparison

`model_comparison.py` splits the 73,637-row hybrid dataset 80/20 (train/
test, stratified by label), scales features with `StandardScaler`, and
screens **9 candidate model families** (Random Forest, Extra Trees,
Gradient Boosting, AdaBoost, XGBoost, LightGBM, CatBoost, SVM, and a small
MLP - TabNet deliberately excluded as a documented scope decision, see
`ML_RESEARCH_METHODOLOGY.md` Section 5.2) via 5-fold cross-validation, then
tunes the top-3 with **Optuna** Bayesian hyperparameter optimization under
**nested cross-validation** (an honest generalization estimate, since
tuning and evaluating on the same data would overstate performance).

**Table (populated after training)** - see `ML_RESEARCH_METHODOLOGY.md`
Section 5.5/6.3 and `ml-service/models/model_comparison_report.json` for
the exact screening, nested-CV, and default-vs-optimized numbers from the
current model version.

The selected model's accuracy on this **4-class** problem with inherently
overlapping class boundaries is expected to sit in a similar range to the
original 3-model version's ~62% - readiness is a continuum, not four crisp
buckets, so some misclassification between *adjacent* classes (e.g.
"Almost Ready" vs "Ready") is inherent to the problem, not a sign of a
broken model. A macro ROC-AUC well above 0.8 shows the model separates
the classes well in a ranking sense even where the hard 4-way decision
boundary is noisy.

**Confusion matrix, per-class precision/recall, and the property that
almost all confusion is between adjacent classes** (the model essentially
never confuses "ready" with "high_risk") are reported in
`ml-service/models/evaluation_report.json` and
`ML_RESEARCH_METHODOLOGY.md` for the current model version - this remains
the important sanity-check property for a decision-support tool: it's not
making wild, implausible calls.

## 5. Explainable AI (SHAP + LIME + permutation importance + PDP)

Rather than a bare percentage, every prediction includes **SHAP** (SHapley
Additive exPlanations - Lundberg & Lee, 2017) values computed against the
"ready" class output, so the student sees *why*. The five features with the
largest |SHAP value| for their specific prediction become the reasons list,
each tagged positive (pushing toward readiness) or negative (pushing toward
risk) and translated into a plain-language message
(`FEATURE_MESSAGES` in `ml-service/app.py`).

The research-grade upgrade cross-checks SHAP against three independent
methods, computed offline by `ml-service/explain.py`: **LIME** (a local
linear surrogate fit around a perturbed instance neighbourhood - a
fundamentally different mechanism from SHAP's game-theoretic values),
**permutation importance** (Breiman, 2001 - fully model-agnostic: shuffle
one feature, measure the held-out accuracy drop), and **partial
dependence** (showing the *shape*, not just the magnitude, of a feature's
effect). Agreement across these mechanistically-different methods is
stronger evidence of genuine model behaviour than any single explanation
technique taken alone; the measured SHAP/LIME agreement rate and the full
global-importance ranking are in
`ml-service/models/explainability_report.json` and
`ML_RESEARCH_METHODOLOGY.md` Section 8.

**Trend-aware plain English.** Every prediction also includes a sentence
comparing this result against the student's *previous* prediction (when one
exists): `ReadinessPredictionService` sends the previous prediction's
stored feature snapshot alongside the new request, and `/predict` computes
the percent change on each top-ranked feature to produce sentences like
*"Your readiness estimate changed because your weekly practice volume
dropped by 45%, your numerical reasoning score declined, and your study
consistency was low"* - directly matching the brief's own example, not just
a static list of reasons.

## 5a. Multi-Output Prediction

Beyond the readiness classification, three further outputs are trained on
**genuine real OULAD ground truth**, not synthetic labels: **risk of
dropping practice** (a binary classifier - will this student go from active
to zero engagement, trained on a real first-half-of-module-predicts-
second-half-outcome split so there's no target leakage), **predicted next
assessment score**, and **predicted score change** (both regressors). All
three appear in `/predict`'s response and, where present, on the student's
dashboard. Two further outputs named in the original upgrade brief -
*recommended daily study hours* and *most effective learning strategy* -
are deliberately **not** trained as supervised predictions, since no
dataset records what the optimal value would have been, and the latter
specifically requires interventional (not just observational) data no
dataset provides; both are delivered instead as transparent rule-based
`StudyPlanService` recommendations. Full reasoning and the real evaluation
numbers (F1/ROC-AUC for the classifier, MAE/RMSE/R² for the regressors) are
in `ML_RESEARCH_METHODOLOGY.md` Section 9.

## 6. Turning a prediction into a percentage

`readiness_percent` is not a second, separately-trained regression - it's a
probability-weighted blend of the classifier's own class probabilities
against fixed class midpoints (`high_risk`=20, `needs_improvement`=45,
`almost_ready`=70, `ready`=90):

```
readiness_percent = Σ P(class) × midpoint(class)
```

This keeps a single model as the source of truth for both the label and the
percentage, and the percentage naturally reflects the model's own
uncertainty (a student near a class boundary gets a percentage between the
two midpoints, not a falsely confident round number).

## 7. Architecture: why a separate Python service

Laravel/PHP has no first-class XGBoost or SHAP implementation, so the
trained model is served by a small **FastAPI microservice**
(`ml-service/app.py`) that Laravel calls over HTTP - the same
swappable-external-service pattern already used for the Gemini AI feedback
integration (`GeminiAiFeedbackService`). `ReadinessPredictionService` (in
Laravel) builds the feature vector, POSTs it to the service's `/predict`
endpoint, and stores the response as a new `exam_readiness_predictions` row
(history is kept, not overwritten, so a readiness trend can be charted).

```
React dashboard --> Laravel (FeatureExtractionService, ReadinessPredictionService)
                        --> HTTP POST /predict --> FastAPI (model.joblib + SHAP + multi-output models)
                        <-- {percent, label, reasons, plain_english_explanation,
                             risk_of_dropping_practice, predicted_next_assessment_score,
                             predicted_score_change} <--
                --> stored in exam_readiness_predictions
```

The upgrade added `previous_features` to the request (for the trend-aware
explanation, Section 5) and several new response fields, but did not touch
this architecture: the same Laravel↔FastAPI HTTP contract, the same
history-not-overwritten persistence, and the same "every other feature
keeps working if this service is down" fallback (`/predict` returns 503;
`ReadinessController` surfaces a friendly error, not a crash).

## 8. Retraining and model versioning

Every training run writes a timestamped `version` alongside the full model
comparison + HPO report (`ml-service/models/model_comparison_report.json`),
comprehensive evaluation (`evaluation_report.json`), and explainability
report (`explainability_report.json`). Beyond that, the research-grade
upgrade adds real **model versioning**: `model_registry.py` archives every
trained version's complete artifact set under `models/versions/v{id}/`
with a SHA-256 hash of the training data snapshot, and `retrain.py`
implements a genuine **champion-vs-challenger promotion gate** - a freshly
retrained model only replaces the live one if it beats the current
champion's macro-F1 by a documented margin, never automatically deploying
a worse or negligibly-different model. `app.py` reports the live version in
every prediction response, and it's stored on every
`exam_readiness_predictions` row - so it's always possible to tell which
model version produced a historical prediction. Admins can see the
currently-deployed model's metrics, the SHAP-ranked global feature
importance, and the full version history on the **ML Research** admin page
(`/admin/ml-research`) without redeploying anything. See
`ML_RESEARCH_METHODOLOGY.md` Section 11 for what is and isn't automated
(there is no scheduled auto-retraining trigger - a deliberate scope
decision).

## 9. Known limitations

Even after the research-grade upgrade, the readiness label is only **45.7%
grounded in real student outcomes** (Section 3) - the remaining share is
still a documented composite heuristic, now calibrated against real data
but not itself a real label. The two real datasets' source populations (UK
distance-learning adults; Portuguese secondary-school students) also don't
match MindRise's actual target demographic (Sri Lankan government-exam
candidates). Neither limitation is hidden: `ML_RESEARCH_METHODOLOGY.md`
Section 12 documents these and several more (platform-only feature
approximation, modest multi-output regression R², a real measured outcome
disparity by disability status and socioeconomic deprivation in the source
data) in full, alongside the concrete path to closing each once more real
MindRise data exists - which, per Section 8 above, requires no
architecture change, only real data to point the existing pipeline at.

## References

- Akiba, T., Sano, S., Yanase, T., Ohta, T. & Koyama, M. (2019). Optuna: A Next-generation Hyperparameter Optimization Framework. *KDD*.
- Breiman, L. (2001). Random Forests. *Machine Learning*, 45(1).
- Chen, T. & Guestrin, C. (2016). XGBoost: A Scalable Tree Boosting System. *KDD*.
- Cortez, P. & Silva, A. (2008). Using Data Mining to Predict Secondary School Student Performance. *Proceedings of 5th FUBUTEC*.
- Friedman, J.H. (2001). Greedy Function Approximation: A Gradient Boosting Machine. *Annals of Statistics*.
- Kuzilek, J., Hlosta, M. & Zdrahal, Z. (2017). Open University Learning Analytics dataset. *Scientific Data*, 4, 170171.
- Lundberg, S.M. & Lee, S.I. (2017). A Unified Approach to Interpreting Model Predictions. *NeurIPS*.
- Ribeiro, M.T., Singh, S. & Guestrin, C. (2016). "Why Should I Trust You?": Explaining the Predictions of Any Classifier. *KDD*.
- Romero, C. & Ventura, S. (2010). Educational Data Mining: A Review of the State of the Art. *IEEE Transactions on Systems, Man, and Cybernetics*.
- Siemens, G. & Baker, R.S.J.d. (2012). Learning Analytics and Educational Data Mining: Towards Communication and Collaboration. *LAK '12*.

For the full, thesis-grade methodology (exact model comparison numbers,
hyperparameter optimization results, complete evaluation suite, bias and
fairness analysis, and every dataset citation), see
[`ML_RESEARCH_METHODOLOGY.md`](ML_RESEARCH_METHODOLOGY.md).
