# Dataset Methodology

How the exam-readiness model's training data was assembled, why each
source was included or excluded, and how real data was mapped onto
HelaIQ's platform-specific features.

## 1. The core problem

No public dataset records "did this Sri Lankan government-exam candidate
pass," because no such dataset exists. The model instead learns from a
**hybrid** dataset: real learning-analytics data provides genuine signal
for the features that generalize across any learning platform (score
trends, consistency, completion rates), while a calibrated synthetic
generator fills in platform-specific features no public dataset measures
(theta, per-category cognitive scores, game scores).

## 2. Datasets investigated

| Dataset | Used? | Why |
|---|---|---|
| **OULAD** (Open University Learning Analytics Dataset, Kuzilek et al. 2017, CC BY 4.0) | ✅ Yes — 32,593 rows | Real students, real dated assessments, real day-level VLE clickstream. The largest genuinely real signal available; chunk-processed rather than loaded fully (~450MB raw). |
| **UCI Student Performance** (Cortez & Silva 2008, CC BY 4.0) | ✅ Yes — 1,044 rows | Real Portuguese secondary students; only 3 static grade snapshots (no temporal structure), so used for the main hybrid dataset but excluded from the temporal multi-output split (§4). |
| **EdNet** | ❌ No | >100GB — infeasible to process for this project's scope. |
| **ASSISTments / KDD Cup** | ❌ No | Gated access-request process not completed in the project timeline. |
| **xAPI-Edu-Data** | ❌ No | Redundant with OULAD's coverage. |

## 3. The hybrid dataset composition

`ml-service/data/hybrid_student_dataset.csv` — **73,637 rows, 45.7% real**:

| Source | Rows | Share |
|---|---|---|
| `real_oulad` | 32,593 | 44.3% |
| `real_uci` | 1,044 | 1.4% |
| `synthetic_calibrated` | 40,000 | 54.3% |

## 4. Mapping real data onto platform features

`ml-service/data_pipeline/feature_mapping.py` maps each real dataset's
own columns onto HelaIQ's feature contract:

- **Directly measured** features come straight from source data:
  `avg_test_score`, `improvement_trend`, `consistency_score`,
  `practice_streak` (the real longest-consecutive-active-day run from
  clickstream), `question_completion_rate` (real assessment-submission
  ratio).
- **Platform-only** features — theta, IQ, per-category cognitive scores,
  game scores — have no public equivalent, since nothing outside HelaIQ
  measures them. For *real* rows, these are generated from a **pseudo-theta
  derived from that student's own real test performance**, run through the
  same structural equations (`data_pipeline/structural_model.py`) the
  pure-synthetic generator uses. This is a meaningful distinction from
  fabricating them independently: a real high-performing OULAD student
  gets platform-only features consistent with high performance, not
  random noise.

## 5. Synthetic data — calibrated, not arbitrary

`generate_dataset.py`'s composite-score heuristic assigns feature weights
that were **empirically calibrated** via logistic regression against real
OULAD outcomes (`calibrate_synthetic.py` → `calibration_report.json`),
rather than hand-picked. This is the point of the "calibrated" label: the
synthetic portion is shaped by what actually predicts real outcomes in
OULAD, not an arbitrary designer's guess.

## 6. Multi-output models (risk of dropping practice, next score, score
change)

Trained on a **temporal split** of real OULAD data only
(`process_oulad_temporal.py`): first-half real activity → input features,
second-half real outcome → target, so there is no target leakage (a
model can't see the future it's predicting). UCI is excluded from this
specific pipeline because it has only 3 static grade snapshots with no way
to split it temporally without leaking the target.

## 7. Sri Lankan adaptation

None of the real datasets represent Sri Lankan government-exam candidates
— OULAD is UK distance-learning adults, UCI is Portuguese secondary
students. This mismatch is **not hidden**; it is the primary limitation
recorded in the threats-to-validity section of `ML_RESEARCH_METHODOLOGY.md`.
The Sri Lankan-specific adaptation happens through channels *other* than
the training data itself:

- Sri Lankan exam structures (`exam_profiles`' real-exam fields, uploaded
  past-paper-derived question archetypes in Bank2–Bank5).
- Local question categories and terminology, built from 20+ uploaded
  reference PDFs (Sri Lankan IQ/reasoning books, a genuine GCE A/L
  specimen paper, an Environmental Officer recruitment guide).
- Full Sinhala language support with a validated glossary (see
  [SINHALA_LANGUAGE_METHODOLOGY.md](SINHALA_LANGUAGE_METHODOLOGY.md)).
- HelaIQ's own accumulating behavioural data — as real students use the
  platform, `exam_readiness_predictions.features` accumulates genuine
  Sri Lankan usage data that could, in a future retrain, either supplement
  or eventually replace the synthetic portion. This is the intended path
  to closing the population-mismatch gap, not yet exercised (the platform
  has too few real students so far).

## 8. Demo/synthetic UI-testing data is a separate concern

The ~30 synthetic demo student accounts created for UI testing/screenshots
(`is_demo_user = true`, see [TESTING_AND_VALIDATION.md](TESTING_AND_VALIDATION.md))
are **not** part of the ML training dataset described above — they are
platform *usage* data for demoing the product, generated after the model
was already trained, and are excluded from every research export by
default. The two synthetic-data concerns (ML training data vs. UI demo
accounts) are intentionally kept separate and should not be conflated.
