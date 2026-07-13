# HelaIQ — Master Context Pack for Thesis Writing

**Purpose of this document**: paste this whole file into a fresh Claude conversation
when you want help drafting/editing thesis chapters. It contains everything about
the project — what it is, why it was built this way, what was actually measured,
and what its honest limitations are — so Claude can write with you without
re-exploring the codebase. Deployment/hosting/infrastructure details are
deliberately excluded; this is about the system's design, methodology, and results.

---

## 1. Project Identity

**HelaIQ** — "An AI-Powered Cognitive Training Platform for IQ Development in Sri
Lanka." Final-year project, CT/2020/074, W.R. Jayasundara, University of Kelaniya.

**Core idea**: an adaptive cognitive-training web platform for Sri Lankan
government-exam candidates (target demographic: 20–30 year olds). The loop is:
adaptive IQ placement test → daily practice tuned to weak areas → progress
tracking → ML-based exam-readiness prediction → government-exam preparation
(mock exams, study plans).

**Origin of scope**: the project was originally narrower; a supervisor review
that the initial scope "lacked novelty" led to a large, explicit phased
expansion (IRT/adaptive testing engine, ML readiness prediction with real+
synthetic hybrid training data, a 6,500+ question competitive-exam bank,
gamification, bilingual EN/SI support, AI-assisted content generation with a
human-review gate). All of that expansion is now built and working.

**A note on academic honesty, since it shapes every section below**: this
project's own working convention (documented in its engineering context file)
is "honest scope-cuts over overclaiming" — every deliberate simplification has
a stated one-paragraph rationale, and negative/inconclusive results are
reported as such rather than hidden. This master document follows the same
convention. Do not let Claude (or yourself) round up "0.68 macro-F1" to
"high accuracy," or claim something is "AI-powered" where it's actually a
rule-based heuristic — the distinction is a real part of this project's
contribution (see §7).

---

## 2. Technology Stack (for a "System Design" chapter)

| Layer | Stack | Why |
|---|---|---|
| Backend | Laravel 9.19, PHP 8.0.11 | Mature MVC framework, strong ORM (Eloquent) for the relational schema an adaptive-testing platform needs |
| Frontend | React 19, TypeScript, Vite, Tailwind 4, shadcn/ui (Radix primitives), TanStack Query v5, React Router v7, i18next, Recharts | Type-safe SPA, component-driven UI, first-class bilingual support via i18next |
| Database | MySQL | Relational integrity for IRT response matrices, session history, gamification ledgers |
| ML microservice | Python 3.11, scikit-learn, XGBoost, LightGBM, CatBoost, Optuna, SHAP, LIME, FastAPI | Laravel/PHP has no first-class gradient-boosting or explainability tooling, so ML runs as a separate HTTP microservice rather than in-process |
| Auth | Laravel Sanctum (SPA cookie session) + Google OAuth (students) + separate email/password (admins) | Standard SPA session auth; role separation keeps admin auth independent of the student-only OAuth flow |

---

## 3. System Architecture

### 3.1 High-level shape
A Laravel REST API backend, a React SPA frontend, and a standalone FastAPI ML
microservice that the backend calls over HTTP (never imported in-process).
This three-tier separation exists specifically because the ML stack (Python's
scientific computing ecosystem) has no equivalent in PHP — the swappable-
service pattern (below) is what makes this boundary safe to cross.

### 3.2 The swappable-service pattern (a recurring, load-bearing architectural
decision worth its own paragraph in a thesis)
Every external/AI dependency (AI feedback generation, AI question generation,
AI coaching, ML readiness prediction) is built as: an interface (`Contract`),
a zero-config **Mock** implementation that produces real, non-fabricated
output without needing any API key, and a real implementation swapped in via
config. This means:
- The system is fully demonstrable and testable without any paid API key.
- The Mock implementations are not placeholders — they compute genuine
  deterministic output (e.g. the Mock question generator solves its own
  generated word problems; the Mock AI coach reads real student performance
  data and writes real templated-but-data-driven encouragement).
- The one exception (ML readiness prediction) uses an HTTP microservice swap
  instead of an interface swap, because gradient-boosting models don't have
  a lightweight in-PHP equivalent to mock.

**Why this matters for the thesis**: it's the answer to "how did you evaluate
an AI-driven system without a production LLM budget" — the Mock
implementations aren't test doubles that get thrown away, they're a real,
permanent part of the shipped system's design.

### 3.3 Core domain tables (for a data-model section)
- `users` (flat RBAC: `super_admin | admin | user`), `categories` (5 fixed
  cognitive-skill categories), `iq_levels` (5 levels), `questions` (6,500+
  active rows, with IRT calibration fields, difficulty metadata, bilingual
  text, image-generation metadata), `test_sessions` / `session_answers`
  (every response, with response-time capture), `user_progress_snapshots`,
  `exam_profiles`, `exam_readiness_predictions` (one row per ML prediction
  run — a history table, never overwritten), gamification tables (`badges`,
  `xp_ledger`, `mission_claims`), `ai_generated_questions` (draft-review-
  promote staging table, kept separate from live `questions`).

---

## 4. Adaptive Testing Engine (IRT/Rasch) — likely its own thesis chapter

**Model**: 1-parameter logistic (Rasch) IRT model. Item difficulty and person
ability (theta) live on the same latent logit scale.

**Calibration**: PROX (Normal Approximation) joint calibration — a
closed-form, single-pass algorithm (Wright & Stone, 1979) rather than
iterative joint maximum-likelihood estimation, chosen because it handles
sparse response matrices (each student only ever answers a sampled subset of
the bank, never the whole thing) without needing iteration to converge.

**Ability estimation**: Maximum-likelihood (Newton-Raphson), re-run after
every answer during the adaptive placement test, and after every completed
daily session using the student's full response history. Theta is clamped to
±4.5 logits to prevent runaway estimates on small samples; the standard
error is reported alongside theta so downstream consumers can judge
estimate reliability, not just the point estimate.

**Adaptive item selection (CAT)**: maximum-information selection — at each
step, the engine picks the unanswered item that maximizes Fisher information
at the student's current theta estimate (i.e. the item they're closest to a
50/50 chance on), which is the standard efficient CAT strategy.

**Stopping rule**: the placement test stops when either (a) a maximum item
count is reached, or (b) — only after a *minimum* item count, to avoid
stopping early on a lucky/unlucky streak — the standard error drops below a
fixed precision threshold. Concretely: minimum 15 items, maximum 25, SE
threshold 0.35 logits. This dual criterion is a standard CAT termination
rule and is what prevents a single early answer from ever producing a
"finished" (but statistically meaningless) ability estimate.

**Validation methodology**: a Monte Carlo parameter-recovery study — generate
synthetic students/items with known ground-truth theta/difficulty, run them
through the exact same calibration code, and measure how well the recovered
estimates correlate with the known truth. Result: **r=0.991 item-difficulty
recovery, r=0.915 person-ability recovery**. This is the standard way to
validate an IRT implementation when no external "gold standard" test exists
to compare against, and it validates the *code*, not a re-implementation of
it (the same `RaschMath` class is used for both the simulation and real
production calibration).

**IQ scaling**: theta is linearly rescaled to a conventional deviation-IQ
scale via `IQ = 100 + 15·theta` (mean 100, SD 15 — the standard Wechsler-
style convention), since PROX-calibrated theta is already approximately
standard-normal, so this is a rescaling, not a second model. Display bounds
are clamped separately to [40, 160] from the underlying theta's ±4.5-logit
clamp — an important distinction for a thesis: the *raw estimate* and the
*display bound* are two different, independently-justified decisions, not
the same number reused twice.

**Edge-case behaviour** (worth citing as a validation table in a results
chapter): all-correct and all-incorrect responses correctly saturate at the
theta/IQ clamp bounds rather than diverging; a single-item estimate produces
a huge, correctly-computed standard error (≈9.6) signalling unreliability —
but the CAT stopping rule's 15-item minimum means a real student can never
actually finish a placement test on one answer, so this is a property of the
underlying math, not a reachable failure mode in the live product.

---

## 5. Question Bank — content-generation methodology

**Scale**: 6,500+ active competitive-exam-grade questions across 5 cognitive
categories, replacing an earlier, much smaller primary-school-level bank
(old rows kept `is_active=false`, never deleted, to preserve response-history
foreign-key integrity — a data-management decision worth a sentence in a
methodology chapter: *why* you don't delete historical content in an
adaptive-testing system).

**Generation methodology — the load-bearing claim for a "how were 6,500
questions produced without hand-authoring each one" section**: every
question is produced by a deterministic PHP generator using a **"generate
forward, solve backward"** pattern — the generator picks random parameters
first, computes the mathematically/logically correct answer from those exact
parameters using a real solver, and only then constructs the question text
and distractors around that guaranteed-correct answer. This is the opposite
of writing a question and hoping the stored answer is right. It's what
allows the question bank to scale to thousands of rows while remaining
independently re-verifiable (see §9's validation results).

**Archetypes span**: arithmetic, percentages, ratios, time-and-work,
speed-and-distance, number series, data interpretation, blood relations,
direction sense, coding-decoding, calendar/clock reasoning, seating
arrangement (including multi-constraint variants solved via brute-force
uniqueness verification over all valid permutations, so an ambiguous puzzle
is structurally impossible to generate), statement-sufficiency critical
reasoning, and a visual-IQ layer (matrix reasoning, rotation, mirror, paper
folding, cube nets, counting, boolean/Venn overlays, chart interpretation).

**Visual questions**: rendered as generated SVG, not stock images. Each
visual archetype's correct answer is computed by the *same* function that
renders the image (e.g. a real geometric rotation/reflection transform, a
real combinatorial set operation) — never asserted independently — which is
the property that makes "this isn't just randomly generated images called
IQ questions" a defensible claim rather than an assertion.

**Difficulty**: a 5-level system, explicitly re-anchored during this
project's history after a real bug was found where the difficulty-weight
formula only produced 3 distinct values across 5 levels (fixed to track
level directly, 1–5). Difficulty is defined by cognitive complexity
(multi-step reasoning, constraint density) rather than superficial cues like
large numbers — an explicit design principle worth stating if a thesis
chapter discusses difficulty calibration.

**AI-assisted generation (separate from the deterministic bank above)**: a
draft → human-admin-review → publish pipeline, using the swappable-service
pattern (§3.2). Every AI-generated draft goes through two independent
duplicate-detection signals (Jaccard word-overlap + a TF-IDF-cosine
microservice check) before an admin ever sees it, and is never auto-
published.

---

## 6. Bilingual (English/Sinhala) Support

Full i18next-based localization, not machine translation bolted on
afterward. Two things worth a methodology paragraph:

1. **A hard, enforced content-integrity process for Sinhala text.** After a
   real Unicode-corruption incident (garbled glyphs from hand-typing Sinhala
   without verification), the project adopted a strict rule: never
   hand-compose novel Sinhala from memory. New Sinhala text must either (a)
   reuse already-verified vocabulary from a maintained corpus (1,398 words,
   built from all seeders + locale files and re-validated on every change),
   or (b) be verified as a real dictionary word and logged with a review
   comment before being added to an approved-word list. A validator script
   checks every Sinhala string for both forbidden/corrupted codepoints and
   unreviewed novel words.
2. **Structural semantic-equivalence checking** for bilingual questions
   (not a deep-NLP claim — explicitly documented as a heuristic): numeric-
   literal parity, option-count parity, and answer-key presence are checked
   between the English and Sinhala versions of every question, to catch
   cases where the two language versions might have drifted apart in
   meaning or answer.

This is a legitimate, citable piece of methodology for a thesis discussing
low-resource-language NLP/content-quality assurance, precisely because it's
honest about being a heuristic rather than overclaiming semantic
understanding.

---

## 7. ML Exam-Readiness Prediction — almost certainly its own thesis chapter

### 7.1 What it predicts
Primary: a 4-class exam-readiness label (`high_risk | needs_improvement |
almost_ready | ready`) plus a smoothed 0–100 readiness percentage.
Secondary (multi-output, real ground truth): probability of dropping
practice, predicted next-assessment score, predicted score change.

**Explicitly NOT built as ML predictions** (a deliberate, documented scope
decision, important for a thesis discussing validity): "recommended daily
study hours" and "most effective learning strategy" have no dataset that
provides valid ground truth for them, so they're delivered as rule-based
recommendations instead, explicitly not framed as ML outputs. This is a
genuine methodological contribution to cite: *knowing which outputs a
dataset cannot honestly support, and saying so, rather than shipping an
unfounded prediction.*

### 7.2 Feature engineering
43 features total: 24 core behavioural/performance features + 19 "advanced"
features with closed-form mathematical definitions (rolling average score,
weekly/monthly trend, learning velocity, knowledge-gain rate, consistency
index, fatigue score, retention score, engagement score, practice
intensity, error-recovery rate, per-category mastery, confidence trend,
reaction-speed trend, adaptive-learning gain, difficulty progression,
question-diversity score, time-management score, revision frequency). Three
features (study hours, motivation score, attendance percent) have no
platform instrumentation and are self-reported via daily check-ins — worth
flagging honestly in a limitations section, since self-report is a weaker
signal than measured behaviour.

The exact same 43-feature ordered list is implemented independently in PHP
(live feature extraction) and Python (training pipeline), and this
one-to-one contract was directly verified byte-for-byte across both
languages **and** the live-serving copy — a genuine engineering risk in any
system that trains a model in one language and serves it from another,
worth naming explicitly in a methodology chapter as a threat you tested for.

### 7.3 Training data — the hybrid real+synthetic strategy (a strong,
citable methodological contribution)
73,637 rows, **45.7% real-world data**:

| Source | Rows | Provenance |
|---|---|---|
| Real: OULAD | 32,593 | Open University Learning Analytics Dataset (Kuzilek et al., 2017, CC BY 4.0) — real students, real dated assessments, real day-level VLE clickstream |
| Real: UCI Student Performance | 1,044 | Cortez & Silva (2008), CC BY 4.0 |
| Synthetic (calibrated) | 40,000 | A composite-score heuristic generator, with weights for its most important features **empirically calibrated via logistic regression against real OULAD outcomes** — i.e. the synthetic portion isn't arbitrary, its generating parameters were fit to match real-world structure |

**Why hybrid, not pure real or pure synthetic**: no public dataset measures
this platform's actual constructs (IRT ability estimate, per-category
cognitive scores, game performance) — nothing public instruments those.
Real data supplies genuine outcome grounding; synthetic data supplies
population coverage for platform-only constructs, generated via the *same*
structural equations for both real-row-derived pseudo-features and pure
synthetic rows, so the two populations share a consistent generative model
rather than being stitched together arbitrarily.

**Excluded datasets, with reasons** (worth a sentence each in a related-work/
methodology section, since exclusion rationale is real methodology):
EdNet (>100GB, infeasible at this project's scale), ASSISTments/KDD Cup
(gated access-request process incompatible with the project timeline),
xAPI-Edu-Data (redundant with OULAD).

### 7.4 Model selection
9 candidate algorithms screened via 5-fold cross-validation (Random Forest,
Extra Trees, Gradient Boosting, AdaBoost, XGBoost, LightGBM, CatBoost, SVM,
MLP), top-3 tuned via Optuna Bayesian hyperparameter optimization under
nested 3-fold CV (nested CV specifically to avoid the optimistic bias of
tuning and evaluating on the same folds). **TabNet was deliberately
excluded** — documented rationale: designed for datasets far larger than
this project's scale, no demonstrated benefit at ~74K rows, and a heavy
PyTorch dependency not justified by any measured gain. This is exactly the
kind of exclusion decision worth defending explicitly in a thesis rather
than silently omitting.

**Selected model**: XGBoost. Screening macro-F1 ≈0.676–0.680 across the
gradient-boosting family (broadly comparable, XGBoost narrowly best);
Optuna tuning improved it marginally (+0.001–0.002) — worth reporting
honestly as a small, not a large, effect of hyperparameter search on this
dataset.

### 7.5 Evaluation (cite these as your actual reported model metrics — real
numbers from an executed evaluation run, not placeholders)
Accuracy 0.697, balanced accuracy 0.672, precision (macro) 0.697, recall
(macro) 0.672, **F1 macro 0.681**, F1 weighted 0.693, ROC-AUC (OVR macro)
0.906, PR-AUC (OVR macro) 0.748, Matthews correlation 0.575, Cohen's kappa
0.574, log loss 0.667, mean Brier score 0.102. The evaluation pipeline's
own diagnosis flags **overfitting** (train score notably exceeds CV score)
— report this in the thesis rather than omitting it; it's a legitimate
finding about model generalization, not a failure of the writeup.

**Performance by data source** (a genuinely interesting result for a
discussion section): real-UCI rows evaluate far better (accuracy 0.951)
than real-OULAD (0.724) or synthetic (0.667) — plausibly because the UCI
population is smaller/more homogeneous, or because its outcome variable is
cleaner. Worth discussing as a limitation/threat to generalization rather
than cherry-picking the best-performing subset.

### 7.6 Explainability
SHAP (TreeExplainer, primary global/local importances) + LIME (independent
local cross-check, *not* the same method, specifically to test whether two
different explanation techniques agree) + permutation importance
(model-agnostic) + partial dependence. Top global features by SHAP: average
test score, question completion rate, theta, wrong-answer percent, days
until exam, question-diversity score, practice streak, study hours — a
sensible, face-valid ranking worth citing directly.

**LIME/SHAP agreement was measured at only 36%** — report this honestly.
Low cross-method agreement on *local* (per-instance) explanations is a
real, known phenomenon in the XAI literature (different explanation methods
often disagree on individual predictions even when both are individually
valid), and is worth a paragraph in a discussion/limitations section rather
than being hidden.

Every live prediction includes the top-5 SHAP reasons plus a **trend-aware
plain-English explanation** — comparing the current prediction against the
student's own previous prediction, not just a static snapshot — which is
what makes the explanation genuinely personalized rather than a generic
disclaimer.

### 7.7 Bias & fairness audit
Real OULAD demographic fields (gender, disability, age band, deprivation
tercile) were analyzed **offline only** — kept entirely out of the model's
feature vector by design (fairness-by-design, not fairness-by-post-hoc-
correction). Findings, reported transparently as a property of the real
underlying UK population data rather than adjusted away: near gender parity
(9.5% vs 9.1% "ready"), a disability gap (7.0% vs 9.5%), and a larger
deprivation-tercile gap (most-deprived 6.7% vs least-deprived 12.2%
"ready"). **Do not claim this fairness analysis is representative of Sri
Lankan students** — it's a property of the UK-sourced OULAD data, and that
population mismatch is itself a limitation worth stating explicitly (§7.9).

### 7.8 A genuine negative result — worth its own results-chapter subsection
A later phase of this project added 9 additional "time-aware" features
(real per-question response-time capture, speed-accuracy scoring) and ran
a proper ablation study comparing 6 feature-set variants at a fixed
algorithm (XGBoost), rather than re-running full 9-model selection per
variant (documented as infeasible: full model selection was measured at
2+ hours per variant). **Result: no time-aware variant beat the existing
43-feature live model.** The current live model (43 features): macro-F1
0.6845. Adding the 9 response-time features actually scored *below* a
behaviour-only intermediate variant, and the full time-aware model still
didn't recover past the baseline. Per the project's own decision rule
("do not claim improved accuracy until evaluation results demonstrate it"),
the time-aware features were **not** merged into the live model and the
live model was **not** retrained on them.

This is exactly the kind of result a thesis should report as a legitimate
finding, not bury: it demonstrates the evaluation methodology actually
gates deployment decisions, rather than every experiment being framed as a
success.

### 7.9 Threats to validity (a ready-made limitations subsection)
- Labels are 45.7% real-grounded; the rest is calibrated but still
  synthetic.
- The 9 time-aware features were, by necessity, synthesized for training
  data (no public dataset records real per-item response times) — honestly
  documented as such, same pattern as the other platform-only features.
- Real datasets' populations (UK distance-learning adults, Portuguese
  secondary students) don't match this platform's actual target demographic
  (Sri Lankan 20–30-year-old competitive-exam candidates) — a genuine,
  stated population-mismatch limitation.
- Multi-output regression targets (next score, score change) have modest
  R² (0.29–0.32) — reported honestly, not hidden or over-tuned.
- A quantified (not just suspected) minor data-leakage risk: ~12.3% of
  real-OULAD students contribute multiple rows (one per module
  presentation), and the current train/test split is row-level rather than
  student-grouped, giving a mild memorization advantage on a small fraction
  of the training data. Documented, not yet corrected (would require a
  retrain).

---

## 8. Additional System Features (for a "features" or "system overview"
chapter — briefer, since these are less methodologically novel than §4/§7)

- **Gamification**: XP (triangular level curve), coins, 14 badges,
  daily/weekly missions, leaderboard.
- **Mock exams**: configurable question count/duration, weighted-but-bounded
  category allocation (roughly half even split, half inverse-mastery-
  weighted, every requested category guaranteed a floor allocation), a real
  countdown timer with auto-submit on expiry.
- **Weak-area-weighted practice**: daily/practice sessions bias question
  sampling toward a student's weaker categories, with the bias sharpening
  (via a per-phase exponent) as an exam date approaches.
- **Study plans**: rule-based (explicitly not ML, see §7.1), phase-aware
  (foundation → practice → intensive → final-revision → exam-day), with a
  "readiness gap" warning that fires only when an exam is genuinely near, a
  meaningful gap exists, *and* the current plan can't close it in time —
  never an unconditional guarantee.
- **Self-learning notes + spaced repetition**: a simplified SM-2 algorithm
  (explicitly documented as simplified, not claiming full commercial-grade
  sophistication), weak-subcategory-triggered lesson recommendations, and a
  retrieval-practice self-check quiz drawn from real bank questions.
- **8 cognitive-training mini-games**, each targeting a distinct construct
  (memory span with interference tasks, sequence/pattern reasoning,
  arithmetic fluency under time pressure, mental rotation, selective
  attention, visuospatial memory, and a multi-task game computing a real
  "cognitive switching cost" metric — reaction-time delta immediately after
  a rule change vs. steady-state performance).

---

## 9. Independent Validation Results (real, executed evidence for a
"testing and validation" chapter)

A full system audit was performed as its own dedicated pass (not part of
feature development), producing concrete, reproducible evidence:

- **131 independently re-derived question answers** (percentages,
  profit/loss, simple interest, averages, data interpretation, work-and-
  time, speed-distance, chained multi-step word problems) — re-computed
  from scratch by re-parsing question text, not by re-invoking the
  generator's own solver — **0 mismatches** against stored answers.
- **1,484 SVG-based visual questions** — 0 malformed SVG found across a
  sampled + automated scan; a sample of transformation-based answers
  independently replayed from the underlying generation rule and confirmed
  correct against the stored answer.
- **Data-integrity check across 6,770 active questions**: 0 missing text,
  0 malformed options JSON, 0 missing/invalid correct-answer keys, 0
  invalid category/level references, 0 out-of-range IRT metadata.
- **Automated backend test suite**: 100/100 passing.
- **IRT/IQ calculation-chain edge cases** verified directly (all-correct,
  all-incorrect, single-item, zero-item, mixed responses) — all produced
  sane, correctly-clamped values with standard errors that correctly
  reflect estimate reliability.
- **Security/authorization audit**: 12 live unauthenticated-access tests
  against protected endpoints, all correctly rejected; every controller's
  data-scoping traced directly to confirm no cross-user data leakage is
  possible; password hashing confirmed (bcrypt via Laravel's `Hash`
  facade), no plaintext storage.

If your thesis includes a "System Testing" or "Validation" chapter, this
section is your primary source — every number above was produced by an
actually-executed check, not estimated.

---

## 10. Known, Honestly-Stated Limitations (a ready-made limitations chapter)

- ML labels are only 45.7% real-grounded (§7.9).
- Real training-data populations don't match the platform's actual target
  demographic (§7.9).
- LIME/SHAP local-explanation agreement is only 36% (§7.6) — a known
  property of comparing distinct XAI methods, not a bug.
- A minor, quantified ML train/test leakage risk from repeat-student rows
  in the real OULAD data (§7.9) — documented, not yet corrected.
- No bulk question-import tool exists (question CRUD is per-question only).
- The AI question/coach generation features have only been exercised
  against their Mock implementations in this environment (no Gemini API
  key configured) — the real-LLM code path is implemented and interface-
  complete but not empirically verified against a live model response.
- A full student-facing (as opposed to admin-facing) browser walkthrough
  of the redesigned UI was not completed in this environment specifically
  because students authenticate exclusively via Google OAuth and no
  password-based path exists for that role in a local dev environment —
  verified instead via the automated test suite, type-checking, and direct
  service-level testing against real data.
- 94% of the visual question bank (1,400 of 1,484 rows, predating a later
  schema addition) lacks stored `generation_rule`/`transformation_steps`
  metadata for post-hoc auditability, even though answers were
  independently re-verified as correct via replay — a traceability gap,
  not a correctness bug.

---

## 11. Suggested Thesis Chapter Mapping

- **Chapter: Introduction/Motivation** → §1
- **Chapter: Literature Review** → you'll need to add this yourself (this
  document is project-internal, not a literature survey) — but §4, §7.1,
  §7.3, and §7.6 each name the specific methodological choice you'll want
  to justify against prior work (Rasch/CAT theory, hybrid real+synthetic
  training data, SHAP/LIME explainability).
- **Chapter: System Design/Architecture** → §2, §3
- **Chapter: Adaptive Testing Methodology** → §4
- **Chapter: Question Bank / Content Generation Methodology** → §5, §6
- **Chapter: ML Methodology** → §7 (this is your longest, most citable
  chapter — it has real numbers, a real negative result, and a real
  bias/fairness audit, all of which are exactly what an examiner wants to
  see)
- **Chapter: System Features** → §8
- **Chapter: Testing & Validation** → §9
- **Chapter: Limitations & Future Work** → §10, plus your own reflections
- **Chapter: Conclusion** → synthesize §1 + §7.5/§7.8 (the headline result
  and the honest negative result) + §10

---

## 12. Things to double-check before citing exact numbers in a final
submission

This document was generated from the project's own engineering-session
records. Numbers in §7.5/§7.8/§9 reflect the state of the system as of the
most recent full audit and ML evaluation run. If it has been more than a
few sessions since this document was generated, ask Claude to re-verify any
specific number you're about to put in a thesis (e.g. re-run
`php artisan test`, re-check `models/ablation_report.json` or
`model_comparison_report.json`) rather than assuming it hasn't changed —
this project's own working convention is to trust the current filesystem/
test output over any cached summary, and your thesis should hold itself to
the same standard.
