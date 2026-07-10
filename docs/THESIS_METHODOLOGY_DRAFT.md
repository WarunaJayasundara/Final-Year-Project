# Methodology Chapter Draft — Adaptive Testing Engine

*Draft chapter section for CT/2020/074, "Web-Based Cognitive Training
Platform for IQ Development." Written to be adapted into your thesis's
Methodology/Design chapter — adjust section numbering, expand the
literature review paragraph with your own reading, and fill in the
Introduction/Related Work framing this assumes exists elsewhere in your
document.*

## 3.x Adaptive Testing Engine

### 3.x.1 Motivation

An initial implementation of the platform's placement and leveling logic
used fixed percentage-accuracy bands (e.g., 0-39% accuracy mapped to Level
1) to place and adjust a student's level. While functional, this approach
treats every question as equally difficult and has no statistical basis for
the chosen thresholds — two well-known limitations of classical, raw-score
based ability measurement (Hambleton, Swaminathan & Rogers, 1991). To give
the platform's central claim — an *adaptive* IQ-measurement instrument — a
genuine psychometric foundation, the leveling and placement logic was
redesigned around **Item Response Theory (IRT)**, specifically the
one-parameter logistic (Rasch) model (Rasch, 1960), and the placement test
was re-implemented as a true **computerized adaptive test (CAT)**.

### 3.x.2 The Rasch Model

The Rasch model expresses the probability that a respondent of ability θ
answers an item of difficulty *b* correctly as:

```
P(θ) = 1 / (1 + e^-(θ-b))
```

Both θ and *b* are estimated on a common logit scale. The model was chosen
over 2- or 3-parameter alternatives (which additionally estimate item
discrimination and guessing parameters) for two reasons appropriate to a
final-year-project timeline and dataset size: (a) it requires substantially
less response data per item to calibrate stably, and (b) its single-
parameter form makes item selection during adaptive delivery reduce to a
simple, interpretable rule (Section 3.x.4), which matters for a system that
must remain explainable in a viva and maintainable by future contributors.

### 3.x.3 Item Calibration (PROX)

Item difficulties are calibrated from accumulated response data using the
**PROX ("Normal Approximation") method** (Wright & Stone, 1979), a
closed-form joint calibration procedure that avoids the iterative
convergence requirements of full joint maximum likelihood estimation.
Proportion-correct statistics for each item and each respondent are
converted to logits, corrected for finite-sample compression via an
expansion factor, and centered against the complementary distribution
(items against mean respondent ability; respondents against mean item
difficulty). A continuity correction (`1/(2n)`) is applied to any item or
respondent with a 0% or 100% observed success rate, to avoid an undefined
logit.

Items with fewer than 5 observed responses retain a **prior** difficulty
derived from their authored level and difficulty-weight metadata rather
than an unstable data-driven estimate, and are automatically superseded by
the calibrated value once sufficient response data accumulates — a
cold-start design consistent with common practice in operational item
banking (Ban, Hanson, Wang, Yi & Harris, 2001).

### 3.x.4 Ability Estimation

A respondent's ability is estimated via maximum likelihood estimation
(MLE), solved with Newton-Raphson iteration:

```
θ_(k+1) = θ_k + [ Σ(u_i - P_i(θ_k)) ] / [ Σ P_i(θ_k)(1 - P_i(θ_k)) ]
```

where *u_i* is the observed response (0/1) to item *i*. The denominator is
the test information function at θ_k; its inverse square root gives the
standard error of the estimate, `SE(θ) = 1/√I(θ)`, used both as the
adaptive test's stopping criterion (Section 3.x.5) and as a
respondent-level precision indicator surfaced in the platform's analytics.

### 3.x.5 Adaptive Item Selection and Test Termination

The placement test is delivered as a genuine item-by-item CAT: after each
response, θ is re-estimated from the responses so far, and the next item is
selected as the unseen, active item whose difficulty is closest to the
updated θ estimate — equivalent to maximizing Fisher information at θ,
since `I(θ) = P(θ)(1-P(θ))` is maximized when `b = θ`. Item selection is
constrained to rotate across the platform's five diagnostic categories
(memory, logical reasoning, numerical ability, attention, spatial/pattern
recognition), a form of **content balancing** consistent with constrained
CAT designs described by Kingsbury & Zara (1989).

Testing terminates when either (a) a maximum of 25 items have been
administered, or (b) at least 15 items have been administered and
`SE(θ) ≤ 0.35` — a fixed-precision stopping rule standard in operational
CAT systems (Weiss, 1982).

Daily and practice sessions continue to use a fixed-size, pre-sampled
question set (for UX reasons: batch review, offline-friendly submission),
but the composition of that set and the resulting level/IQ update are now
driven by the same θ estimate, recomputed via MLE over the respondent's
complete placement-and-daily response history each time a session
completes — using all available evidence rather than only the most recent
session, as the earlier rolling-average heuristic did.

### 3.x.6 Deriving Level and IQ Score from θ

The platform's existing 5-level structure and its student-facing "estimated
IQ score" feature are both derived from θ via simple linear
transformations, so that no second model or parallel scoring system is
introduced:

- **Level (1-5):** θ is cut at -1.5, -0.5, 0.5 and 1.5 logits, corresponding
  approximately to one standard deviation per level under the
  post-calibration assumption that θ is approximately standard-normal.
- **IQ estimate:** `IQ = 100 + 15θ`, the conventional deviation-IQ scale
  (mean 100, SD 15) used by contemporary IQ instruments (e.g., Wechsler
  scales), clamped to [40, 160].

### 3.x.7 Validation

Because the platform's real usage volume is not yet sufficient to assess
calibration accuracy directly, the calibration and ability-estimation
implementation was validated independently via a **Monte Carlo parameter
recovery study** (Harwell, Stone, Hsu & Kirisci, 1996) — a standard IRT
validation methodology in which synthetic response data is generated from
respondents and items with *known* true parameters, and the implementation
under test is assessed by how accurately it recovers those known values
from the simulated responses alone.

500 synthetic respondents (θ ~ N(0,1)) and 60 synthetic items (b ~ N(0,1))
were generated, with each respondent answering a random ~50% subset of
items (simulating the sparse, incomplete response matrix characteristic of
real deployment) according to the Rasch probability model. Recovered item
difficulties were mean-equated against the true values prior to comparison,
since Rasch-model difficulty is identified only up to an additive constant
(Lord, 1980, ch. 2).

**Table 3.x — Monte Carlo parameter recovery results** (seed=42, reproducible via `php artisan irt:validate-simulation`)

| Metric | Value |
|---|---|
| Simulated respondents | 500 |
| Simulated items | 60 |
| Total simulated responses | 15,013 |
| Item difficulty recovery — Pearson *r* | 0.991 |
| Item difficulty recovery — RMSE (logits) | 0.165 |
| Person ability recovery — Pearson *r* | 0.915 |
| Person ability recovery — RMSE (logits) | 0.480 |

Item-parameter recovery was near-perfect (*r* = 0.991), consistent with
calibration accuracy benefiting from pooled information across all 500
simulated respondents per item. Ability recovery, while still strong
(*r* = 0.915), showed higher error, attributable to each simulated
respondent answering only ~30 of the 60 items — the same sparsity, and the
same corresponding precision limit, that motivates the platform's
multi-item (15-25 item) placement test length and its policy of
re-estimating θ from the full response history rather than a single
session.

### 3.x.8 Reliability Reporting

Because respondents in an adaptive/randomly-sampled-item system do not
share a common fixed item set, classical internal-consistency reliability
(Cronbach's alpha) is not an appropriate reliability index here, as its
derivation assumes a fixed test form (Cronbach, 1951). Instead, the platform
reports **marginal reliability** (Green, Bock, Humphreys, Linn & Reckase,
1984), the standard IRT/CAT analogue:

```
reliability = 1 - mean(SE(θ)²) / Var(θ)
```

computed across all respondents with an ability estimate, and surfaced
alongside item difficulty distributions and point-biserial item
discrimination indices on an administrator-facing psychometrics dashboard,
providing an ongoing, empirical check on question-bank quality as the
platform accumulates real usage data.

## 3.y AI Exam Readiness Prediction (Machine Learning)

### 3.y.1 Motivation

The IRT/CAT engine (Section 3.x) measures ability precisely but does not, by
itself, answer the practical question a student preparing for a competitive
examination actually asks: *am I ready, and if not, what specifically should
I work on?* This module adds a supervised machine-learning layer that
predicts a 4-class exam-readiness outcome (Ready / Almost Ready / Needs
Improvement / High Risk) from a 24-feature behavioural and ability profile,
with an explainable-AI (SHAP) layer surfacing the specific reasons behind
each prediction - directly addressing the predictive-analytics and
explainable-AI novelty criteria expected of an undergraduate research
project in this space (Romero & Ventura, 2010; Siemens & Baker, 2012).

### 3.y.2 Feature Engineering

Twenty-one of the twenty-four input features are derived directly from
existing platform data (the IRT ability estimate and its rescalings,
per-category accuracy, session history, game performance, and derived
behavioural statistics such as response-time and score-consistency trends).
Three features - daily study hours, self-rated motivation, and attendance -
have no objective instrumentation on the platform and are captured via a
short self-report check-in rather than fabricated, a deliberate design
choice favouring honesty over feature-set completeness.

### 3.y.3 Synthetic Training Data

Consistent with the validation approach used for the IRT engine (Section
3.x.7), the supervised model is trained on a synthetic dataset (80,000
records) rather than real historical outcomes, since the platform's actual
usage volume cannot yet support model training. Feature values are sampled
from documented distributions parameterized by latent traits (ability,
motivation, consistency); the ground-truth label is derived from a
documented, weighted composite heuristic over the same features (including
an urgency interaction between time-to-exam and practice consistency) plus
injected Gaussian noise, then discretized into the four readiness classes.
This is a transparent, reproducible substitute for real labels, with an
explicit path to retraining on real data once available.

### 3.y.4 Model Selection

Three tree-ensemble classifiers - Random Forest (Breiman, 2001), Gradient
Boosting (Friedman, 2001), and XGBoost (Chen & Guestrin, 2016) - were
trained on an 80/20 stratified train/test split and compared on accuracy,
macro-precision/recall/F1, and macro one-vs-rest ROC-AUC. XGBoost was
selected automatically by highest macro-F1 (0.616), with a macro ROC-AUC of
0.856 indicating strong class separability despite an inherently noisy
4-way decision boundary (readiness is a continuum; most residual error is
between adjacent classes, e.g. "Almost Ready" vs. "Ready", not between the
extremes).

**Table 3.y — Model comparison** (80,000 synthetic records, 80/20 split, seed=42)

| Model | Accuracy | Precision (macro) | Recall (macro) | F1 (macro) | ROC-AUC (macro, OvR) |
|---|---|---|---|---|---|
| Random Forest | 0.611 | 0.648 | 0.572 | 0.597 | 0.850 |
| Gradient Boosting | 0.619 | 0.645 | 0.592 | 0.612 | 0.854 |
| **XGBoost (selected)** | **0.622** | **0.650** | **0.596** | **0.616** | **0.856** |

### 3.y.5 Explainability

Per-prediction feature attributions are computed via SHAP (SHapley Additive
exPlanations; Lundberg & Lee, 2017) `TreeExplainer` against the "ready"
class output. The five highest-magnitude contributing features per
prediction are surfaced to the student as plain-language reasons (e.g.
"Excellent Logical Reasoning", "Low practice frequency"), directly satisfying
the explainable-AI requirement rather than exposing a bare probability.
Global feature importance (mean |SHAP value| across the held-out set) ranks
`avg_test_score`, `theta`, `practice_streak`, and `consistency_score` as the
four most influential features, consistent with domain expectations and
serving as a sanity check on model validity.

### 3.y.6 Deployment Architecture

The trained model is served by a standalone FastAPI microservice
(`ml-service/`), called by Laravel over HTTP via `ReadinessPredictionService`
- architecturally identical to the existing Gemini AI-feedback integration's
swappable-service pattern. This keeps the Laravel application free of a
heavy ML runtime dependency while still allowing genuine gradient-boosted
tree inference and SHAP explanation, and every prediction is persisted with
its model version for auditability and future retraining comparison.

## References

- Ban, J.C., Hanson, B.A., Wang, T., Yi, Q. & Harris, D.J. (2001). A comparative study of on-line pretest item-calibration/scaling methods in computerized adaptive testing. *Journal of Educational Measurement*, 38(3).
- Breiman, L. (2001). Random Forests. *Machine Learning*, 45(1).
- Chen, T. & Guestrin, C. (2016). XGBoost: A Scalable Tree Boosting System. *KDD*.
- Cronbach, L.J. (1951). Coefficient alpha and the internal structure of tests. *Psychometrika*, 16(3).
- Friedman, J.H. (2001). Greedy Function Approximation: A Gradient Boosting Machine. *Annals of Statistics*.
- Green, B.F., Bock, R.D., Humphreys, L.G., Linn, R.L. & Reckase, M.D. (1984). Technical guidelines for assessing computerized adaptive tests. *Journal of Educational Measurement*, 21(4).
- Hambleton, R.K., Swaminathan, H. & Rogers, H.J. (1991). *Fundamentals of Item Response Theory.* Sage Publications.
- Harwell, M., Stone, C.A., Hsu, T.C. & Kirisci, L. (1996). Monte Carlo studies in item response theory. *Applied Psychological Measurement*, 20(2).
- Kingsbury, G.G. & Zara, A.R. (1989). Procedures for selecting items for computerized adaptive tests. *Applied Measurement in Education*, 2(4).
- Lord, F.M. (1980). *Applications of Item Response Theory to Practical Testing Problems.* Lawrence Erlbaum Associates.
- Lundberg, S.M. & Lee, S.I. (2017). A Unified Approach to Interpreting Model Predictions. *NeurIPS*.
- Rasch, G. (1960). *Probabilistic Models for Some Intelligence and Attainment Tests.* Danish Institute for Educational Research.
- Romero, C. & Ventura, S. (2010). Educational Data Mining: A Review of the State of the Art. *IEEE Transactions on Systems, Man, and Cybernetics*, 40(6).
- Siemens, G. & Baker, R.S.J.d. (2012). Learning Analytics and Educational Data Mining: Towards Communication and Collaboration. *LAK '12*.
- Weiss, D.J. (1982). Improving measurement quality and efficiency with adaptive testing. *Applied Psychological Measurement*, 6(4).
- Wright, B.D. & Stone, M.H. (1979). *Best Test Design.* MESA Press.
