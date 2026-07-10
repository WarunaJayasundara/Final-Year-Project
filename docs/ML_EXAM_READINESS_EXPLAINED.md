# How the AI Exam Readiness Predictor Works (Plain-Language Walkthrough)

This document explains the exam-readiness machine learning module added on
top of the existing Rasch/IRT engine (see
[`IRT_ADAPTIVE_TESTING_EXPLAINED.md`](IRT_ADAPTIVE_TESTING_EXPLAINED.md) for
that earlier work). It's written so you can explain the model, its training
data, and its evaluation in a viva without reciting code.

## 1. What it predicts

Given a student's current activity on the platform, the model predicts one
of four exam-readiness classes:

- **Ready** - strong ability, consistent practice, on track.
- **Almost Ready** - close, with a few identifiable gaps.
- **Needs Improvement** - meaningful gaps in ability or practice habits.
- **High Risk** - significant gaps, especially if the exam is close.

Alongside the class, it produces a **readiness percentage** (a smoother,
more informative number than the 4-class label alone) and a short list of
**human-readable reasons** - e.g. "Excellent Logical Reasoning", "Low
practice frequency" - so the prediction is explainable, not a black box.

## 2. Where the input features come from

The model takes a fixed 24-feature vector per student
(`FeatureExtractionService::FEATURE_ORDER` in the backend). Most features
are computed directly from data the platform already has:

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

## 3. Why a synthetic training dataset

MindRise's real usage volume (a handful of students) is nowhere near enough
to train a supervised model, and no public dataset matches this exact
feature set. So `ml-service/generate_dataset.py` generates 80,000 synthetic
student records from documented statistical distributions (e.g.
`theta ~ N(0,1)`, category scores as noisy functions of theta, practice
counts as Poisson processes driven by a latent "motivation" trait).

Each record's label isn't assigned arbitrarily - it comes from a **documented
composite heuristic** (`_composite_score` in the same file): a weighted,
z-scored combination of the features a domain expert would actually consider
"exam readiness" (ability, consistency, practice volume, attendance,
motivation, etc.), plus an **urgency interaction term** (an exam within 14
days amplifies the penalty for low practice/consistency, rather than just
adding a flat effect), plus Gaussian noise so the label isn't a perfectly
deterministic function of the features - real outcomes never are.

This mirrors the intellectual honesty of the IRT engine's Monte Carlo
validation: the model is validated on how well it **recovers a known,
documented ground-truth relationship** from raw features alone, not on real
historical exam outcomes (which don't exist yet for this platform). Once
enough real students report a real exam result, the `label` column should be
replaced by that outcome and the model retrained - `train_model.py` itself
does not need to change.

## 4. Training and model comparison

`train_model.py` splits the 80,000 rows 80/20 (train/test, stratified by
label), scales features with `StandardScaler`, and trains three candidate
classifiers, each evaluated on the held-out 20%:

| Model | Accuracy | Precision (macro) | Recall (macro) | F1 (macro) | ROC-AUC (macro, OvR) |
|---|---|---|---|---|---|
| Random Forest | 0.6109 | 0.6484 | 0.5722 | 0.5967 | 0.8498 |
| Gradient Boosting | 0.6191 | 0.6449 | 0.5918 | 0.6115 | 0.8539 |
| **XGBoost (selected)** | **0.6219** | **0.6497** | **0.5959** | **0.6159** | **0.8555** |

XGBoost was selected automatically (highest macro-F1). An accuracy around
62% on a **4-class** problem with deliberately overlapping class boundaries
and injected noise is expected and reasonable - readiness is a continuum,
not four crisp buckets, so some misclassification between *adjacent*
classes (e.g. "Almost Ready" vs "Ready") is inherent to the problem, not a
sign of a broken model. The macro ROC-AUC of 0.86 shows the model separates
the classes well in a ranking sense even where the hard 4-way decision
boundary is noisy.

**Confusion matrix** (rows = actual, columns = predicted; order: high_risk,
needs_improvement, almost_ready, ready):

```
                 high_risk  needs_improvement  almost_ready  ready
high_risk             2271               1274            44      0
needs_improvement      764               3938          1266     12
almost_ready            26               1434          2949    343
ready                    0                 40           847    792
```

Almost all confusion is between **adjacent** classes (e.g.
needs_improvement <-> almost_ready) - the model essentially never confuses
"ready" with "high_risk" (0 and 0 in the far corners), which is the
important property for a decision-support tool: it's not making wild,
implausible calls.

## 5. Explainable AI (SHAP)

Rather than a bare percentage, every prediction includes **SHAP** (SHapley
Additive exPlanations - Lundberg & Lee, 2017) values computed against the
"ready" class output, so the student sees *why*. The five features with the
largest |SHAP value| for their specific prediction become the reasons list,
each tagged positive (pushing toward readiness) or negative (pushing toward
risk) and translated into a plain-language message
(`FEATURE_MESSAGES` in `ml-service/app.py`).

**Global feature importance** (mean |SHAP value| across the test set, top
8 of 24):

| Rank | Feature | Mean \|SHAP\| |
|---|---|---|
| 1 | `avg_test_score` | 0.400 |
| 2 | `theta` (IRT ability) | 0.337 |
| 3 | `practice_streak` | 0.250 |
| 4 | `consistency_score` | 0.203 |
| 5 | `wrong_answer_percent` | 0.181 |
| 6 | `weekly_practice_count` | 0.169 |
| 7 | `study_hours` | 0.159 |
| 8 | `improvement_trend` | 0.153 |

This ordering matches domain intuition (raw ability and test performance
dominate, followed closely by practice consistency/frequency) - a useful
sanity check that the model learned a sensible relationship rather than an
arbitrary one.

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
                        --> HTTP POST /predict --> FastAPI (model.joblib + SHAP)
                        <-- {percent, label, reasons} <--
                --> stored in exam_readiness_predictions
```

## 8. Retraining and model versioning

Every training run writes a timestamped `version` into
`ml-service/models/metadata.json` alongside the full metric comparison,
confusion matrix, and feature importances. `app.py` reports this version in
every prediction response, and it's stored on every
`exam_readiness_predictions` row - so it's always possible to tell which
model version produced a historical prediction, and admins can see the
currently-deployed model's metrics on the Psychometrics/ML overview page
without redeploying anything. Retraining is just re-running
`generate_dataset.py` (or swapping in a real dataset once available) and
`train_model.py` - the new `model.joblib`/`scaler.joblib`/`metadata.json`
are picked up the next time `app.py` restarts.

## 9. Known limitation

The labels are synthetic (Section 3). This is disclosed, not hidden: the
model is a genuine demonstration of a full ML pipeline (data generation,
feature engineering, model comparison, evaluation, explainability, and
deployment) applied to this platform's real feature-extraction logic, and
the path to retraining on real outcomes once they exist requires no
architecture change - only a real `label` column.

## References

- Lundberg, S.M. & Lee, S.I. (2017). A Unified Approach to Interpreting Model Predictions. *NeurIPS*.
- Chen, T. & Guestrin, C. (2016). XGBoost: A Scalable Tree Boosting System. *KDD*.
- Friedman, J.H. (2001). Greedy Function Approximation: A Gradient Boosting Machine. *Annals of Statistics*.
- Breiman, L. (2001). Random Forests. *Machine Learning*, 45(1).
- Romero, C. & Ventura, S. (2010). Educational Data Mining: A Review of the State of the Art. *IEEE Transactions on Systems, Man, and Cybernetics*.
- Siemens, G. & Baker, R.S.J.d. (2012). Learning Analytics and Educational Data Mining: Towards Communication and Collaboration. *LAK '12*.
