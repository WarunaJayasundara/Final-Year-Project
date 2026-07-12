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
Improvement / High Risk), three additional real-data-grounded outputs
(risk of dropping practice, predicted next assessment score, predicted
score change), and an explainable-AI layer (SHAP, cross-checked with LIME
and permutation importance) surfacing the specific reasons behind each
prediction in plain, trend-aware English - directly addressing the
predictive-analytics and explainable-AI novelty criteria expected of an
undergraduate research project in this space (Romero & Ventura, 2010;
Siemens & Baker, 2012). A full standalone methodology document
(`docs/ML_RESEARCH_METHODOLOGY.md`) backs every claim in this section with
exact numbers, tables, and machine-readable source-of-truth JSON reports;
this section is a condensed thesis-chapter treatment of the same material.

### 3.y.2 Dataset Selection and Hybrid Strategy

A first version of this module (documented in the now-superseded parts of
`ML_EXAM_READINESS_EXPLAINED.md`) trained exclusively on synthetic data,
since the platform's own usage volume cannot support model training and no
public dataset matches its exact feature schema. This is a legitimate
starting point but caps the work's research contribution: a reviewer can
reasonably ask how a purely-synthetic-label model generalizes to real
students, and no evidence could be offered.

This iteration integrates two real, publicly available, citable educational
datasets, selected against the brief's own criteria (learning behaviour,
engagement, assessment performance, cognitive indicators, study habits,
exam outcomes) plus two practical constraints (freely downloadable without
a gated request process; processable on a single development machine):

- **OULAD** (Kuzilek, Hlosta & Zdrahal, 2017) - 32,593 real students, 7
  university course modules, a day-level virtual-learning-environment
  clickstream (10.6M events), and dated real assessment scores. CC BY 4.0.
- **UCI Student Performance** (Cortez & Silva, 2008) - 1,044 secondary-
  school students with three sequential real grades and explicit
  study-habit fields. CC BY 4.0.

Larger or more granular alternatives (EdNet, ASSISTments, KDD Cup
Educational datasets) were considered and excluded, respectively, for
infeasible scale (>100GB), gated per-dataset access-request processes this
project has no institutional channel to complete, and a step-level
interaction-log shape that does not map cleanly onto this platform's
session/category structure - documented in full in the companion
methodology document's dataset-selection section, since documenting
exclusions is as important to a defensible methodology as documenting
inclusions.

Every feature is classified as either **real-measured** (derived directly
from a genuine measurement - e.g. `avg_test_score` from real submitted
assessment scores, `practice_streak` from the longest real consecutive
active-day run in the VLE clickstream) or **platform-only** (no public
dataset measures IRT ability, MindRise's specific cognitive categories, or
its mini-games; these are generated for real rows from a *pseudo-theta
derived from that student's own real assessment performance*, passed
through the same structural equations the synthetic generator trusts, so a
real row and a synthetic row sharing the same underlying ability are
structurally comparable). The final hybrid training set combines
32,593 real OULAD rows, 1,044 real UCI rows, and 40,000 synthetic rows
whose composite-score heuristic weights are **empirically calibrated**
against a multinomial logistic regression fit on the real OULAD outcome
data (replacing hand-picked domain-expert weights with observed
coefficients wherever a real analogue exists) - 73,637 rows total, 45.7%
grounded in real student outcomes, enforced by a hard 40% floor in the
assembly pipeline so this share cannot silently regress.

### 3.y.3 Advanced Feature Engineering

Eighteen additional behavioural features were engineered beyond the
original 24, each with an exact mathematical specification (full table:
`ML_RESEARCH_METHODOLOGY.md` Section 4). Representative examples: learning
velocity (`LV = (θ_now - θ_(t-4wk)) / 4`, the rate of IRT-ability change per
week), consistency index (`CI = 100(1 - CV)`, a scale-invariant
coefficient-of-variation measure), fatigue score (within-session accuracy
decay: first-half minus second-half accuracy, averaged over recent
sessions), error recovery rate (`P(correct at i+1 | incorrect at i)`, a
standard learning-analytics "bounce-back" metric), and category mastery
(`100 × P(correct | θ_c, item difficulty)` via the same Rasch model as
Section 3.x, reusing the existing IRT engine rather than introducing a
second ability metric). Real-derivable features are computed genuinely from
OULAD's dated activity/assessment tables at training time; platform-only
features are generated from the same latent structural model as the
original 24 for training data, and computed for real from MindRise's own
`session_answers`/`game_scores`/`checkins` tables at live inference time.

### 3.y.4 Multi-Model Comparison and Hyperparameter Optimization

Nine candidate model families were screened (5-fold stratified
cross-validation, macro-F1): Random Forest, Extra Trees, Gradient Boosting,
AdaBoost, XGBoost, LightGBM, CatBoost, SVM, and a small MLP. **TabNet**
(Arik & Pfister, 2021) was deliberately excluded: it is designed to be
competitive with gradient-boosted trees specifically on datasets an order
of magnitude larger than this project's (the original paper's own
benchmarks use 100K-10M+ rows), offers no demonstrated advantage at this
scale, and would add a full PyTorch dependency to an otherwise lightweight
inference service - a documented scope decision, not an oversight.

The top-3 screened candidates were tuned via **Optuna** (Tree-structured
Parzen Estimator, Bayesian optimization) under a **nested cross-validation**
scheme: an outer 3-fold split provides an honest generalization estimate
(tuning and evaluating on the same fold would overstate performance), while
an inner 3-fold/12-trial Optuna search selects hyperparameters within each
outer training fold; a final Optuna pass on the complete training set
produces the parameters actually deployed. Grid and plain random search
were not used as the primary method, since the mixed continuous/integer
hyperparameter space (e.g. a log-scale learning rate) makes an
exhaustive grid prohibitively coarse-or-large, and Bayesian optimization
exploits previously-evaluated trials more efficiently than uniform random
sampling.

**Table 3.y.4 — Model comparison and final selection**

*(Populated from `ml-service/models/model_comparison_report.json` - see
`ML_RESEARCH_METHODOLOGY.md` Section 5.5/6.3 for the exact screening,
nested-CV, and default-vs-optimized numbers from the run backing this
document's current version.)*

### 3.y.5 Comprehensive Evaluation

Beyond accuracy and macro-F1, the deployed model is evaluated on: precision/
recall (macro and weighted), ROC-AUC and PR-AUC (one-vs-rest macro - PR-AUC
specifically because the "ready" class is a minority class, ~10% of the
dataset), balanced accuracy, Matthews correlation coefficient, Cohen's
kappa, log loss, Brier score, and per-class calibration curves (is a
predicted 70% confidence actually right ~70% of the time). Generalization
is additionally estimated via 10-fold stratified cross-validation, repeated
5×3-fold cross-validation (reducing the CV estimate's own variance), and
1,000-resample bootstrap 95% confidence intervals on accuracy and macro-F1.
A learning curve (train vs. cross-validation score across increasing
training-set sizes) provides an explicit overfitting/underfitting
diagnosis, and a validation curve over the deployed model's most impactful
hyperparameter shows the bias-variance tradeoff directly. Performance is
additionally broken down by `data_source` (real OULAD / real UCI /
synthetic-calibrated) to check the model is not quietly overfitting to the
easier synthetic signal at the expense of real-student generalization.

### 3.y.6 Explainable AI

Per-prediction feature attributions are computed via SHAP (Lundberg & Lee,
2017) against the "ready" class output, cross-checked by three independent
methods so agreement across mechanistically-different techniques provides
stronger evidence of genuine model behaviour than any single method alone:
**LIME** (Ribeiro, Singh & Guestrin, 2016 - a local linear surrogate fit
around a perturbed instance neighbourhood), **permutation importance**
(Breiman, 2001 - a fully model-agnostic measure), and **partial
dependence** (showing the shape, not just the magnitude, of each top
feature's marginal effect). SHAP interaction values additionally surface
genuine feature interactions (e.g. low practice volume mattering more when
little exam time remains) beyond independent main effects.

Predictions are additionally translated into a **trend-aware plain-English
explanation**: `ReadinessPredictionService` supplies the student's previous
prediction's feature snapshot alongside the current request, and the
inference service computes the percent change on each top-ranked SHAP
feature to produce sentences of the form *"Your readiness estimate changed
because your weekly practice volume dropped by 45%, ..."* rather than a
static, non-comparative explanation - directly satisfying the brief's
worked example.

### 3.y.7 Multi-Output Prediction

Beyond the readiness classification, three additional outputs are trained
on **genuine, temporally non-leaky real ground truth**: for each (student,
course module) in OULAD, the first half of real activity/assessment records
become the input features, and something that only happens in the second
half becomes the target - risk of dropping practice (zero second-half VLE
activity, a binary classifier), predicted next assessment score, and
predicted score change (both regressors). This avoids target leakage that
a same-summary features-and-target split would introduce, at the cost of
training these three outputs on OULAD alone (UCI's three static grades
provide no defensible way to carve out a "first half" of behaviour distinct
from the target).

Two further outputs named in the original brief - *recommended daily study
hours* and *most effective learning strategy* - are deliberately **not**
built as supervised predictions. Neither has any dataset (real or
MindRise's own) recording what the optimal value would have been for a
given student, and the latter specifically would require interventional or
causal data (the same student's outcome under strategy A versus strategy B)
that no observational dataset can provide - an observed correlation between
"students who used strategy A did better" and strategy A *causing* that
outcome is a textbook causal-inference confound. Both are instead delivered
as transparent rule-based recommendations from the existing
`StudyPlanService`, now informed by this module's real predictions as an
input signal, and explicitly not framed as ML outputs they are not.

### 3.y.8 Continual Learning

Every training run is versioned (`ml-service/model_registry.py`): full
artifacts are archived per version with a SHA-256 hash of the training data
snapshot, and a **champion-versus-challenger promotion gate**
(`retrain.py`) only replaces the live deployed model if a freshly retrained
challenger beats it on the same held-out gating metric by a documented
margin - never automatically deploying a worse or negligibly-different
model. A complete, append-only experiment history is kept in
`models/registry.json`. A fully automatic, continuously-scheduled
production MLOps pipeline (drift-detection dashboards, automatic real-usage
retraining triggers) was scoped out as disproportionate infrastructure for
a single-VM student project - documented as a deployment-configuration note
rather than built, consistent with this methodology's practice of
distinguishing what is mechanically implemented from what is intentionally
out of scope.

### 3.y.9 Threats to Validity, Bias, and Limitations

The largest remaining validity threat is construct validity of the label:
even with the hybrid dataset, only 45.7% of training rows carry a genuine
real-world outcome, and that outcome (a UK distance-learning course result)
is itself a proxy for, not a direct measurement of, the Sri Lankan
government-competitive-exam readiness this platform actually targets - a
population-mismatch threat to external validity that this iteration
narrows but does not close. A real-data bias analysis (using OULAD's
demographic fields, which are excluded from the model's own feature vector
by design - a protected characteristic must never be a direct model input,
even one correlated with a real disparity the source data reflects) finds
a measurable outcome gap by disability status (7.0% vs. 9.5% reaching the
top outcome band) and by socioeconomic deprivation tercile (6.7% vs. 12.2%)
in the real OULAD population, reported transparently rather than adjusted
away, since it reflects a property of the real-world data source, not an
artifact introduced by this model. Full discussion of these and further
limitations (platform-only feature approximation, modest multi-output
regression R² of 0.29-0.32, the fixed `days_until_exam` value for real
rows, and others) is in `ML_RESEARCH_METHODOLOGY.md` Section 12.

### 3.y.10 Deployment Architecture

The trained model is served by a standalone FastAPI microservice
(`ml-service/`), called by Laravel over HTTP via `ReadinessPredictionService`
- architecturally identical to the existing Gemini AI-feedback integration's
swappable-service pattern, and unchanged by this upgrade despite the
substantially larger feature vector and model set behind it. This keeps the
Laravel application free of a heavy ML runtime dependency while still
allowing genuine gradient-boosted tree inference, SHAP/LIME explanation,
and multi-output regression, and every prediction is persisted with its
model version, plain-English explanation, and (where available) the three
multi-output predictions for auditability and future retraining comparison.

## References

- Akiba, T., Sano, S., Yanase, T., Ohta, T. & Koyama, M. (2019). Optuna: A Next-generation Hyperparameter Optimization Framework. *KDD*.
- Arik, S.O. & Pfister, T. (2021). TabNet: Attentive Interpretable Tabular Learning. *AAAI*.
- Ban, J.C., Hanson, B.A., Wang, T., Yi, Q. & Harris, D.J. (2001). A comparative study of on-line pretest item-calibration/scaling methods in computerized adaptive testing. *Journal of Educational Measurement*, 38(3).
- Breiman, L. (2001). Random Forests. *Machine Learning*, 45(1).
- Chen, T. & Guestrin, C. (2016). XGBoost: A Scalable Tree Boosting System. *KDD*.
- Cortez, P. & Silva, A. (2008). Using Data Mining to Predict Secondary School Student Performance. *Proceedings of 5th FUBUTEC*.
- Cronbach, L.J. (1951). Coefficient alpha and the internal structure of tests. *Psychometrika*, 16(3).
- Friedman, J.H. (2001). Greedy Function Approximation: A Gradient Boosting Machine. *Annals of Statistics*.
- Green, B.F., Bock, R.D., Humphreys, L.G., Linn, R.L. & Reckase, M.D. (1984). Technical guidelines for assessing computerized adaptive tests. *Journal of Educational Measurement*, 21(4).
- Hambleton, R.K., Swaminathan, H. & Rogers, H.J. (1991). *Fundamentals of Item Response Theory.* Sage Publications.
- Harwell, M., Stone, C.A., Hsu, T.C. & Kirisci, L. (1996). Monte Carlo studies in item response theory. *Applied Psychological Measurement*, 20(2).
- Ke, G., Meng, Q., Finley, T., Wang, T., Chen, W., Ma, W., Ye, Q. & Liu, T.Y. (2017). LightGBM: A Highly Efficient Gradient Boosting Decision Tree. *NeurIPS*.
- Kingsbury, G.G. & Zara, A.R. (1989). Procedures for selecting items for computerized adaptive tests. *Applied Measurement in Education*, 2(4).
- Kuzilek, J., Hlosta, M. & Zdrahal, Z. (2017). Open University Learning Analytics dataset. *Scientific Data*, 4, 170171.
- Lord, F.M. (1980). *Applications of Item Response Theory to Practical Testing Problems.* Lawrence Erlbaum Associates.
- Lundberg, S.M. & Lee, S.I. (2017). A Unified Approach to Interpreting Model Predictions. *NeurIPS*.
- Prokhorenkova, L., Gusev, G., Vorobev, A., Dorogush, A.V. & Gulin, A. (2018). CatBoost: unbiased boosting with categorical features. *NeurIPS*.
- Rasch, G. (1960). *Probabilistic Models for Some Intelligence and Attainment Tests.* Danish Institute for Educational Research.
- Ribeiro, M.T., Singh, S. & Guestrin, C. (2016). "Why Should I Trust You?": Explaining the Predictions of Any Classifier. *KDD*.
- Romero, C. & Ventura, S. (2010). Educational Data Mining: A Review of the State of the Art. *IEEE Transactions on Systems, Man, and Cybernetics*, 40(6).
- Siemens, G. & Baker, R.S.J.d. (2012). Learning Analytics and Educational Data Mining: Towards Communication and Collaboration. *LAK '12*.
- Weiss, D.J. (1982). Improving measurement quality and efficiency with adaptive testing. *Applied Psychological Measurement*, 6(4).
- Wright, B.D. & Stone, M.H. (1979). *Best Test Design.* MESA Press.
