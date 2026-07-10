# How MindRise's Adaptive Testing Actually Works (Plain-Language Walkthrough)

This document exists so you can explain the IRT engine in your viva without
reciting code — it walks through the same maths your code runs, with small
worked examples you can redo by hand or in a spreadsheet.

## 1. The core idea: everyone has a hidden "ability" number

Before this rewrite, the platform decided your level by looking at your
placement-test percentage and slotting it into a band (0-39% → Level 1,
40-54% → Level 2, etc.). That's not measurement — it's a lookup table. It
treats every question as equally hard, which you know isn't true: "what's
next in 2, 4, 6, 8?" and "a shop had 52 books, sold 11, how many are left,
then split the rest into 3 boxes with a leftover of 2 — how many per box?"
are not the same difficulty.

**Item Response Theory (IRT)** fixes this by giving every *question* its own
difficulty number, and every *student* their own ability number, both on the
same scale. That ability number is called **theta (θ)** — a "latent trait,"
meaning we never observe it directly, only estimate it from how someone
answers questions of known difficulty.

The specific, simplest version of IRT is the **Rasch model** (Rasch, 1960),
also called the 1-parameter logistic model. It says:

```
P(correct) = 1 / (1 + e^-(θ - b))
```

where `θ` is the student's ability and `b` is the item's difficulty, both on
the same **logit** scale (roughly -3 to +3, centered on 0 = average).

**Read this equation as:** "the probability of a correct answer depends only
on the *gap* between how able you are and how hard the question is." If
θ = b (you're exactly as able as the question is hard), the equation gives
exactly 0.5 — a 50/50 coin flip, which is the intuitive definition of "this
question is at your level."

**Worked check:** if θ = 1.4 and b = 0.2 (you're clearly more able than this
question is hard):
```
P = 1 / (1 + e^-(1.4-0.2)) = 1 / (1 + e^-1.2) = 1 / (1 + 0.301) = 0.769
```
→ 77% chance you get it right. That matches intuition: a question below your
level, you're likely (but not guaranteed) to answer correctly.

This is implemented in `App\Services\Irt\RaschMath::probabilityCorrect()`.

## 2. Where do item difficulties (b) come from? — PROX calibration

You can't just assign difficulty by eyeballing a question. You calibrate it
from real response data: if 95% of students get an item right, it's easy
(low b); if 5% do, it's hard (high b). The exact algorithm used here is
**PROX** (the "Normal Approximation" method), from Wright & Stone's *Best
Test Design* (1979) — a classical, non-iterative, closed-form calibration
method (as opposed to full joint maximum likelihood, which needs many
iterations to converge).

**Step by step, with a toy example.** Say 3 students answer 3 items:

|        | I1 | I2 | I3 |
|--------|----|----|----|
| P1     | 1  | 1  | 0  |
| P2     | 1  | 0  | 0  |
| P3     | 0  | 0  | 0  |

**Step 1 — proportion correct → logit**, for every item and every person:
`logit(p) = ln(p / (1-p))`. (If p is exactly 0 or 1, add a tiny continuity
correction — `1/(2n)` — first, so you never take `ln(0)`.)

- Item I1: 2/3 correct → logit = ln(2) ≈ **0.693**
- Item I2: 1/3 correct → logit = ln(0.5) ≈ **-0.693**
- Item I3: 0/3 correct → corrected to 1/6 → logit = ln(0.2) ≈ **-1.609**

(Persons work out identically in this toy example, by symmetry.)

**Step 2 — expansion factor.** Raw logits from a small sample are more
compressed than the "true" underlying spread, so PROX corrects for this:
```
X = sqrt(1 + Var(item logits) / 2.89)
```
Variance of `[0.693, -0.693, -1.609]` ≈ 0.896, so `X = sqrt(1.310) ≈ 1.144`.

**Step 3 — center and scale.**
```
item_difficulty_i = X * (mean(person logits) - item_logit_i)
```
Mean person logit here ≈ -0.536, so:
- I1: `1.144 × (-0.536 - 0.693) = 1.144 × -1.229 ≈ -1.41`
- I2: `1.144 × (-0.536 - (-0.693)) = 1.144 × 0.157 ≈ 0.18`
- I3: `1.144 × (-0.536 - (-1.609)) = 1.144 × 1.073 ≈ 1.23`

**Result:** the item everyone got right (I1) gets the *lowest* difficulty
(-1.41); the item nobody got right (I3) gets the *highest* (1.23). That's
exactly the ordering you'd expect — the maths just makes it precise and puts
it on a scale usable for the next step.

This is implemented in `RaschMath::calibrateItems()`, run against real
response data by `App\Services\Irt\RaschCalibrationService` (triggered via
`php artisan irt:calibrate` or the admin Psychometrics page's "Recalibrate"
button).

**Why items need ~5+ responses before calibrating:** with only 1-2
responses, the proportion-correct estimate is too noisy to trust (get one
lucky guess right and a brand new item looks "easy"). Until an item has
enough data, it uses a **prior** difficulty derived from its authored level
(`RaschCalibrationService::priorDifficulty()`) — Level 1 questions start
assumed easy, Level 5 assumed hard — which gets *replaced* by the real
calibrated value once enough students have answered it.

## 3. Estimating a student's ability from their answers — MLE

Once items have known difficulties, a student's ability is estimated by
finding the θ that makes their *actual* pattern of right/wrong answers most
probable — this is **maximum likelihood estimation (MLE)**, solved
iteratively via **Newton-Raphson**:

```
θ_new = θ_old + [ Σ(u_i - P_i) ] / [ Σ P_i(1-P_i) ]
```

where `u_i` is 1 if correct/0 if not, and `P_i` is the model's predicted
probability at the current θ guess. You start at θ=0 and repeat until it
barely moves.

**Worked example.** A student answers 3 items with difficulties
`b = [-1.4, 0.2, 1.2]` (our calibrated I1/I2/I3 from above, rounded), getting
I1 and I2 right, I3 wrong (`u = [1, 1, 0]`).

*Iteration 1 (start θ=0):*
- P(I1) = 1/(1+e^1.4) = 0.802
- P(I2) = 1/(1+e^0.2) = 0.450
- P(I3) = 1/(1+e^-1.2)⁻¹... = 0.232

  (using `1/(1+e^-(θ-b))` for each)

- numerator = (1-0.802)+(1-0.450)+(0-0.232) = **0.516**
- denominator (information) = 0.802×0.198 + 0.450×0.550 + 0.232×0.768 = **0.584**
- Δ = 0.516/0.584 ≈ **0.884** → θ becomes 0.884

*Iteration 2 (θ=0.884):*
- P(I1)=0.908, P(I2)=0.665, P(I3)=0.422
- numerator ≈ 0.006, denominator ≈ 0.551, Δ ≈ 0.011 → θ ≈ **0.895**

Two iterations and it's already barely moving — Newton-Raphson converges
fast for this model. Final θ ≈ 0.90: this student is meaningfully above
average (θ=0), consistent with getting the two easier items right and
missing the hardest one.

**Standard error:** `SE = 1/sqrt(information)`. Here, with only 3 items,
`SE ≈ 1/sqrt(0.55) ≈ 1.35` — very imprecise. This is *exactly* why the
placement test uses 15-25 items, not 3: precision (a smaller SE) needs
enough evidence. This is implemented in `RaschMath::estimateAbility()`.

## 4. Choosing which question to ask next — adaptive item selection

A question's **Fisher information** at a given θ is `P(θ)×(1-P(θ))`, which
is mathematically maximized exactly when `b = θ` (the question is exactly
as hard as the student is able). So "pick the most informative next item"
and "pick the item closest in difficulty to my current ability estimate"
are the *same rule* for the Rasch model — that's what
`App\Services\Irt\AdaptiveItemSelectionService` does: after every answer,
re-estimate θ, then serve whichever unseen question (rotating through the 5
categories for content balance) has difficulty closest to the new θ.

The test stops once either 25 items have been asked, or (after at least 15,
to avoid stopping on a lucky/unlucky early streak) `SE(θ) ≤ 0.35` — both
are standard computerized-adaptive-testing (CAT) stopping rules.

## 5. From θ to an IQ number and a level

Two more small, honest conversions, both linear rescalings of the same θ —
no new modelling assumptions:

- **IQ score** = `100 + 15×θ`, clamped to [40, 160] — the conventional
  "deviation IQ" scale (mean 100, SD 15) used by modern IQ tests. Since θ is
  already approximately standard-normal after calibration, this is just
  relabeling the axis, exactly like converting Celsius to Fahrenheit.
- **Level (1-5)** = θ cut at -1.5, -0.5, 0.5, 1.5 — each level spans
  roughly one standard deviation, the same convention as stanine grouping.

## 6. How do we know the calibration/estimation code is actually correct?

Real usage data is still thin (a handful of students), which isn't enough
to *validate* an algorithm — you'd be checking it against almost nothing.
So `php artisan irt:validate-simulation` runs a **Monte Carlo parameter
recovery study** (a standard IRT validation method — see Harwell, Stone, Hsu
& Kirisci, 1996): it generates synthetic students and items with a *known*
true ability/difficulty, simulates their responses via the exact same Rasch
probability formula, then runs the exact same calibration/estimation code
the live app uses and checks how well it recovers the parameters it isn't
supposed to know.

Last run (500 simulated students, 60 items, seed=42):

| Metric | Value |
|---|---|
| Item difficulty recovery — Pearson r | 0.9913 |
| Item difficulty recovery — RMSE (logits) | 0.1649 |
| Person ability recovery — Pearson r | 0.9149 |
| Person ability recovery — RMSE (logits) | 0.4797 |

An `r` this close to 1.0 means the code correctly recovers the underlying
structure; the (deliberately) lower ability-recovery number reflects that
any *individual* test only has ~15-25 items, so single-test ability
estimates are inherently noisier than item calibration averaged over
hundreds of respondents — that's a property of the measurement, not a code
bug, and is exactly why the platform keeps refining θ from every session's
new response data rather than freezing it after one test.

## 7. Why "marginal reliability" instead of Cronbach's alpha?

Classical reliability (Cronbach's alpha) assumes every respondent answers
the *same fixed set* of items. Here, every student answers a different,
adaptively/randomly sampled subset — alpha's core assumption doesn't hold.
The IRT/CAT-appropriate analogue is **marginal reliability** (Green, Bock,
Humphreys, Linn & Reckase, 1984):

```
reliability = 1 - mean(SE(θ)²) / Var(θ across all students)
```

It answers the same underlying question ("how much of the variation between
students is real signal vs. measurement noise?") but correctly accounts for
every student having their own precision (SE), which classical alpha can't
express. This is what the admin Psychometrics page reports.

## References

- Rasch, G. (1960). *Probabilistic Models for Some Intelligence and Attainment Tests.*
- Wright, B.D. & Stone, M.H. (1979). *Best Test Design.* Chicago: MESA Press. (PROX calibration)
- Lord, F.M. (1980). *Applications of Item Response Theory to Practical Testing Problems.*
- Green, B.F., Bock, R.D., Humphreys, L.G., Linn, R.L. & Reckase, M.D. (1984). Technical guidelines for assessing computerized adaptive tests. *Journal of Educational Measurement*, 21(4).
- Harwell, M., Stone, C.A., Hsu, T.C. & Kirisci, L. (1996). Monte Carlo studies in item response theory. *Applied Psychological Measurement*, 20(2).
- Kingsbury, G.G. & Zara, A.R. (1989). Procedures for selecting items for computerized adaptive tests. *Applied Measurement in Education*, 2(4). (content-balanced item selection)
