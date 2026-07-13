# IRT Methodology

Formal companion to
[IRT_ADAPTIVE_TESTING_EXPLAINED.md](IRT_ADAPTIVE_TESTING_EXPLAINED.md)
(which walks through the same material with worked examples for viva
prep). This document states the model, calibration method, ability
estimator, and item-selection rule as implemented, plus the validation
evidence for each.

## 1. Measurement model

HelaIQ uses the **Rasch model** (1-parameter logistic IRT):

```
P(X = 1 | θ, b) = 1 / (1 + exp(-(θ - b)))
```

where `θ` is a student's latent ability and `b` is an item's difficulty,
both estimated on the same logit scale. The Rasch model was chosen over a
2- or 3-parameter model (which additionally estimate item discrimination
and/or a guessing parameter) because:

- it requires substantially less response data per item to calibrate
  reliably, which matters for a bank that is still growing (~6,500 active
  items, many with low response counts);
- it has the specific-objectivity property (person and item estimates are
  theoretically independent of *which* items/persons were used to obtain
  them), which is the right property for a platform that keeps adding new
  items over time;
- discrimination is tracked separately as a diagnostic
  (`ItemAnalysisService::itemDiscrimination()`, a point-biserial
  correlation) without folding it into the ability estimate itself.

Implementation: `App\Services\Irt\RaschMath`.

## 2. Item calibration — PROX

Item difficulties are calibrated from real response data using **PROX**
(Wright & Stone, 1979), a closed-form, non-iterative normal-approximation
method. For each item, the proportion correct is converted to a logit,
then corrected for the compression inherent in small-sample logits via an
expansion factor derived from the variance of item and person logits (see
the "Explained" doc for the full worked derivation). PROX was chosen over
full joint maximum-likelihood (JML) estimation because it converges in a
single pass with no risk of the non-convergence pathologies JML can
exhibit on sparse response matrices — appropriate for a platform where
many items still have modest response counts.

Calibration status per item follows an explicit lifecycle
(`irt_calibration_status`: `uncalibrated → provisional → calibrated`),
tracked alongside a running `irt_response_count`. `RaschCalibrationService`
recalibrates on demand (`php artisan irt:calibrate`) and updates both
fields on every run — a real gap (the response count was previously only
backfilled once at migration time, never kept live) found and fixed during
the time-aware upgrade session.

## 3. Ability estimation

Student ability (`theta`) is estimated via **Maximum Likelihood
Estimation (MLE)** over a student's response pattern against the known
item difficulties (`AbilityEstimationService`). A standard error (`se`) is
estimated alongside `theta` from the test information function, and drives
the adaptive stopping rule (§4).

## 4. Adaptive item selection (CAT)

The placement test is a genuine Computerized Adaptive Test, not a fixed
question sequence:

1. Start at a neutral prior ability.
2. After each answer, re-estimate `theta` from all answers so far.
3. Select the next item by **maximum information** at the current `theta`
   estimate (`AdaptiveItemSelectionService::selectNext()`), i.e. the item
   whose difficulty is closest to the student's current estimated ability
   — this is the item that will most reduce uncertainty about `theta`.
4. Stop when either a minimum item count is reached **and** the standard
   error drops below a fixed precision threshold, or a maximum item count
   is reached regardless of precision (a hard ceiling so the test always
   terminates). In practice this yields 15–25 items per placement test.

Category rotation is layered on top of pure maximum-information selection
so a placement test samples across all 5 cognitive domains rather than
converging entirely within whichever domain happens to have the most
informative items at a given ability level.

## 5. Deriving IQ and platform level from theta

- **IQ score**: `IqScoreService::fromTheta()` — a standard deviation IQ,
  `100 + 15 × θ` (mean 100, SD 15), the conventional transformation from a
  standardized ability score to a Wechsler-style IQ figure.
- **Classification bands**: `IqScoreService::classify()` — extremely_low
  (<70) / below_average (70–84) / average (85–114) / above_average
  (115–129) / gifted (≥130), the standard Wechsler bands.
- **Platform level (1–5)**: `LevelAdjustmentService::levelNumberForTheta()`
  — cutpoints at θ = -2.0 / -1.0 / 1.0 / 2.0, chosen to align exactly with
  the IQ classification bands above (a real inconsistency found and fixed
  this session — the two systems previously used different cutpoints,
  producing visible contradictions like "Level 5 – Expert" showing an
  "Above Average" IQ label instead of "Gifted").

## 6. Validation

The engine was validated via Monte Carlo simulation
(`php artisan irt:validate-simulation`): synthetic students and items with
known true parameters are generated, the full pipeline (PROX calibration →
MLE ability estimation → adaptive selection) is run against simulated
responses, and recovered parameters are correlated against ground truth.

**Result**: item-parameter recovery r = 0.991, person-parameter (ability)
recovery r = 0.915 — both strong recovery correlations, consistent with
published Rasch-simulation benchmarks for a bank of this size and response
density.

## 7. What this methodology deliberately does not claim

- The Rasch model assumes unidimensionality (all items measure one latent
  trait) within each of the 5 cognitive-domain categories; cross-domain
  comparisons (e.g. "your memory theta vs your numerical theta") are
  reported as separate per-category estimates, not merged into one score,
  precisely because the platform does not claim a single unidimensional
  trait spans all 5 domains.
- PROX is a classical estimator; it is not claimed to match the precision
  of iterative JML or marginal maximum likelihood at very high response
  volumes. It was chosen for its stability at the response volumes this
  platform actually has, not asserted to be state-of-the-art in general.
