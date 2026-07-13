# Time-Aware Analytics

Covers the response-time capture infrastructure, the speed-accuracy
scoring model, and how they feed (or, per the ablation result, currently
don't feed) the readiness model.

## 1. What existed before this work

Before the time-aware upgrade, the platform captured **zero** per-question
response times anywhere, and the test-taking UI had **no timer of any
kind** except the fixed mock-exam countdown. `answered_at` timestamps
existed but only as an inter-answer-delta proxy, not a real per-question
duration.

## 2. Response-time capture

- `session_answers` gained `response_time_ms`, `time_performance_ratio`
  (actual time ÷ expected time), `answered_within_expected_time`.
- `questions` gained `learned_expected_time_seconds`,
  `time_sample_count`, `time_calibration_status` — an explicit
  `uncalibrated → provisional → calibrated` lifecycle, mirroring the
  existing IRT calibration lifecycle exactly.
- `useQuestionTimer()` (frontend) — the first timer wired into the
  question-answering UI (`SessionRunner`, `AdaptivePlacementRunner`).
- `ResponseTimeCalibrationService` (`php artisan time:calibrate`) learns
  each question's expected solving time from the **median** of real
  recorded response times, once enough samples exist.

## 3. Speed-Accuracy Performance Score

`SpeedAccuracyScoreService` computes a bounded [0, 100] per-answer score:

- Wrong answers always score 0, regardless of speed — a fast wrong answer
  is never rewarded for speed.
- Correct-but-slow answers are penalized up to −15% of full marks.
- There is **no** speed bonus above full marks for a correct-and-fast
  answer — three candidate formulations were compared in the service's own
  docblock before this one was selected, specifically to avoid rewarding
  guessing-fast over genuine accuracy.
- Rasch theta and IRT calibration are completely untouched by this score
  — it is a separate, additive metric, not a replacement for the ability
  estimate.

## 4. Real-exam pacing

`exam_profiles` gained optional, skippable real-exam structure
(`exam_total_questions`, `exam_duration_minutes`, `pass_mark`,
`negative_marking`, `exam_sections`). When both duration and question
count are supplied, `ExamProfile::targetSecondsPerQuestion()` computes a
real pace target (e.g. "72 seconds per question"), which mock exams and
the study-plan readiness-gap panel both reference.

## 5. The 9 time-aware ML features

`FeatureExtractionService::TIME_AWARE_FEATURE_ORDER` (Laravel) and
`ml-service/data_pipeline/time_features.py` (Python) define 9 objective,
response-time-derived features (exam pace gap, time efficiency score, and
others). They are fully computed end-to-end and available via
`extractTimeAware()` — but **deliberately not merged** into the live
43-feature vector the deployed model actually uses.

### Why not — the ablation result

A dedicated ablation study compared the live 43-feature model against 5
variants that progressively add IRT, behavioural, and response-time
features (full results and table in
[ML_READINESS_MODEL.md](ML_READINESS_MODEL.md#4-the-time-aware-ablation-result-a-negative-result-reported-honestly)).
**No time-aware variant beat the current live model** — adding response-time
features actually scored *below* the behaviour-only variant. Per the
brief's own "do not claim improved accuracy until evaluation demonstrates
it," the live model was left unchanged and this is reported as a
legitimate negative result rather than hidden or spun.

One honest caveat on the training data itself: because no public dataset
records real per-item response times, the 9 time-aware features in the
*training* data are synthesized from the same theta/motivation/consistency
latents as other platform-only features — the same pattern already used
for `fatigue_score`/`retention_score`/etc., documented as such in
`time_features.py`'s own docstring. This is one plausible reason the
ablation found no benefit: synthesized response-time features may not
carry the same signal real response-time data would. A future retrain
using genuine accumulated HelaIQ response-time data (once enough real
students have used the timer-equipped UI) could revisit this question with
real rather than synthesized time-aware training signal.

## 6. Rule-based (non-ML) time-management outputs

Two additive `/predict` fields are rule-based, not ML outputs:

- `time_management_readiness_percent` — only computed when the optional
  `exam_pace_gap`/`time_efficiency_score` fields are sent, comparing the
  student's actual pace against their target exam's pace requirement.
- `StudyPlanService`'s `readiness_gap` block and insufficient-plan
  `warning` — fires only when an exam is genuinely near, the readiness gap
  is meaningful, and the current plan mathematically can't close it before
  the exam date. It never guarantees an outcome, only flags a real,
  computed shortfall.

## 7. Mock exams — the first real timed assessment

Mock exams (`session_type = 'mock'`) did not exist in any form before
this work. `QuestionSamplingService::sampleForMockExam()` builds a
weighted-but-bounded category allocation (50% even split across
categories + 50% inverse-mastery-weighted toward weak categories, with a
floor so no requested category is starved to zero). `MockExamRunner` is
the first real countdown timer in the app that auto-submits on expiry.
