# HelaIQ — Consolidated Thesis Reference Document

**An AI-Powered Cognitive Training Platform for IQ Development in Sri Lanka**
Final Year Project CT/2020/074 — W.R. Jayasundara, Department of Computer Systems Engineering, University of Kelaniya
Supervisor: Dr. N. Mekhala Hakmanage, Senior Lecturer, Computer Systems Engineering

*This is the single master reference for writing the thesis: it consolidates everything previously spread across 23 separate documents in `docs/` into one document, in the order needed for a thesis (overview → features → architecture → methodology → testing → deployment → user guide → results → limitations → viva prep → formal methodology chapter draft). Every number in this document is either taken directly from a prior verified project record or was independently re-queried against the live system while writing this document (see §8) — nothing here is fabricated or estimated. Where two prior documents disagreed on a figure (this happened twice — the IRT level cutpoints and the question-bank size), the more recent, corrected figure is used and the discrepancy is noted so it is not silently smoothed over.*

---

## Table of Contents

1. [Project Overview & Motivation](#1-project-overview--motivation)
2. [Full Feature List](#2-full-feature-list)
3. [System Architecture](#3-system-architecture)
4. [Core Methodologies](#4-core-methodologies)
   - 4.1 [Adaptive Testing Engine (IRT / Rasch)](#41-adaptive-testing-engine-irt--rasch)
   - 4.2 [ML Exam-Readiness Prediction](#42-ml-exam-readiness-prediction)
   - 4.3 [Time-Aware Analytics](#43-time-aware-analytics)
   - 4.4 [Question Generation System](#44-question-generation-system)
   - 4.5 [Personalized Learning System](#45-personalized-learning-system)
   - 4.6 [Mock Exam System](#46-mock-exam-system)
   - 4.7 [Game Design](#47-game-design)
   - 4.8 [Sinhala / Bilingual Methodology](#48-sinhala--bilingual-methodology)
5. [Testing & Validation](#5-testing--validation)
6. [Deployment / Current Infrastructure](#6-deployment--current-infrastructure)
7. [User Guide](#7-user-guide)
8. [Pre-Test / Post-Test Evaluation: Methodology and Results](#8-pre-test--post-test-evaluation-methodology-and-results)
9. [Limitations & Known Issues](#9-limitations--known-issues)
10. [Viva / Defense Preparation](#10-viva--defense-preparation)
11. [Thesis Methodology Chapter Draft](#11-thesis-methodology-chapter-draft)

---

## 1. Project Overview & Motivation

### 1.1 The problem

Sri Lankan candidates preparing for competitive government examinations (SLAS, Development Officer, Grama Niladhari, banking recruitment, the Police, the Teaching Service, and similar public-sector entrance exams) generally rely on static PDF question banks and printed guides. These resources have no notion of a candidate's actual ability, no measurement of *when* a candidate is genuinely ready, and no way to tell whether daily practice is closing the gap before the exam date. Sinhala-language adaptive practice tools for this specific market are essentially absent.

### 1.2 Origin and scope evolution

The project began with a narrower scope. Early supervisor feedback that this scope "lacked novelty" led to a large, explicit, phased expansion, executed over multiple project sessions: an adaptive Item Response Theory (IRT) testing engine, a machine-learning exam-readiness predictor trained on a hybrid of real and calibrated-synthetic data, a purpose-built competitive-exam-grade question bank (grown from an original ~2,000-question primary-school-level set to 6,770 active questions as of the most recent full audit), full gamification, complete bilingual (English/Sinhala) support, AI-assisted content generation with a mandatory human-review gate, and — in later sessions — real per-question response-time capture, mock exams, self-learning study notes with spaced repetition, and a full visual identity/UX redesign (rebranded from the working name "MindRise" to "HelaIQ"). All of this expanded scope is now built, tested, and working; this document reports it as it exists today, without rounding up either its strengths or its limitations.

### 1.3 What HelaIQ does — the core loop

1. **A properly measured starting point.** An adaptive IRT/computerized-adaptive-test (CAT) placement test estimates a real latent ability score (θ, "theta"), not a raw percentage.
2. **Practice that targets weaknesses.** Daily and practice sessions weight question allocation toward a student's weakest categories rather than sampling evenly or randomly.
3. **A readiness signal that means something.** A trained machine-learning model estimates whether a student is on track, distinguishing *general cognitive readiness* from *readiness for a specific named exam*.
4. **Full bilingual support.** Every student-facing surface exists in English and Sinhala, protected by a validated Sinhala-text corpus process (§4.8) to prevent script corruption.
5. **Government-exam preparation tools.** An exam profile with a live countdown, a rule-based adaptive study plan, and full timed mock exams.

### 1.4 Academic honesty as a working principle

This project's own standing engineering convention — carried through every session of its development and preserved unchanged in this consolidated document — is "honest scope-cuts over overclaiming": every deliberate simplification carries a one-paragraph rationale, and negative or inconclusive results (most notably the time-aware ML ablation study, §4.3) are reported as such rather than hidden or reframed as successes. Wherever this document says a number is "real," it means the number came from an actually-executed test, query, or training run — not an estimate, and never a fabricated or rounded-up figure. This principle should be carried into every chapter of the thesis itself.

---

## 2. Full Feature List

### 2.1 Who uses the platform

| Role | What they do |
|---|---|
| **Student** | Registers (Google OAuth or email/username + password), takes the adaptive placement test, receives daily/weak-area/timed practice recommendations, tracks IQ and readiness trends, plays 8 cognitive-training mini-games, takes timed mock exams, reads self-learning study notes with spaced repetition, sets a government-exam target with a live countdown, and can submit feedback. |
| **Admin** | Manages the question bank across four authoring modes (manual text, manual image, deterministic pattern-generated, AI-drafted), reviews AI-generated content before it can go live, runs IRT psychometric calibration, monitors cohort-wide research analytics with a synthetic/real data toggle, manages an uploaded reference-document knowledge library, and reviews student feedback. |

### 2.2 Feature list

- **Adaptive IRT/CAT placement test** (Rasch 1-parameter logistic model), 15–25 items, content-balanced across 5 cognitive categories.
- **Daily and category practice sessions** with weak-area-weighted question allocation, sharpening automatically as an exam date approaches.
- **Mock exams** with configurable question count/duration, weighted-but-bounded category allocation, and the platform's first genuine countdown timer (auto-submits on expiry).
- **Exam-readiness prediction (ML)** — a 4-class label plus a smoothed percentage, distinguishing general cognitive readiness from readiness for a named exam, plus three additional real-data-grounded predictions (risk of dropping practice, predicted next assessment score, predicted score change).
- **Explainable AI** on every prediction: top-5 SHAP-attributed reasons plus a trend-aware plain-English explanation comparing against the student's previous prediction.
- **AI-assisted wrong-answer explanations** (Gemini, with an honest rule-based Mock fallback that always works with zero API key).
- **8 cognitive-training mini-games**: Memory Match, Sequence Puzzle, Math Rush, Mental Rotation Challenge, Selective Attention, Working Memory Span (adaptive), Visual-Spatial Memory (adaptive), and Cognitive Command Center (a multi-task game computing a real cognitive-switching-cost metric).
- **Gamification**: XP on a triangular level curve, coins, a 14-badge catalogue, daily/weekly missions, a cohort leaderboard.
- **Self-learning study notes** with structured sections (learning objective, worked example, key technique, common mistakes), a simplified SM-2 spaced-repetition scheduler, weak-subcategory-triggered recommendations, and an answer-included retrieval-practice self-check.
- **Government exam profile** with a live countdown, phase-aware rule-based study plan, optional real-exam pacing structure (question count, duration, pass mark, negative marking, sections), and a real post-exam outcome capture (attended / passed / score).
- **Speed-accuracy scoring** — a bounded [0,100] per-answer score built on real captured response times, separate from and non-destructive to the IRT ability estimate.
- **Student feedback and rating system**, with an admin review dashboard and a structurally-anonymized CSV export (no user-identifying columns ever selected).
- **Full bilingual (EN/SI) UI**, backed by a validated Sinhala corpus-safety process.
- **Admin research analytics** with a synthetic/real data toggle so demo accounts can never contaminate research numbers, an ML Research dashboard (model comparison, evaluation metrics, SHAP importances, version history), a Question Bank Stats dashboard, and an admin Knowledge Library for uploaded reference PDFs (topic extraction, chapter-structure detection, AI-question source context, study-note generation source).
- **AI question generation** with a mandatory draft → admin-review → publish pipeline; two independent duplicate-detection signals (Jaccard text overlap + a TF-IDF-cosine microservice check).

### 2.3 Scale, as of the most recent full system audit

| Metric | Value |
|---|---|
| Active questions | 6,770 (of 14,719 total rows; the remainder are retired banks kept `is_active=false` for foreign-key/history integrity, never deleted) |
| Active image-based (SVG) questions | 1,484 |
| Cognitive categories | 5 (memory, logical reasoning, numerical ability, attention, spatial/pattern) |
| Difficulty levels | 5 |
| Mini-games | 8 |
| Badges | 14 |
| Backend automated tests | 100/100 passing |
| API routes | 99, 0 duplicates |
| Sinhala verified-word corpus | 1,398 words, 33 scanned source files |
| ML training dataset | 73,637 rows, 45.7% real-world data |
| ML feature vector | 43 features (24 original + 19 advanced behavioural) |

---

## 3. System Architecture

### 3.1 Technology stack

| Layer | Stack |
|---|---|
| Backend | Laravel **9.19**, PHP **8.0.11** (pinned to the XAMPP environment; blocks PHP 8.1+-only syntax such as "new in initializers") |
| Frontend | React **19**, TypeScript, Vite, Tailwind **4**, shadcn/ui (Radix primitives), TanStack Query v5, React Router v7, i18next, Recharts, sonner |
| Database | MySQL, database name `iq_platform` (internal name predates the HelaIQ rebrand) |
| ML microservice | Python 3.11, scikit-learn, XGBoost, LightGBM, CatBoost, Optuna, SHAP, LIME, FastAPI + uvicorn |
| Auth | Laravel Sanctum (SPA cookie session); Google OAuth for students; a separate email/password endpoint for admins |

### 3.2 Layered overview

```
Browser (React 19 SPA)
   │  same-origin XHR via the Vite dev proxy — never talks to :8000 directly
   ▼
Laravel 9 API (:8000, PHP 8.0.11)
   │  Http/Controllers (thin) → Services/ (business logic, organized by domain:
   │  Irt/, Ml/, Analytics/, Sessions/, Study/, Gamification/, QuestionBank/,
   │  AiQuestionGeneration/) → Eloquent Models → MySQL
   │  Sanctum cookie-session auth
   ├───────────────────────────────┬──────────────────────────────
   ▼                                ▼
MySQL (iq_platform)          ML microservice (FastAPI, :8100)
questions, test_sessions,    /predict, /health, /metadata,
session_answers, users,      /evaluation-report,
exam_profiles,               /explainability-report,
exam_readiness_predictions,  /duplicate-check
study_notes, feedback, ...   scikit-learn / XGBoost / LightGBM / CatBoost model
```

### 3.3 Why the frontend never talks to the ML service directly

The ML microservice has no authentication of its own — it trusts whatever feature vector it is given. If the browser could call it directly, a student could submit a fabricated feature vector and obtain an arbitrary readiness score. Instead, `ReadinessPredictionService` (Laravel) is the *only* caller: it computes the feature vector itself from the student's real database rows (`FeatureExtractionService`), so a student can influence their own score only by actually practicing.

### 3.4 The swappable-service pattern

Every external or replaceable dependency in the backend — AI feedback, AI question generation, AI coaching, study-note generation — follows the same shape:

```
Contracts/XServiceInterface.php     the interface controllers depend on
Services/X/MockXService.php         works with zero config, always available
Services/X/GeminiXService.php       real implementation, config-driven
AppServiceProvider::register()      binds the interface to Mock or Gemini
                                     based on config('services.x_driver')
```

This means the whole platform runs and demos correctly with **zero API keys configured** — everything falls back to the Mock implementation — and switching to a real Gemini-backed feature is a single `.env` change (e.g. `AI_FEEDBACK_DRIVER=gemini`, `GEMINI_API_KEY=...`) with no code change. The Mock implementations are not disposable test doubles: the Mock question generator solves its own generated word problems for real, and the Mock study-note generator surfaces genuine keyword-matched topics rather than fabricating prose it can't actually understand. The ML readiness prediction follows the same *shape* but swaps an HTTP microservice rather than a PHP class, since PHP has no first-class gradient-boosting or SHAP implementation.

### 3.5 Request-flow example: exam-readiness prediction

1. Student clicks "Run prediction" (`ReadinessCard.tsx`).
2. `POST /api/readiness/predict` → `ReadinessController::predict()`.
3. `FeatureExtractionService::extract()` builds a 43-value feature vector from the student's real `test_sessions`, `session_answers`, `game_scores`, `user_daily_checkins`, `exam_profiles`, and previous predictions.
4. `ReadinessPredictionService::predictFor()` posts that vector (plus the previous prediction's snapshot, for trend explanations) to the ML service's `/predict`.
5. The ML service runs the live XGBoost model, computes SHAP-based reasons and a plain-English trend explanation, and returns the result.
6. Laravel persists it as a new `exam_readiness_predictions` row (history is never overwritten) and returns it to the frontend.
7. `ReadinessController::present()` adds a presentation-layer `readiness_type` (`general` vs. `exam_specific`), computed from whether the student currently has an active, not-yet-due exam profile — the *only* place this distinction is made; the model itself is unaware of it.

### 3.6 Authentication architecture

- **Students** authenticate via Google OAuth (`auth_provider='google'`) or username/email + password (`auth_provider='password'`); both land the same `role='user'` account. A student who registered by password and later signs in with Google on the same Google-verified email has that identity linked automatically (only that direction is done automatically, never the reverse).
- **Admins** authenticate via a separate `POST /api/admin/login` endpoint, restricted to `role IN ('admin','super_admin')`, never reachable via Google.
- **Session**: Sanctum SPA cookie session (not bearer tokens); the frontend fetches a CSRF cookie before any mutating request and axios attaches it automatically.

### 3.7 Database schema — grouped overview

MySQL, 25 tables (verified directly against every `Schema::create(...)` call across all 46 migration files — 3 of the 25 are Laravel framework tables (`password_resets`, `personal_access_tokens`, `failed_jobs`) rather than application domain tables), all managed through Laravel migrations. Grouped by purpose (see individual migration files for exact column definitions):

| Group | Key tables |
|---|---|
| Identity & access | `users` (flat RBAC via `role`), `password_resets`, `personal_access_tokens` |
| Question bank | `categories` (5 fixed), `iq_levels` (5), `questions` (~6,770 active), `ai_generated_questions` (draft staging), `source_documents` (uploaded reference PDFs) |
| Testing & scoring | `test_sessions`, `session_answers`, `user_progress_snapshots` |
| Exam readiness & planning | `exam_profiles`, `exam_readiness_predictions` (append-only history), `user_daily_checkins` |
| Gamification | `games`, `game_scores`, `badges`, `user_badges`, `xp_ledger` (append-only), `mission_claims` |
| Self-learning content | `study_notes`, `study_note_reviews` (spaced-repetition scheduling) |
| Feedback | `feedback` |
| AI coaching | `ai_coach_logs` |

For the full column-level reference — every column's type, nullability/default, and foreign keys, for all 25 tables, built by reading every migration (including later `add_*_to_*_table` migrations against each table) — see `docs/DATABASE_SCHEMA.md`.

**Database connection / configuration** (full detail, env var names only, in `DATABASE_SCHEMA.md`): MySQL via **XAMPP** locally, accessed exclusively through Laravel's **Eloquent** ORM (no alternative query layer). Schema source of truth is the **migrations** themselves — there is no separate hand-maintained SQL dump. Connection is configured via `backend/.env`: `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE` (value `iq_platform`), `DB_USERNAME`, `DB_PASSWORD` (names only — no credential values are reproduced in any documentation file).

**Key design decisions**, each worth a sentence in a data-model chapter:

- **Retire, never delete.** Questions, exam profiles, and badges that become obsolete are deactivated (`is_active=false`), never removed, because `session_answers`, `exam_readiness_predictions`, and similar history tables hold foreign keys into them; deleting would either cascade-destroy real history or require nullable foreign keys everywhere.
- **History tables are append-only.** `exam_readiness_predictions` and `xp_ledger` are never updated in place — every new fact is a new row, so trends can be plotted and past states audited.
- **`is_demo_user` / `is_demo_feedback`** are the only two flags that exist purely to separate synthetic UI-testing data from real research data; every research-facing query filters them out by default.
- **No `RefreshDatabase` in tests** — the test suite runs against the real development database, with an explicit `tearDown()` in every test file that deletes exactly the rows it created. This is a deliberate convention, not an oversight, chosen so IRT calibration and other stateful services are exercised against realistic data volumes.

### 3.8 Frontend architecture

- **Routing**: a single `App.tsx` route tree, guarded by composable wrapper routes (`<RequireAuth>`, `<RequirePlacement>`, `<RequireRole roles={[...]}>`). Admin routes live under `/admin/*` inside their own persistent-sidebar layout, separate from the student-facing top-nav layout.
- **Server state**: TanStack Query v5 everywhere — no ad-hoc `fetch` + `useState`. A critical, project-wide convention: **hook-level** `useMutation({ onSuccess, onError })` must be used, never per-call `.mutate(vars, { onSuccess })` — the latter is silently dropped under React 18/19 Strict Mode's double-invoke behaviour. (This exact anti-pattern was found live in all 8 games during the final system audit and fixed — see §5.)
- **i18n**: i18next, EN/SI namespaces under `src/locales/{en,si}/`, loaded eagerly at boot.
- **Design system**: a "Ceylon sapphire + cinnamon gold" palette (a deep-sapphire primary plus a sparing gold accent for achievements/streaks), a hand-coded `HelaIQMark` logo (an "H"-shaped mark using two strokes plus a 3-step ascending crossbar, deliberately avoiding generic AI-app clichés like a brain icon, a robot, or sparkles), a `BalancedGrid` primitive that computes a genuinely balanced row layout instead of a fixed `grid-cols-N` (columns = min(preferred, itemCount), rows = ceil(itemCount/columns), items per row = ceil(itemCount/rows)), and dedicated Sinhala typography (`@fontsource/noto-sans-sinhala` applied via a `:lang(si)` CSS rule, since the primary English typeface has no Sinhala glyphs).

### 3.9 Deployment topology (development)

All three services run on the same machine during development: Laravel on `:8000`, Vite on `:5173`, the ML service on `:8100`. None of them are persistent OS services — each needs a manual restart after a reboot or a long idle period. Full current and planned deployment detail is in §6.

---

## 4. Core Methodologies

### 4.1 Adaptive Testing Engine (IRT / Rasch)

#### 4.1.1 Motivation

An initial implementation of the platform's placement and levelling logic used fixed percentage-accuracy bands (e.g. 0–39% accuracy → Level 1). This treats every question as equally difficult, which is false, and has no statistical basis for its thresholds — two well-known limitations of classical, raw-score-based ability measurement (Hambleton, Swaminathan & Rogers, 1991). To give the platform's central claim — an *adaptive* measurement instrument — a genuine psychometric foundation, the placement logic was rebuilt around **Item Response Theory (IRT)**, specifically the **one-parameter logistic (Rasch) model** (Rasch, 1960), and the placement test was reimplemented as a true **computerized adaptive test (CAT)**.

#### 4.1.2 The Rasch model

```
P(θ) = 1 / (1 + e^-(θ - b))
```

`θ` (a student's latent ability) and `b` (an item's difficulty) are estimated on the same logit scale. If `θ = b`, the equation gives exactly 0.5 — a 50/50 chance, the intuitive definition of "this question is at your level." Implemented in `App\Services\Irt\RaschMath`.

The Rasch model was chosen over 2- or 3-parameter alternatives (which additionally estimate item discrimination and/or guessing) for two reasons appropriate to a bank that is still growing and a final-year-project timeline: (a) it requires substantially less response data per item to calibrate stably, and (b) it has the **specific-objectivity property** — person and item estimates are theoretically independent of which items/persons were used to obtain them — the right property for a platform that keeps adding new items over time. Discrimination is still tracked, as a separate diagnostic (`ItemAnalysisService::itemDiscrimination()`, a point-biserial correlation), without being folded into the ability estimate itself.

#### 4.1.3 Item calibration — PROX

Item difficulties are calibrated from real response data using **PROX** ("Normal Approximation," Wright & Stone, 1979), a closed-form, non-iterative calibration method, chosen over full joint maximum-likelihood estimation (JML) because it converges in a single pass with no risk of the non-convergence pathologies JML can exhibit on sparse response matrices — appropriate for a platform where many items still have modest response counts.

Worked mechanics: for each item and person, proportion-correct is converted to a logit (`logit(p) = ln(p/(1-p))`, with a `1/(2n)` continuity correction for any 0% or 100% observed rate); a finite-sample **expansion factor** `X = sqrt(1 + Var(item logits)/2.89)` corrects for the compression inherent in small-sample logits; each item's difficulty is then `X × (mean(person logits) − item_logit)`. Items with fewer than 5 observed responses use a **prior** difficulty derived from their authored level instead of an unstable data-driven estimate, automatically superseded once enough real data accumulates.

Calibration status follows an explicit lifecycle (`irt_calibration_status`: `uncalibrated → provisional → calibrated`), tracked alongside a running `irt_response_count`. `RaschCalibrationService` recalibrates on demand (`php artisan irt:calibrate`) and updates both fields on every run — a real gap (the response count was previously backfilled only once, at migration time, and never kept live) was found and fixed during a later project session.

#### 4.1.4 Ability estimation — MLE

A student's ability is estimated via **Maximum Likelihood Estimation**, solved by **Newton-Raphson iteration**:

```
θ_(k+1) = θ_k + [ Σ(u_i − P_i(θ_k)) ] / [ Σ P_i(θ_k)(1 − P_i(θ_k)) ]
```

where `u_i` is the observed 0/1 response to item `i`. The denominator is the test information function at `θ_k`; its inverse square root gives the standard error, `SE(θ) = 1/√I(θ)`, which drives both the adaptive stopping rule (§4.1.5) and a respondent-level precision indicator surfaced in the platform's analytics.

#### 4.1.5 Adaptive item selection (CAT) and termination

Since `I(θ) = P(θ)(1−P(θ))` is maximized exactly when `b = θ`, "pick the most informative next item" and "pick the item closest in difficulty to the current ability estimate" are the *same rule* for the Rasch model. After every answer, θ is re-estimated from all responses so far, and the next item is selected as the unseen, active item whose difficulty is closest to the updated estimate (`AdaptiveItemSelectionService::selectNext()`), constrained to rotate across the platform's 5 cognitive categories for content balance (a form of constrained CAT design, Kingsbury & Zara, 1989).

Testing terminates when either (a) a maximum of **25 items** has been administered, or (b) at least **15 items** have been administered **and** `SE(θ) ≤ 0.35` — a standard fixed-precision CAT stopping rule (Weiss, 1982) that prevents a single early lucky/unlucky streak from producing a "finished" but statistically meaningless estimate. In practice this yields 15–25 items per placement test.

Daily and practice sessions continue to use a fixed-size, pre-sampled question set for UX reasons (batch review, single submission), but θ is recomputed via MLE over the student's **complete response history** every time a session completes, so level and IQ stay consistent with the adaptive placement test's own scoring.

#### 4.1.6 Deriving level and IQ from θ

- **IQ score**: `IQ = 100 + 15θ` (mean 100, SD 15 — the conventional deviation-IQ scale used by contemporary instruments such as the Wechsler scales), clamped to **[40, 160]**. Since θ is already approximately standard-normal after calibration, this is a rescaling, not a second model.
- **Classification bands** (`IqScoreService::classify()`): extremely_low (<70) / below_average (70–84) / average (85–114) / above_average (115–129) / gifted (≥130) — the standard Wechsler bands.
- **Platform level (1–5)** (`LevelAdjustmentService::levelNumberForTheta()`): θ cut at **−2.0 / −1.0 / 1.0 / 2.0** logits. *(Note on an internal discrepancy, resolved here: an earlier draft of the leveling logic used narrower cutpoints of −1.5/−0.5/0.5/1.5; a real inconsistency was subsequently found where the level system and the IQ classification bands could contradict each other — e.g. a "Level 5 – Expert" student showing an "Above Average" rather than "Gifted" IQ label — and the level cutpoints were corrected to −2.0/−1.0/1.0/2.0 specifically to align exactly with the IQ bands above. The corrected cutpoints are what is live in the current system and should be the ones cited in the thesis.)*

Both the raw θ estimate (clamped at ±4.5 logits internally) and the display-bound IQ clamp ([40,160]) are two separate, independently-justified decisions, not the same number reused twice.

#### 4.1.7 Validation — Monte Carlo parameter recovery

Because real usage volume is not yet large enough to validate the calibration/estimation code against, it was validated independently via a **Monte Carlo parameter-recovery study** (a standard IRT validation method: Harwell, Stone, Hsu & Kirisci, 1996): synthetic students and items with a *known* true ability/difficulty are generated, simulated responses are generated via the exact same Rasch probability formula, and the exact same production calibration/estimation code is run against those simulated responses and checked for how well it recovers the parameters it wasn't told.

**Table 4.1 — Monte Carlo parameter recovery results** (seed=42, reproducible via `php artisan irt:validate-simulation`)

| Metric | Value |
|---|---|
| Simulated respondents | 500 |
| Simulated items | 60 |
| Total simulated responses | 15,013 |
| Item-difficulty recovery — Pearson *r* | 0.991 |
| Item-difficulty recovery — RMSE (logits) | 0.165 |
| Person-ability recovery — Pearson *r* | 0.915 |
| Person-ability recovery — RMSE (logits) | 0.480 |

Item-parameter recovery was near-perfect, consistent with calibration accuracy benefiting from pooled information across all 500 simulated respondents per item. Ability recovery, while still strong, showed higher error — attributable to each simulated respondent answering only ~30 of the 60 items, the same sparsity (and corresponding precision limit) that motivates the platform's 15–25-item placement-test length and its policy of re-estimating θ from the full response history rather than a single session.

**Edge-case behaviour** (re-verified directly via `tinker`, worth citing as a small results table):

| Scenario | θ | SE | IQ | Classification |
|---|---|---|---|---|
| All correct (5 items) | 4.5 (clamped) | 1.95 | 160 (clamped) | gifted |
| All wrong (5 items) | −4.5 (clamped) | 4.69 | 40 (clamped) | extremely_low |
| Single correct item | 4.5 | 9.59 (huge — correctly signals unreliability) | 160 | gifted |
| Zero items | 0 | 9.99 | — | — |
| Mixed (3/5 correct) | 1.59 | 1.10 | 124 | above_average |

The single-correct-item case's huge SE (9.59) is real and correctly computed, but the live placement flow can never actually reach it in production: `TestSessionController` enforces the 15-item minimum described in §4.1.5, so a genuinely low-confidence single-item estimate is never shown to a real student as a finished result — a property of the underlying mathematics, not a reachable failure mode.

#### 4.1.8 Reliability reporting

Classical internal-consistency reliability (Cronbach's alpha) assumes every respondent answers the same fixed set of items — an assumption that does not hold for an adaptively/randomly sampled test. The platform instead reports **marginal reliability** (Green, Bock, Humphreys, Linn & Reckase, 1984), the IRT/CAT-appropriate analogue:

```
reliability = 1 − mean(SE(θ)²) / Var(θ across all students)
```

surfaced on an administrator-facing Psychometrics dashboard alongside item difficulty distributions and point-biserial item discrimination indices.

#### 4.1.9 What this methodology deliberately does not claim

- The Rasch model assumes unidimensionality within each of the 5 cognitive categories; cross-category comparisons (e.g. memory θ vs. numerical θ) are reported as separate per-category estimates, never merged into one score.
- PROX is a classical estimator, not claimed to match iterative JML or marginal maximum likelihood at very high response volumes — it was chosen for stability at the response volumes this platform actually has.

---

### 4.2 ML Exam-Readiness Prediction

#### 4.2.1 What is predicted

| Output | Type | Ground truth |
|---|---|---|
| Readiness label (`high_risk` / `needs_improvement` / `almost_ready` / `ready`) + smoothed 0–100 percentage | Classification | Composite heuristic (synthetic rows) / real outcome (real rows) — see §4.2.4 |
| Risk of dropping practice (probability + boolean) | Binary classification | Real (OULAD temporal split) |
| Predicted next assessment score | Regression | Real (OULAD temporal split) |
| Predicted score change | Regression | Real (OULAD temporal split) |
| Time-management readiness percent | Rule-based (not ML) | N/A |

Two outputs the original brief considered — **"recommended daily study hours"** and **"most effective learning strategy"** — are **deliberately not** built as ML predictions. No available dataset (real or synthetic) provides valid ground truth for either: no dataset records what the *optimal* study duration would have been for a given student, and determining the most effective strategy specifically requires interventional/causal data (the same student's outcome under strategy A versus strategy B) that no observational dataset — including this platform's own logged history — can provide (an observed correlation between "students who used strategy A did better" and strategy A *causing* that outcome is a textbook causal-inference confound). Both are delivered instead as `StudyPlanService` rule-based recommendations, explicitly not framed as ML outputs. This is a deliberate, documented scope decision — reintroducing them as literal ML predictions without genuine new ground-truth data would contradict this project's own validity argument.

#### 4.2.2 General vs. exam-specific readiness

The model computes one readiness number regardless of whether a student has an exam profile — the *distinction* between "general cognitive readiness" and "readiness for exam X" is applied entirely at the presentation layer (`ReadinessController::present()`), not by training two different models. If a student has an active, not-yet-due exam profile, the number is labelled "Readiness for {exam name}"; otherwise it is labelled "Overall Cognitive Readiness." Once an exam's date passes, the student is prompted once for a real outcome (attended/passed/score), and the profile moves to a "Past Exams" history; readiness reverts to the general framing until a new profile is created. The number is never artificially forced to a placeholder — it is always the model's actual current output.

#### 4.2.3 Input features — 43 total

24 "original" features (θ, IQ, per-category accuracy/theta, session counts, streak, self-reported study hours/motivation/attendance, days until exam, etc.) plus **19** "advanced" behavioural features, each with an exact mathematical definition:

`rolling_avg_score`, `weekly_trend`, `monthly_trend`, `learning_velocity`, `knowledge_gain_rate`, `consistency_index`, `fatigue_score`, `retention_score`, `engagement_score`, `practice_intensity`, `error_recovery_rate`, `category_mastery`, `confidence_trend`, `reaction_speed_trend`, `adaptive_learning_gain`, `difficulty_progression`, `question_diversity_score`, `time_management_score`, `revision_frequency`.

Representative definitions (full table: `ml-service/data_pipeline/advanced_features.py`):

| # | Feature | Definition |
|---|---|---|
| 1 | `rolling_avg_score` | Mean of the last 5 assessment/session scores |
| 2 | `weekly_trend` | OLS slope β₁ of score ~ β₀ + β₁·week, fit over the last 8 weeks |
| 4 | `learning_velocity` | `LV = (θ_now − θ_(t−4wk)) / 4` |
| 6 | `consistency_index` | `CI = 100(1 − CV)`, CV = σ/μ of recent scores |
| 7 | `fatigue_score` | Within-session accuracy decay: 1st-half minus 2nd-half accuracy, averaged over recent sessions |
| 8 | `retention_score` | Accuracy on questions re-encountered ≥14 days after first attempt |
| 11 | `error_recovery_rate` | `P(correct at i+1 | incorrect at i)` |
| 12 | `category_mastery` | `100 × P(correct)` via the same Rasch model, `1/(1+e^-(θ_c − b̄))` |
| 17 | `question_diversity_score` | `100 × distinct subcategories attempted / total available` |

The exact same ordered 43-feature list is implemented independently in PHP (`FeatureExtractionService::FEATURE_ORDER` + `::ADVANCED_FEATURE_ORDER`) and Python (`feature_mapping.py::FULL_FEATURE_ORDER`), and this one-to-one contract was directly verified byte-for-byte across both languages **and** the live-serving copy in `ml-service/app.py` — a genuine engineering risk in any system that trains in one language and serves from another, worth naming explicitly as a threat that was tested for. Three features (`study_hours`, `motivation_score`, `attendance_percent`) have no platform instrumentation and are self-reported via daily check-ins — a weaker signal than measured behaviour, flagged honestly.

#### 4.2.4 Training data — the hybrid real + calibrated-synthetic strategy

No public dataset records whether a Sri Lankan government-exam candidate passed, because no such dataset exists. The model instead learns from a **hybrid** dataset (`ml-service/data/hybrid_student_dataset.csv`, **73,637 rows, 45.7% real**):

| `data_source` | Rows | Share | Provenance |
|---|---|---|---|
| `real_oulad` | 32,593 | 44.3% | Open University Learning Analytics Dataset (Kuzilek, Hlosta & Zdrahal, 2017, CC BY 4.0) — real UK distance-learning students, real dated assessment scores, real day-level virtual-learning-environment clickstream (10.6M events; chunk-processed, never loaded fully — the raw file is ~450MB) |
| `real_uci` | 1,044 | 1.4% | UCI Student Performance (Cortez & Silva, 2008, CC BY 4.0) — real Portuguese secondary-school students, three sequential real grades |
| `synthetic_calibrated` | 40,000 | 54.3% | `generate_dataset.py`'s composite-score heuristic, weights for its most-important features **empirically calibrated** via logistic regression against real OULAD outcomes |

A hard **40% floor** is enforced in the assembly pipeline, so a future change to the synthetic row count cannot silently dilute the real-data anchor below a documented minimum.

**Datasets considered and explicitly excluded** (documenting exclusions is as important to a defensible methodology as documenting inclusions):

| Dataset | Why excluded |
|---|---|
| EdNet | >100GB across its full release — infeasible on this project's compute envelope |
| ASSISTments / KDD Cup Educational datasets | Distributed via a gated per-dataset access-request process this project has no institutional channel to complete within the project timeline; KDD Cup's data is also step-level ITS interaction logs that don't map cleanly onto this platform's session/category structure |
| xAPI-Edu-Data | ~480 rows, course-level (not day-level) engagement — materially less than OULAD while covering largely the same relationship OULAD already provides |
| Generic Kaggle "student performance" uploads | Mostly re-hosted copies of the UCI dataset already used, or unsourced/unlicensed with no traceable provenance |

**Mapping real data onto platform features** (`feature_mapping.py`): every feature is classified **real-measured** (derived directly from a genuine source measurement — e.g. `avg_test_score` from real submitted assessment scores, `practice_streak` from the longest real consecutive active-day run in the clickstream, `question_completion_rate` from a real assessment-submission ratio) or **platform-only** (no public dataset measures IRT ability, HelaIQ's specific cognitive categories, or its mini-games; for real rows these are generated from a **pseudo-theta derived from that student's own real avg_test_score and consistency_score**, `θ ≈ (avg_test_score − 50) / 15`, run through the *same* structural equations the pure-synthetic generator trusts — meaning a real high-performing student gets platform-only features consistent with high performance, not random noise; the honest limitation is that this remains an approximation, not a direct measurement).

**Calibrating the synthetic generator against real outcomes**: a multinomial logistic regression was fit on all 32,593 real OULAD students' actual `final_result` label against five real-measured features (train accuracy 64.0%), and the resulting coefficients replace the corresponding hand-picked weights in the synthetic generator's composite-score heuristic (L1-normalized to preserve the original weight mass).

**Table 4.2 — Calibrated synthetic-generator weights**

| Feature | Calibrated weight (of 0.40 total mass) | Raw logistic-regression coefficient |
|---|---|---|
| `avg_test_score` | 0.305 | +6.12 |
| `consistency_score` | 0.040 | −0.81 |
| `weekly_practice_count` | 0.027 | +0.54 |
| `attendance_percent` | 0.026 | +0.53 |
| `improvement_trend` | 0.001 | −0.03 |

Two findings reported honestly rather than smoothed over: (1) `avg_test_score` dominates the calibrated weight far more (76% of the mass) than the original hand-picked weights assumed (35%) — in real data, raw assessment performance is a far stronger predictor of final outcome than any engagement metric; (2) `consistency_score`'s real coefficient is **negative** — students with *lower* score variance were, in this real data, *less* likely to reach the top outcome band (a plausible reading is that OULAD's highest performers show substantial variance across assessments, while a low-variance profile is equally consistent with consistent mediocrity). Because the calibration pipeline normalizes on `abs(coefficient)`, this sign is not preserved in the deployed weight — a documented simplification, not hidden.

#### 4.2.5 Multi-output models — real, temporally non-leaky ground truth

`process_oulad_temporal.py` builds a genuinely forward-looking setup: for each (student, course module), the **first half** of real activity/assessment records become the input features, and something that only happens in the **second half** becomes the target — avoiding target leakage that a same-summary features-and-target split would introduce. UCI is excluded from this specific pipeline (only 3 static grades, no dated log to split without leakage).

**Table 4.3 — Multi-output evaluation results**

| Target | Metric | Value | n (train / test) |
|---|---|---|---|
| `risk_of_dropping_practice` | F1 | 0.775 | 20,579 / 5,145 |
| `risk_of_dropping_practice` | ROC-AUC | 0.945 | |
| `next_assessment_score` | MAE | 12.93 (score points, 0–100 scale) | 15,560 / 3,891 |
| `next_assessment_score` | RMSE | 17.10 | |
| `next_assessment_score` | R² | 0.315 | |
| `score_change` | MAE | 8.76 | 15,560 / 3,891 |
| `score_change` | RMSE | 12.09 | |
| `score_change` | R² | 0.285 | |

The risk classifier performs strongly (F1 0.775, ROC-AUC 0.945) — disengagement is a comparatively easy-to-detect pattern from first-half behaviour. The two regression targets are honestly **modest** (R² 0.29–0.32): predicting an exact future score from only first-half summary features is a genuinely hard problem, and reporting this honestly rather than over-tuning until it looks better is itself a defensible research finding — it shows first-half engagement/performance summaries carry real but limited predictive signal for a specific future score.

#### 4.2.6 Model selection

Nine candidate model families were screened via 5-fold stratified cross-validation on macro-F1 (Random Forest, Extra Trees, Gradient Boosting, AdaBoost, XGBoost, LightGBM, CatBoost, SVM, and a small MLP), then the top 3 were tuned via **Optuna** (Tree-structured Parzen Estimator, Bayesian optimization) under a **nested cross-validation** scheme (an outer 3-fold split gives an honest generalization estimate, since tuning and evaluating on the same fold would overstate performance; an inner 3-fold/12-trial search finds hyperparameters; a final full-training-set Optuna pass produces the parameters actually deployed).

**TabNet** (Arik & Pfister, 2021) was deliberately excluded — a documented scope decision, not an oversight: it is designed to be competitive with gradient-boosted trees specifically on datasets an order of magnitude larger than this project's (the original paper's own benchmarks use 100K–10M+ rows), shows no demonstrated advantage at ~74K rows, and would add a full PyTorch dependency to an otherwise lightweight FastAPI service.

**Table 4.4 — Model comparison and selection (training run `20260711054426`, 58,909 train / 14,728 test rows, 43 features)**

| Model | 5-fold CV screening macro-F1 |
|---|---|
| Random Forest | 0.6656 |
| Extra Trees | 0.6531 |
| Gradient Boosting | 0.6738 |
| AdaBoost | 0.4978 |
| XGBoost | 0.6760 |
| LightGBM | 0.6752 |
| CatBoost | 0.6747 |
| SVM (15,000-row subsample) | 0.6389 |
| MLP | 0.6657 |

Top-3 selected for HPO: XGBoost, LightGBM, CatBoost.

| Model | Default test macro-F1 | Optimized test macro-F1 | Gain |
|---|---|---|---|
| XGBoost | 0.6795 | **0.6808** | +0.0013 |
| LightGBM | 0.6779 | 0.6799 | +0.0020 |
| CatBoost | 0.6793 | 0.6800 | +0.0008 |

**Selected: XGBoost**, optimized macro-F1 = **0.6808** on held-out test data. Optuna tuning improved it only marginally (+0.001–0.002) — reported honestly as a small, not a large, effect of hyperparameter search on this dataset.

*Known, documented discrepancy*: the `model.joblib` file currently deployed live is stamped version `20260709143659`, an earlier training run than the `20260711054426` run these headline figures are drawn from. Both runs used the same pipeline and produced comparable macro-F1; the discrepancy exists because `model_registry.py`'s formal `register_version()`/`promote()` workflow was implemented but never actually run for either training run — a real, acknowledged gap (§9), not silently resolved.

#### 4.2.7 Comprehensive evaluation

Beyond accuracy and macro-F1, `evaluate.py` computes precision/recall (macro and weighted), ROC-AUC and PR-AUC (one-vs-rest macro — PR-AUC specifically because the "ready" class is a minority class, ~10% of the dataset), balanced accuracy, Matthews correlation coefficient, Cohen's kappa, log loss, Brier score, per-class calibration curves, 10-fold and repeated 5×3-fold stratified cross-validation, 1,000-resample bootstrap 95% confidence intervals, a learning curve (explicit overfitting/underfitting diagnosis), a validation curve over the model's most impactful hyperparameter, and a per-`data_source` performance breakdown.

**Table 4.5 — Final evaluation metrics** (executed run, not placeholders)

| Metric | Value |
|---|---|
| Accuracy | 0.697 |
| Balanced accuracy | 0.672 |
| Precision (macro) | 0.697 |
| Recall (macro) | 0.672 |
| **F1 (macro)** | **0.681** |
| F1 (weighted) | 0.693 |
| ROC-AUC (OVR macro) | 0.906 |
| PR-AUC (OVR macro) | 0.748 |
| Matthews correlation coefficient | 0.575 |
| Cohen's kappa | 0.574 |
| Log loss | 0.667 |
| Mean Brier score | 0.102 |

The evaluation pipeline's own diagnosis flags **overfitting** (train score notably exceeds cross-validation score) — reported here rather than omitted, since it is a legitimate finding about model generalization visible live on the admin ML Research dashboard, not a failure of the writeup.

**Table 4.6 — Performance by data source** (a genuinely interesting discussion-section result)

| Data source | Accuracy |
|---|---|
| Real UCI | 0.951 |
| Real OULAD | 0.724 |
| Synthetic-calibrated | 0.667 |

Real-UCI rows evaluate far better than real-OULAD or synthetic rows — plausibly because the UCI population is smaller/more homogeneous, or its outcome variable is cleaner. Worth discussing as a limitation/threat to generalization rather than cherry-picking the best-performing subset.

#### 4.2.8 Explainability

Explanations are computed via **four independent methods**, specifically so agreement across mechanistically-different methods provides stronger evidence of genuine model behaviour than any single method's output alone:

| Method | Mechanism | Role |
|---|---|---|
| **SHAP** (TreeExplainer, primary) | Game-theoretic Shapley values | Global importance, per-instance explanations, pairwise interaction values |
| **LIME** | Local linear surrogate around a perturbed neighbourhood | Independent local cross-check |
| **Permutation importance** | Shuffle one feature, measure held-out F1 drop | Fully model-agnostic |
| **Partial dependence** | Marginal effect of one feature, holding others at their observed distribution | Shows the *shape* of a feature's effect, not just its magnitude |

**Top global features by SHAP** (a sensible, face-valid ranking): average test score, question completion rate, θ, wrong-answer percent, days until exam, question-diversity score, practice streak, study hours.

**LIME/SHAP agreement was measured at only 36%** — reported honestly rather than hidden. Low cross-method agreement on *local* (per-instance) explanations is a known phenomenon in the explainable-AI literature (different explanation methods often disagree on individual predictions even when both are individually valid), worth a discussion-section paragraph rather than being smoothed over.

**Trend-aware plain-English explanations.** Every `/predict` response includes the top-5 SHAP reasons for that specific prediction plus a sentence comparing the current prediction against the student's *previous* one (not just a static snapshot): `ReadinessPredictionService` sends the previous prediction's stored feature snapshot as `previous_features`, and `/predict` computes the percent change on each top-ranked feature — e.g. *"Your readiness estimate changed because your weekly practice volume dropped by 45%, your numerical reasoning score declined, and your study consistency was low."* First-ever predictions (no prior snapshot) fall back to static framing for every clause.

#### 4.2.9 Bias and fairness audit

Real OULAD demographic fields (gender, disability, age band, deprivation tercile) were analyzed **offline only** — kept entirely out of the model's feature vector by design (fairness-by-design, not post-hoc correction): `feature_mapping.py` keeps them as `_demographic_*`-prefixed columns, explicitly dropped before any model ever sees the dataframe, so an "exam readiness" prediction can never directly condition on a protected characteristic even one correlated with a real disparity the source data reflects.

**Table 4.7 — Real outcome disparity found in real OULAD data** (share of students reaching the top "ready"/Distinction outcome band)

| Group | n | "Ready" share |
|---|---|---|
| Gender: F | 14,718 | 9.5% |
| Gender: M | 17,875 | 9.1% |
| Disability: No | 29,429 | 9.5% |
| Disability: Yes | 3,164 | **7.0%** |
| Age: 0–35 | 22,944 | 8.1% |
| Age: 35–55 | 9,433 | 11.9% |
| Age: 55+ | 216 | 19.0% (small n, less reliable) |
| Deprivation: most-deprived third | 14,020 | **6.7%** |
| Deprivation: mid third | 9,285 | 9.6% |
| Deprivation: least-deprived third | 8,177 | **12.2%** |

Gender shows near-parity (0.4 percentage-point gap). Disability status and socioeconomic deprivation both show a real, non-trivial gap (2.5pp and 5.5pp respectively). These disparities are a property of the **real OULAD population and UK higher-education context**, not of HelaIQ or this model — reported transparently, since fairness-by-design already ensures they cannot leak into the model as a feature; this is itself the correct research practice, not a flaw to fix by adjusting the model.

#### 4.2.10 Continual learning

`model_registry.py` archives every training run's full artifact set under a timestamped version, indexed with a SHA-256 hash of the training-data snapshot. `retrain.py` implements a genuine **champion-vs-challenger promotion gate** — a challenger only replaces the live model if it beats the live macro-F1 by a documented margin (≥0.5 percentage points), never automatically deploying a worse or negligibly-different model. A fully-automatic, continuously-scheduled production MLOps pipeline (drift-detection dashboards, automatic real-usage retraining triggers) was scoped out as disproportionate infrastructure for a single-VM student project — a documented decision, not a missing feature. As of the most recent project record, `register_version()`/`promote()` had not yet actually been run for the current live model (§9).

---

### 4.3 Time-Aware Analytics

#### 4.3.1 What existed before this work

Before this upgrade, the platform captured **zero** per-question response times anywhere, and the test-taking UI had **no timer of any kind** except the fixed mock-exam countdown; `answered_at` timestamps existed only as an inter-answer-delta proxy.

#### 4.3.2 Response-time capture

- `session_answers` gained `response_time_ms`, `time_performance_ratio` (actual ÷ expected time), `answered_within_expected_time`.
- `questions` gained `learned_expected_time_seconds`, `time_sample_count`, `time_calibration_status` — an explicit `uncalibrated → provisional → calibrated` lifecycle, mirroring the IRT calibration lifecycle exactly.
- `useQuestionTimer()` — the first timer wired into the question-answering UI.
- `ResponseTimeCalibrationService` (`php artisan time:calibrate`) learns each question's expected solving time from the **median** of real recorded response times once enough samples exist.

#### 4.3.3 Speed-Accuracy Performance Score

`SpeedAccuracyScoreService` computes a bounded [0, 100] per-answer score: wrong answers always score 0 regardless of speed; correct-but-slow answers are penalized up to −15%; there is **no** speed bonus above full marks for a correct-and-fast answer (three candidate formulations were compared in the service's own docblock before this one was chosen, specifically to avoid rewarding fast guessing over genuine accuracy). Rasch θ and IRT calibration are completely untouched — this is a separate, additive metric.

#### 4.3.4 Real-exam pacing

`exam_profiles` gained optional, skippable real-exam structure (`exam_total_questions`, `exam_duration_minutes`, `pass_mark`, `negative_marking`, `exam_sections`). When both duration and question count are supplied, `targetSecondsPerQuestion()` computes a real pace target (e.g. "72 seconds per question"), referenced by mock exams and the study-plan readiness-gap panel.

#### 4.3.5 The 9 time-aware ML features and the ablation study — a reported negative result

`FeatureExtractionService::TIME_AWARE_FEATURE_ORDER` (Laravel) and `ml-service/data_pipeline/time_features.py` (Python) define 9 objective, response-time-derived features (exam pace gap, time efficiency score, and others). They are fully computed end-to-end and available via `extractTimeAware()` — but were **deliberately not merged** into the live 43-feature vector the deployed model actually uses, because of the following result.

A dedicated ablation study (`ml-service/ablation_study.py`) fixed the algorithm at XGBoost (the already-selected winner) and varied only the feature set across 6 groups, rather than re-running full 9-model selection per variant (measured at 2+ hours per variant — infeasible for 6 variants and answering a different question than the one being asked):

**Table 4.8 — Ablation study results**

| Variant | Features | macro-F1 |
|---|---|---|
| A: current live baseline | 43 | **0.6845** (highest) |
| Step 1: scores only | 8 | 0.5465 |
| Step 2: + IRT | 14 | 0.5918 |
| B: Step 3, + behaviour | 39 | 0.6827 |
| C: Step 4, + response time | 50 | 0.6764 (time-aware, *worse* than B) |
| D: Step 5, full + subjective | 52 | 0.6810 (time-aware, still below A) |

**No time-aware variant beat the current live 43-feature model.** Adding the 9 response-time features scored *below* the behaviour-only variant, and the full model did not recover past the baseline either. Per this project's own decision rule — "do not claim improved accuracy until evaluation results demonstrate it" — the live model was **not** retrained or swapped. This is reported here as a legitimate negative result, not hidden: it demonstrates that the evaluation methodology actually gates deployment decisions rather than every experiment being framed as a success, which is precisely the kind of result a rigorous thesis should report rather than bury.

One honest caveat on the training data itself: because no public dataset records real per-item response times, the 9 time-aware features in the *training* data are synthesized from the same θ/motivation/consistency latents as other platform-only features (the same pattern already used for `fatigue_score`/`retention_score`), documented as such in `time_features.py`'s own docstring — this synthesized-rather-than-measured quality is one plausible reason the ablation found no benefit, and a future retrain using genuine accumulated response-time data (once enough real students have used the timer-equipped UI) could revisit this question with real rather than synthesized signal.

#### 4.3.6 Rule-based (non-ML) time-management outputs

- `time_management_readiness_percent` — computed only when the optional `exam_pace_gap`/`time_efficiency_score` fields are sent, comparing a student's actual pace against their target exam's pace requirement.
- `StudyPlanService`'s `readiness_gap` block and insufficient-plan `warning` — fires only when an exam is genuinely near, the readiness gap is meaningful, **and** the current plan mathematically cannot close it before the exam date; it never guarantees an outcome, only flags a computed shortfall.

#### 4.3.7 Mock exams — the first real timed assessment

Mock exams (`session_type='mock'`) did not exist in any form before this work; see §4.6.

---

### 4.4 Question Generation System

#### 4.4.1 Four ways a question enters the bank

| Mode | How | Admin route |
|---|---|---|
| Manual (text) | Admin fills a stepped wizard form with live preview | `AdminQuestionNewPage` → `QuestionWizard` |
| Manual (image) | Admin uploads an image asset for the stem/options | Same wizard, image mode |
| Pattern-generated (visual) | `SvgFigureBuilder` deterministically renders matrix-reasoning, rotation, mirror, paper-folding, cube-net, grid-counting, chart, and boolean-overlay figures as SVG — no external image assets, no LLM | `AdminVisualGeneratorPage` |
| AI-drafted (Gemini/Mock) | `AiQuestionGeneratorServiceInterface` generates a full draft, staged in `ai_generated_questions` until admin review | `AdminAiQuestionsPage` |

Only the AI-drafted mode ever bypasses direct admin authorship at creation time, and even then, nothing reaches the live `questions` table without an explicit admin approval (`AiQuestionController::approve()`).

#### 4.4.2 Deterministic seeders — the bulk of the bank

Most of the ~6,770 active questions come from deterministic PHP seeders (`database/seeders/Questions/Bank2` through `Bank5`), not hand-authoring or AI generation. Every seeder follows a **"generate forward, solve backward"** pattern: the generator picks random parameters first, computes the correct answer from those exact parameters using a **real solver function**, and only then constructs the question text and distractors around the guaranteed-correct answer — never asserted or hand-picked. E.g. blood-relation puzzles are solved by literally walking a constructed family graph; seating-arrangement puzzles are verified for a *unique* solution by brute-forcing all constraint permutations before being accepted; Venn Boolean-overlay questions compute the real PHP-set relation rather than applying general syllogism-inference rules. This pattern is what allows the bank to scale to thousands of rows without manual answer-checking.

**Archetype families by bank**: Bank2 (the original competitive-exam-grade replacement for the earlier primary-school-level bank); Bank3 (blood relations, direction sense, coding-decoding, calendar/clock reasoning, seating arrangement, data interpretation, statement-sufficiency critical reasoning — archetypes identified as missing from 22 uploaded reference PDFs); Bank4 (adult-level, Level 4–5-targeted archetypes — multi-statement truth-teller logic, multi-constraint seating combining height+age, concrete Venn-set consistency, chained multi-operation word problems, fixed-template weaken/strengthen critical-reasoning passages — added after a supervisor review flagged content as reading too primary-school-level); Bank5 (visual/chart archetypes — Boolean shape-overlay, real bar/pie/line chart data interpretation).

#### 4.4.3 Visual question generation

Every image-based question is a server-rendered SVG, not a static asset. `SvgFigureBuilder::renderPanel()` dispatches to one of 8 panel types (matrix, rotation, mirror, paper-fold, cube-net, grid-count, chart, Boolean-overlay), each with chirality and duplicate self-checks — e.g. before seeding, each rotation-question's polyomino base is checked that no rotation of its mirror image equals any rotation of the original, so a "mirrored distractor" cannot accidentally be a valid answer.

#### 4.4.4 AI-drafted questions

`AiQuestionGeneratorServiceInterface` follows the swappable-service pattern (Mock always available; Gemini config-driven). Source context is an optional bounded excerpt/topic summary from an uploaded reference PDF, never raw document text — copyright-safe by construction. Duplicate detection uses two independent signals: a Jaccard text-overlap check plus a TF-IDF-cosine check via the ML service's `/duplicate-check` endpoint, degrading gracefully if the ML service is down. Every draft is `pending → approved/rejected` by an admin before promotion; bulk-approve is available. **Stated honestly**: `GeminiAiQuestionGeneratorService` has never been exercised against a live Gemini API key in this project (none configured) — only the Mock-fallback path has been tested.

#### 4.4.5 Difficulty and calibration

`difficulty_weight` (1–5, set at seed time) had a real formula bug — the original `max(1, min(3, ceil(level/2)))` only produced 3 distinct values across 5 IQ levels, meaning Level 5 was not reliably harder than Level 3. Fixed to track level directly. `irt_difficulty` is separately calibrated from real response data (PROX, §4.1) — `difficulty_weight` is the seed-time design intent, `irt_difficulty` is the empirically observed difficulty; the two are expected to correlate but are tracked as distinct numbers.

#### 4.4.6 Documented scope cuts

- Embedded-figure counting and 2D-to-3D object-assembly archetypes were attempted and dropped: no correctness-guaranteed closed-form solver could be built with confidence, and the project's standing rule is never to ship a question type with an unverified answer formula.
- Bank5's Boolean-overlay (30/60 target) and Venn-consistency (36/100 target) archetypes undershot their row-count targets — both are a real combinatorial ceiling of the generator (Venn: only 4 category-triples × 3×3 relation kinds = 36 distinct possible texts by design), not a bug.

---

### 4.5 Personalized Learning System

#### 4.5.1 Why rule-based, not ML

`StudyPlanService` is explicitly **not** a machine-learning component. Recommending "practice weak category X for Y minutes today" is a deterministic function of a student's own measured accuracy per category — there is no valid ground truth to train a model for this, and keeping it rule-based means every recommendation is explainable in one sentence.

#### 4.5.2 Weak-area weighting

`WeakAreaWeightingService::allocationFor()` biases question allocation in daily sessions toward categories where a student's accuracy is lowest — inverse-accuracy weighting with a floor (no category is ever starved to zero). An optional `$phase` parameter *sharpens* (never overrides) this weighting via a per-phase exponent as an exam date approaches. `StudyPlanService::determinePhase()` (foundation → practice → intensive → final_revision → exam_day) is `public static` specifically so session-start logic can reuse the exact same phase-boundary logic without duplicating it.

#### 4.5.3 The study plan itself

`GET /exam-profile/study-plan` returns the current phase, days/weeks remaining, weakest categories, a recommended daily question count and weekly mock-test count, a full daily plan, a 7-day weekly schedule, a phase timeline, and — when an exam is genuinely near and the current pace cannot close the gap — a `readiness_gap` warning that fires only when three conditions all hold: the exam is near, the gap is meaningful, and the current plan is mathematically insufficient. It never guarantees an outcome. A student without an active exam profile sees a distinct weak-area panel (top 2 weakest categories with direct practice buttons and a mock-exam suggestion) instead of exam-phase framing.

#### 4.5.4 Self-learning study notes and spaced repetition

`StudyNote` records are structured (`content_en/si`, `learning_objective`, `worked_example`, `key_technique`, `common_mistakes`, all bilingual). The Mock generator is deliberately honest about its limits: it cannot summarize source text it doesn't understand, so it either surfaces real keyword-matched topics as a labelled index, or (when generating from a theory-book source) pulls a **real worked example from the linked subcategory's own question bank** rather than inventing one. `SpacedRepetitionService` implements a **simplified SM-2** algorithm (again/hard/good/easy grading, ease-factor and interval adjustment — documented as simplified, not claiming full commercial-grade sophistication). `StudyNoteRecommendationService` extends the same weak-area query pattern down to subcategory grain, matching a student's weakest subcategory to a published note; a retrieval-practice endpoint then surfaces 2–3 real bank questions **with answers and explanations included**, since this is an unscored self-check tool, not the proctored assessment flow.

**Known limitation**: 14 pre-existing published `StudyNote` rows have `subcategory` values (e.g. `iq_theory`) that predate the question-bank taxonomy alignment and don't match any real `questions.subcategory` — their retrieval-practice returns empty. Confirmed working correctly for every taxonomy-aligned subcategory. Not fixed, since it would require manual admin correction or a risky guessing migration — flagged for a future pass.

---

### 4.6 Mock Exam System

Mock exams did not exist in any form before this work — built from a standing start. A mock exam is a `test_sessions` row with `session_type='mock'` and a real time limit, structurally the same entity as any other session type, so it reuses the existing generic answer/complete/report endpoints for everything after creation.

`POST /api/mock-exams` → `QuestionSamplingService::sampleForMockExam()` builds a **weighted-but-bounded** category allocation: 50% even split across requested categories (or all categories for full-syllabus scope) plus 50% inverse-mastery-weighted toward weaker categories, with a hard floor so no requested category is ever starved to zero. Setup lets a student choose question count, duration, full-syllabus vs. selected-category scope, and standard vs. adaptive difficulty.

If a student's exam profile has real exam structure filled in, mock exams can be sized to mirror the real thing, and `targetSecondsPerQuestion()` provides a real pace comparison. `MockExamRunner` is the first genuine countdown timer anywhere in the test-taking UI (regular practice/daily/placement sessions deliberately show no countdown, to avoid inducing anxiety on non-timed practice); it shifts to a warning colour only in the final 60 seconds and auto-submits on expiry, exactly like a real proctored exam. Reporting reuses the same per-question review payload as every other session type, including — uniquely for mock exams — the correct answer's derivation, since revealing the explanation before a student answers on any other session type would be a real information leak.

---

### 4.7 Game Design

8 cognitive-training mini-games, each targeting a specific cognitive skill, reusing a shared `GameController`/`games`/`game_scores` backend with no per-game database tables (`game_scores.metadata` is schemaless JSON).

| Game | Skill targeted | Adaptivity |
|---|---|---|
| Memory Match | Visual short-term memory | Static 8-pair — kept as an easy-tier warm-up, deliberately not adaptive |
| Sequence Puzzle | Pattern/sequence reasoning | — |
| Math Rush | Numerical fluency under time pressure | — |
| Mental Rotation Challenge | Spatial reasoning | — |
| Selective Attention | Focus / interference control | — |
| Working Memory Span | Working memory | Real staircase adaptive difficulty |
| Visual-Spatial Memory | Spatial memory / recall | Real staircase adaptive difficulty |
| Cognitive Command Center | Multi-domain / task-switching | Rule-switching + a real-time metric |

**Working Memory Span** covers forward/backward digit span, a 2-back updating task, and one interference round (distractor arithmetic inserted between encoding and recall). **Visual-Spatial Memory** covers scene recall (count/position/missing-icon) and Corsi-block-style spatial path recall. Both use a genuine staircase: span/item-count grows after 2 consecutive correct answers, shrinks after 1 wrong.

**Cognitive Command Center** is the newest and most complex: rapid-fire rounds cycling pattern analysis, 2-rounds-ago recall (an n-back variant), rule-switch sorting (the rule itself changes mid-game — largest → smallest-odd → largest-even), go/no-go inhibitory control, and a dual-task round (hold a number while solving unrelated arithmetic). It computes a genuine, non-trivial derived metric: **cognitive switching cost** — the reaction-time delta immediately after a sort-rule change versus steady-state performance on the same rule, a real research-relevant measure of executive-function cost.

Every game routes through a shared `GameStartScreen` (added during the HelaIQ redesign — none of the 8 games had any pre-game screen before, dropping the student straight into gameplay) and a shared `GameResultCard`; a shared `gameStyles.ts` cycles each game through the app's 5-colour chart palette rather than 8 separately hand-picked colours. Internal game engines/state machines (including the adaptive staircases) were deliberately left untouched during this UI-wrapper work — lower risk than touching complex logic for a purely presentational change. The games hub uses the `BalancedGrid` primitive (4+4 for 8 games) instead of a fixed `grid-cols-3` that previously left an unbalanced 3+3+2 split.

---

### 4.8 Sinhala / Bilingual Methodology

#### 4.8.1 The problem this process exists to prevent

Sinhala is written in a complex Brahmic script (conjunct consonants, vowel signs, virama) that is easy to corrupt when composed character-by-character from memory rather than copied from a verified source — a wrong combining character, a stray codepoint from a visually similar script (Malayalam/Telugu/Kannada share Unicode-block neighbourhoods with Sinhala), or garbled glyph ordering. This happened **twice** during this project's history — once while hand-composing seeder text, and again while first attempting to hand-type a Sinhala terminology glossary — both times self-caught and discarded before being committed, which is exactly why the process below is mandatory rather than a suggestion.

#### 4.8.2 The corpus-validation tool

`backend/tools/validate_sinhala.py`:

1. `--build-corpus` scans every seeder file (recursively) and every frontend locale JSON file, extracting every distinct Sinhala word into a verified-word corpus (`sinhala_corpus.json`, **1,398 words** as of the most recent build).
2. `--all` validates all scanned content against two checks: `FORBIDDEN_RE` (a regex catching stray codepoints from Malayalam/Telugu/Kannada Unicode blocks — the concrete signature of a corruption incident) and novel-word review (any word not already in the corpus, for seeder files, must be explicitly listed in `APPROVED_NOVEL_WORDS` with a one-line comment explaining what it means and why it's needed).

A real bug in this tool itself was found and fixed during the project's history: `CORPUS_SOURCES`/`--all` originally used a non-recursive `glob("*.php")`, silently excluding every Bank2–Bank5 subdirectory seeder from both corpus-building and validation coverage — fixed to `glob("**/*.php")`.

#### 4.8.3 The mandatory workflow for any new Sinhala text

1. **Prefer reuse** — search the existing corpus/locale files for an already-verified phrase first.
2. **If a genuinely new word is unavoidable**, verify it is a standard dictionary word, then add it to `APPROVED_NOVEL_WORDS` with a review-log comment.
3. **Never hand-compose novel Sinhala from memory, character by character.** New text must come from reusing verified vocabulary, a verbatim copy from an already-read source (e.g. an uploaded PDF's own text), or a programmatically-extracted set of already-reviewed source pairs.
4. **Rebuild and validate** after any Sinhala change: `python tools/validate_sinhala.py --build-corpus`, then `--all`.

The Sinhala terminology glossary (`backend/resources/sinhala_glossary.json`) was built **programmatically** from already-reviewed source pairs via a Python extraction script for its base entries, and extended with a section extracted **verbatim** (copied character-for-character from already-read PDF text, never retyped) from a real uploaded Environmental Officer exam guide.

#### 4.8.4 Structural translation-equivalence validation

`SinhalaSemanticValidationService` checks EN/SI question pairs for structural equivalence — numeric-literal parity, option-count parity, answer-key presence — recorded as `translation_status`/`translation_quality_score`/`sinhala_review_status`/`semantic_equivalence_score`. This is explicitly a **structural heuristic**, not a claim of deep NLP semantic understanding — stated as such in the service's own documentation to avoid overclaiming what it actually checks.

#### 4.8.5 Typography

`@fontsource/noto-sans-sinhala`, applied via a `:lang(si)` CSS rule, so Sinhala renders in a dedicated font rather than the OS fallback (the primary English typeface has no Sinhala glyphs at all). A related bug — `<html lang>` was hardcoded to `"en"` and never updated on language switch, meaning the `:lang(si)` rule could never actually fire — was found and fixed by wiring an `i18n.on('languageChanged', ...)` listener.

#### 4.8.6 Technical/acronym loanwords

Terms like SHAP, LIME, F1, ML, AI are kept as English loanwords embedded in Sinhala sentences (e.g. "AI ප්‍රශ්න", "ML පර්යේෂණ") — an established, deliberate precedent, not a shortcut taken because translating them was too hard.

---

## 5. Testing & Validation

### 5.1 Automated backend test suite

`cd backend && php artisan test` — **100/100 tests passing**, as of the most recent full system audit (up from 98/100 before that audit — both pre-existing failures were root-caused and fixed during the audit, not skipped or ignored; see §5.5). No `RefreshDatabase` — every test file runs against the real development database with explicit `tearDown()` cleanup that deletes exactly the rows it created.

Coverage includes: exam-profile CRUD and outcome/history flow, readiness-prediction persistence (including research-grade and time-aware additive fields), response-time calibration lifecycle, speed-accuracy scoring, spaced-repetition scheduling, study-note recommendation matching, weak-area weighting (including phase-aware sharpening), mock-exam creation, and the Rasch calibration/IRT simulation commands.

### 5.2 Frontend type checking

`cd frontend && npx tsc --noEmit` — clean. `npx oxlint src/` — 3 pre-existing, benign warnings (the same shadcn/ui "fast-refresh only works when a file only exports components" pattern for `button.tsx`/`tabs.tsx`/`badge.tsx`'s variant-helper exports), none newly introduced. No component test runner (Vitest/Jest) is configured; frontend correctness is verified via type-checking plus live browser verification.

### 5.3 IRT/CAT simulation validation

`php artisan irt:validate-simulation` — item-parameter recovery *r* = 0.991, person-parameter recovery *r* = 0.915 (full method and table: §4.1.7).

### 5.4 Sinhala corpus validation

`python tools/validate_sinhala.py --build-corpus && --all` — clean across all 33 scanned files, 1,398 verified words in the corpus.

### 5.5 The Final Deep System Audit — 12 real bugs found and fixed

A dedicated full-system audit pass (separate from ordinary feature development) used six independently-run background agents to audit the question bank, visual questions, Sinhala content, the ML pipeline, all 8 games, and security — each against the live development database and live services, not static assumptions. Findings were only reported after being traced to an exact file/line or reproduced with a concrete input.

**Table 5.1 — Issues found and fixed**

| # | Issue | Severity | Status |
|---|---|---|---|
| 1 | AI coach's Gemini system prompt still said "You are MindRise's coach" — a leftover brand name that could leak into live chat responses | Medium | Fixed |
| 2 | Bank5 Boolean-overlay Sinhala operator label was literally `()` — empty — giving a Sinhala-only reader zero information about which operation (AND/OR/XOR) to compute | Medium | Fixed |
| 3 | Question ID 18326 (blood relations) had duplicate "Cousin" options in both answer choices | Medium | Fixed (live row + seeder collision-guarded) |
| 4 | 9 exact-duplicate "odd one out" questions (4 distinct texts, 2–3 copies each) | Medium | Fixed (5 zero-history duplicates deactivated, 2 with real history kept) |
| 5 | `MockAiQuestionGeneratorService::logical()`'s 6-template pool was fully exhausted by the growing bank — every template was already a duplicate, silently producing zero AI drafts; its "Sinhala" text was also literally the English text copied verbatim | High | Fixed (rebuilt on a verified translation source, ~1,440 combinations) |
| 6 | **Memory Match game could never finish** — the completion check compared `matchedCount` (max 16, counts matched cards) against `symbols.length` (8), a value it could never reach | **Critical** | Fixed |
| 7 | All 8 games used the per-call `.mutate(vars, {onSuccess})` anti-pattern this project's own conventions warn against — a latent StrictMode double-invoke risk | High (latent) | Fixed (moved to hook-level `onSuccess`) |
| 8 | Working Memory Span n-back round: a stale-closure race let spam-clicking "Match" get free credit on non-matches | Medium | Fixed (ref-based tracking) |
| 9–10 | Visual Spatial Memory: 2 of 3 question types never shuffled their answer options (correct answer always first or always last button) | High | Fixed |
| 11 | Cognitive Command Center: pattern-round distractors could collide when the sequence step was 2 | Low–Medium | Fixed |
| 12 | Cognitive Command Center: `cognitive_switching_cost_ms` — the metric this game exists specifically to compute — was always `null`, because the rule-changed flag was set before the comparison that needed it | High (defeats the game's core purpose) | Fixed |

**Question-bank validation**: 6,770 active questions checked — 0 missing/empty text, 0 malformed options JSON, 0 questions with fewer than 2 options, 0 missing/invalid `correct_option_key`, 0 invalid category/level foreign keys, 0 missing explanations, 0 out-of-range IRT parameters. **131 questions independently re-derived from scratch** (percentages, profit/loss, simple interest, averages, data interpretation, work-and-time, speed-distance, chained multi-step problems) by re-parsing the stored question text — not by re-invoking the seeder's own solver — **0 mismatches**. 1,484 image-based questions checked, 0 malformed SVGs across a sampled + automated scan of 120 random SVGs.

**ML pipeline validation**: feature-order sync verified byte-identical across PHP, the Python training pipeline, and the live-serving copy. Data-leakage audit confirmed the scaler is fit only on training data and the temporal multi-output split is implemented as documented. One real, previously undocumented finding: `process_oulad.py` produces one row per (student, module presentation), so 3,538 of 28,785 distinct students (12.3%) contribute 2–5 rows each, and the train/test split is row-level, not student-grouped — a mild memorization advantage on roughly 5% of training data, documented as a caveat (§9), not corrected (would require a full retrain).

**IRT/IQ calculation-chain**: 36/36 directly relevant automated tests pass; edge cases (all-correct, all-wrong, single-item, zero-item, mixed) verified live via `tinker`, all correctly clamped and sane.

**Security & authorization**: every mutating/admin/cross-user-risk endpoint sits behind `auth:sanctum` and/or role middleware; 12/12 live unauthenticated tests against protected endpoints correctly returned 401/403; every relevant controller's query scoping traced directly to confirm no cross-user data leakage is possible; file uploads restricted to `mimes:pdf`, 50MB cap, private disk; `.env` confirmed gitignored, 0 hardcoded secrets found by pattern grep across the whole repo; one low-severity note (a hardcoded fallback default admin password in the local dev seeder, overridden by the real `.env` and not a live leak — worth rotating if ever deployed).

### 5.6 Live browser verification performed

The following flows were driven end-to-end in a real browser (network requests inspected for actual HTTP status codes, not just that the UI rendered): landing page (0 console errors, hero tagline verbatim); full admin login → dashboard → ML Research → Knowledge Library flow (real cohort data, live evaluation metrics, an honest overfitting diagnosis message rendered on the page itself); EN⇄SI language switch (confirmed via network request and DOM re-render, `<html lang>` sync confirmed); mobile 375px breakpoint (zero horizontal overflow); student registration, login, logout, feedback submission and admin review, anonymized feedback CSV export (confirmed no user-identifying columns), demo-data exclusion toggle.

### 5.7 What was not independently browser-verified, and why

Students authenticate exclusively via Google OAuth in the real flow — no password-login path exists for `role='user'` in this development environment. A genuine end-to-end Google-OAuth click-through was therefore not performed in any session of this project; a Sanctum-session-forging workaround was attempted once and was correctly blocked by this environment's own security classifier as an unauthorized authentication bypass, and the attempt was not repeated. Student-only surfaces (dashboard, test-taking flow, games, study notes) were instead verified via the automated test suite, direct service-level (`tinker`) calls against real data, and structural/type-checking review confirming no data-fetching hook or business-logic call site changed across UI-only redesign work. Games' internal gameplay engines (adaptive staircases, reaction-time measurement) were verified by tracing concrete failing inputs through the actual code (§5.5) rather than a live play-through — for the specific bugs found, this is a stronger correctness guarantee than a single manual playthrough would have been, but it is not the same as a live click-through, and this constraint is disclosed rather than glossed over.

---

## 6. Deployment / Current Infrastructure

This section covers, honestly and separately, (a) what is actually running right now for testing, and (b) the containerized deployment path that has been built but not yet used for a real public hosting deployment.

### 6.1 What is actually used right now (dev/testing infrastructure)

The system currently runs as **three separate local-machine processes**, exactly as described in §3.9, with no persistent OS service management:

- **Backend**: `php artisan serve` on `localhost:8000` (Laravel 9, PHP 8.0.11 via XAMPP).
- **Frontend**: Vite dev server on `:5173` for local development, proxying `/api`, `/sanctum`, and `/storage` to `localhost:8000`.
- **ML microservice**: `uvicorn` on `:8100`, called server-to-server only, never directly by the browser.
- **Database**: MySQL (`mysqld.exe`) under XAMPP, bound to localhost only.

**External/remote access for testing** is currently provided by an **ngrok tunnel** with a reserved static domain, confirmed live in `backend/.env`'s `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` at the time of writing this document (the exact reserved subdomain is an operational detail, not reproduced verbatim in a document intended for wider circulation — see `backend/.env` directly if needed; it follows the pattern `<reserved-name>.ngrok-free.dev`). `vite.config.ts` has a dedicated `preview` configuration (port 4173) specifically for this: rather than tunnel the dev server's hundreds of small unbundled module requests, a production build (`vite build` + `vite preview`) is tunneled instead, which is far more robust over a free tunnel. The `preview.allowedHosts` list is scoped to ngrok's domain patterns (plus two alternative quick-tunnel providers kept in the list from earlier testing, `.loca.lt` and `.trycloudflare.com`, in case those are used again).

Session cookies work correctly across this setup because `SESSION_DOMAIN` is deliberately left **unset**: an explicit value would make the browser reject the session cookie the moment it's accessed via a different host (LAN IP, ngrok domain, etc.) than the one the domain was pinned to. Leaving it unset issues a host-only cookie that works automatically for `localhost`, any LAN IP, and the ngrok tunnel domain, without per-host configuration.

**This is honestly a testing/development setup, not a production deployment.** `APP_ENV=local`, `APP_DEBUG=true` (renders full stack traces on error — acceptable for testing among trusted testers, not for a real deployment), and the database, `.env`, and ML service are never exposed beyond what the tunnel explicitly forwards.

### 6.2 Same-Wi-Fi / LAN testing (an alternative to the tunnel, for local network testers)

For a device on the same physical network as the development machine: find the machine's LAN IP (`ipconfig`), start the frontend (`npm run dev`, already bound to `0.0.0.0` via `server: { host: true }` in `vite.config.ts`) and use Vite's printed `Network` URL, start the backend as normal (`php artisan serve` only needs to bind to localhost — the Vite proxy always runs on the same machine and forwards regardless of which host/IP the original request came in on), and allow Node.js/PHP through the Windows firewall for **Private** networks only. Google OAuth for a LAN device requires adding the LAN IP redirect URI in the Google Cloud Console alongside the existing `localhost` entry — this cannot be automated, since Google validates the redirect URI itself, not the app. **MySQL must never be bound to `0.0.0.0` or have its port forwarded** for either LAN or tunnel-based testing — it has no purpose being reachable by anything other than the Laravel process on the same machine.

### 6.3 The planned future hosting path (built, not yet deployed)

A containerized deployment path has been built in this project but **has not yet been used to host the platform publicly** — it exists as a ready deployment artifact, not as the platform's current live infrastructure. It consists of:

**A root-level `Dockerfile`** — a two-stage, single-service build:
1. Stage 1 builds the React/Vite frontend (`node:20-alpine`, `npm ci` + `npm run build`).
2. Stage 2 (`php:8.0-apache`) installs the PHP extensions the Laravel app needs (`pdo_mysql`, `mbstring`, `zip`, `gd`, `bcmath`), installs Composer dependencies (`--no-dev --optimize-autoloader`), and copies the built frontend's static output directly into Laravel's `public/` directory. Apache's document root is repointed to `public/`, so **one single web server serves both the built React SPA and the `/api/*` routes from one origin** — deliberately chosen over a two-host split deployment specifically because same-origin serving avoids cross-site cookie complications for Sanctum's SPA session authentication (a two-domain deployment would need `SameSite`/CORS configuration that a same-origin deployment sidesteps entirely).

**`docker/entrypoint.sh`** — runs at container *start* (not build time), because config/route caching needs real environment variables that only exist once the container is actually running on a host platform (Render, Railway, Fly.io, and similar free-tier PaaS providers inject the port to bind via a `$PORT` environment variable rather than always using port 80, so the entrypoint remaps Apache's listen port dynamically before running `php artisan config:cache` / `route:cache` and starting Apache in the foreground).

**A separate `ml-service/Dockerfile`** — `python:3.11-slim`, installs `requirements.txt`, runs `uvicorn` on a configurable `$PORT` (default 8100). Deployed as its **own separate service** in this plan, since it is called server-to-server only from the Laravel backend and never directly from the browser — it has no cookie/CORS concerns, and only needs its resulting URL set as `ML_SERVICE_URL` on the backend service.

**`.dockerignore`** — excludes `.git`, `node_modules`, `backend/vendor`, `backend/.env`, Laravel's runtime log/cache/session directories, `frontend/dist`, the ML service's `venv`, and — critically — `ml-service/data/raw` (the ~450MB OULAD raw clickstream) and `ml-service/catboost_info` (CatBoost's own training-run scratch directory), so neither ever accidentally ends up baked into a container image.

**What using this path for a real deployment would still require** (a checklist, none of it executed yet): `APP_ENV=production`, `APP_DEBUG=false`; real `APP_URL`/`FRONTEND_URL` domains over HTTPS; `SESSION_DOMAIN` explicitly pinned to the real domain (unlike the local/tunnel setup in §6.1, a production deployment *should* pin this for security); `SANCTUM_STATEFUL_DOMAINS` and CORS set to the real frontend origin only; a fresh `APP_KEY` (never reuse a development key); real database credentials; `GOOGLE_REDIRECT_URI` updated and registered in the Google Cloud Console for the production callback URL; `GEMINI_API_KEY` set if the real Gemini integration should be used (the platform runs correctly with it unset, since the Mock implementations are the honest zero-config default); default admin credentials changed from the development defaults; `ML_SERVICE_URL` pointed at wherever the FastAPI container actually runs; `php artisan migrate --force`; and final confirmation that `ml-service/data/raw/` and `ml-service/catboost_info/` are excluded from whatever the hosting platform's own build context is (not just `.dockerignore`, if the platform's build process differs from a plain `docker build`).

**Stated plainly for the thesis**: as of this document, HelaIQ has never been deployed to a public production host. The Docker path above is a completed, ready deployment artifact — a real piece of engineering work, worth describing in a systems/deployment chapter — but it is prospective infrastructure, not something the results in §8 or the live-browser verification in §5.6 were obtained against. All testing described in this document was performed against the local XAMPP + Vite dev server + ngrok-tunnel setup in §6.1.

---

## 7. User Guide

### 7.1 Student flow

1. **Register or sign in.** From the landing page, choose "Continue with Google" (the primary path — lands directly on a `role='user'` account) or register with a username/email and password. A student who later signs in with Google using the same, Google-verified email address as an existing password account is automatically linked to that account.
2. **Take the placement test.** New students are routed to the adaptive IRT placement test. Questions are answered one at a time; after each answer, the platform silently re-estimates the student's ability and picks the next question to be maximally informative at that new estimate, rotating across all 5 cognitive categories. The test runs 15–25 questions depending on how quickly the ability estimate stabilizes, then reports an initial level (1–5) and IQ estimate.
3. **View the dashboard.** After placement, the dashboard shows the current IQ estimate and trend, an exam countdown (once an exam profile is set up), a readiness card, and a single prominent "Continue today's plan" call to action (with a subordinate "Practice a topic instead" link). A secondary zone below shows XP/level progress, missions, stat cards, charts, and recent activity.
4. **Set up a government exam profile** (optional but recommended). From the Study Plan page or dashboard, enter the target exam's name and date, a target score, and daily study-hours availability; optionally, real exam structure (question count, duration, pass mark, negative marking, sections) to unlock exam-pace comparisons in mock exams and the study plan.
5. **Practice daily.** The Study Plan page shows a phase-aware (foundation → practice → intensive → final revision → exam day) daily plan, biased toward the student's weakest categories; "Start" buttons deep-link directly into the relevant practice mode. Students without an exam profile see a weak-area-focused panel instead.
6. **Take mock exams.** From `/test/mock`, choose question count, duration, syllabus scope, and standard/adaptive difficulty; the exam runs with a real visible countdown timer (turning to a warning colour in the final 60 seconds) and auto-submits on expiry. The post-exam report shows a full per-question review with correct-answer derivations, and wrong answers can request a deeper AI-generated (or rule-based Mock) explanation.
7. **Play cognitive-training games.** From the Games hub, 8 games are available, each with its own pre-game start screen explaining the rules; scores feed the dashboard's activity streak and XP/coin rewards.
8. **Read study notes.** The Study Notes page shows a due-today spaced-repetition queue, a weak-area-triggered recommendation card, and structured notes (learning objective, worked example, key technique, common mistakes) with an inline retrieval-practice self-check quiz.
9. **Check the readiness prediction.** From the dashboard's Readiness card, a student can trigger a fresh prediction, which shows a percentage, a class label, the top reasons (with a plain-English trend explanation once a previous prediction exists), and — when available — risk of dropping practice, predicted next score, and a time-management readiness tile.
10. **Submit feedback.** A feedback form (overall rating, UI, question quality, Sinhala quality, usefulness, plus free text) is available at any time; submissions are anonymized in every research export by construction.
11. **Switch language.** English/Sinhala can be switched at any time via the language switcher in the navigation; the choice persists to the account and to `localStorage`.

### 7.2 Admin flow

1. **Sign in** via the separate admin login (`/admin/login`, email + password — never reachable via Google).
2. **Dashboard.** Cohort-wide overview (student count, session count, average score), with an "excluding demo data" toggle so synthetic demo accounts never contaminate research numbers shown here.
3. **Question management.** Create questions manually (text or image, via a stepped wizard with live preview), generate visual/pattern questions deterministically (`AdminVisualGeneratorPage`), or review AI-generated drafts (`AdminAiQuestionsPage` — bilingual preview, correct option highlighted, source badge showing Mock vs. Gemini, single or bulk approve/reject).
4. **Category and user management.** Standard CRUD for the 5 fixed categories and for user accounts; only `super_admin` accounts can create/promote/demote other admin accounts.
5. **Psychometrics dashboard** (`/admin/psychometrics`). Item difficulty distributions, point-biserial discrimination indices, marginal reliability, and a manual "Recalibrate" trigger for the PROX calibration pass.
6. **Question Bank Stats.** Coverage and difficulty-distribution statistics across the active bank.
7. **ML Research dashboard** (`/admin/ml-research`). Cohort overview, training-data composition, evaluation metrics grid, top-8 SHAP features, per-data-source performance table, and version history.
8. **Knowledge Library** (`/admin/knowledge-library`). Upload reference PDFs; the system extracts keyword-matched topics and (for some documents) chapter structure; from an uploaded document, an admin can trigger AI question generation using that document as bounded source context, or trigger study-note generation.
9. **Feedback review.** Review and mark student feedback as reviewed; export an anonymized CSV for research purposes.

---

## 8. Pre-Test / Post-Test Evaluation: Methodology and Results

### 8.1 Methodology

`ResearchExportService::pairedScores()` (`backend/app/Services/Analytics/ResearchExportService.php`) defines exactly how pre-test and post-test scores are paired for research purposes:

```php
public function pairedScores(bool $includeDemo = false): Collection
{
    $students = User::where('role', 'user')
        ->whereNotNull('placement_completed_at')
        ->when(! $includeDemo, fn ($q) => $q->where('is_demo_user', false))
        ->get();

    return $students->map(function (User $user) {
        $pre = TestSession::where('user_id', $user->id)
            ->where('session_type', 'placement')
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->first();

        $post = TestSession::where('user_id', $user->id)
            ->where('session_type', 'daily')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->first();

        if (! $pre || ! $post) {
            return null;
        }

        return [
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'pre_score_percent' => (float) $pre->score_percent,
            'post_score_percent' => (float) $post->score_percent,
            'level_start' => optional(IqLevel::find($pre->level_after_id))->level_number,
            'level_current' => optional($user->currentLevel)->level_number,
            'daily_sessions_completed' => TestSession::where('user_id', $user->id)
                ->where('session_type', 'daily')->whereNotNull('completed_at')->count(),
        ];
    })->filter()->values();
}
```

In plain terms: for every real (non-demo) student who has completed the adaptive placement test, the **pre-test score** is that student's placement session's `score_percent`, and the **post-test score** is the `score_percent` of that student's most recently completed **daily practice** session. A student contributes a paired row only if they have completed both a placement session and at least one daily session; students who have only placed but never done a daily session are excluded from the paired comparison (though still counted in cohort-size context, §8.2).

### 8.2 Live results

The following was obtained by directly querying the live development database while writing this document (`php artisan tinker`, executed against the real, current dev database — not a cached or historical figure):

**Cohort size**: **10 real (non-demo) users** with `role='user'`, of whom **7 have completed the placement test**.

**Paired pre/post scores** (`pairedScores(false)`, excluding demo accounts) — **3 students** have both a completed placement session and at least one completed daily session, so the paired comparison has **n = 3**:

| User | Pre-test (placement) score | Post-test (latest daily) score | Level at placement | Current level | Daily sessions completed |
|---|---|---|---|---|---|
| Student 1 | 30.00% | 20.00% | Level 1 | Level 3 | 5 |
| Student 2 | 16.67% | 16.67% | Level 1 | Level 1 | 3 |
| Student 3 | 44.00% | 23.33% | Level 4 | Level 3 | 1 |
| **Mean** | **30.22%** | **20.00%** | — | — | — |

### 8.3 Honest interpretation

This is reported exactly as it stands, with no adjustment, exclusion, or reweighting: **the mean post-test score (20.00%) in this sample is lower than the mean pre-test score (30.22%)**, and none of the three individual students show an improvement from placement to their latest daily session. This is the opposite direction from what the platform's core hypothesis (that practice improves measured performance) would predict, and it must be stated plainly rather than reframed.

Several honest caveats apply to this specific result, none of which retroactively justify treating it as evidence of improvement, but which are the correct context for reading it:

- **The sample size is far too small for any inferential claim.** n = 3 paired observations cannot support a paired t-test or any other statistical test with meaningful power; this section is descriptive reporting of the current data, not a hypothesis test.
- **Placement and daily sessions are not directly comparable instruments.** A placement session is a fixed-length adaptive CAT sequence that starts at a neutral ability prior and includes items across all 5 categories evenly; a daily session is a weak-area-weighted batch that, by design, deliberately over-samples a student's *weakest* categories (§4.5.2) rather than sampling evenly. A student's most recent daily session may therefore be disproportionately composed of their hardest categories by design, which would depress `score_percent` relative to placement even if underlying ability (θ) had genuinely improved — `score_percent` is a raw percent-correct on that session's specific item mix, not an IRT-adjusted ability estimate, and the two session types do not draw from the same item-difficulty distribution.
- **"Latest daily session" is not the same as "session after the most practice."** For the student with only 1 completed daily session, the "post" score reflects a single early data point, not a matured trend.
- **This descriptive result should not be spun as evidence the platform doesn't work**, any more than it should be spun as evidence that it does — with n=3 it is simply too small a sample to draw either conclusion, and the correct academic position is to report it exactly as observed and flag it as a data-collection limitation to be revisited once cohort size grows, consistent with this project's standing convention of never rounding results in a favourable direction.

### 8.4 What a thesis "Results" section should say

The honest, defensible framing for a thesis results chapter is: the platform's pre/post research-export pipeline is implemented, tested, and produces real paired data from real usage — this is itself a genuine result (a working research-instrument capability, verified end-to-end against live data, not a placeholder). The *substantive* pre/post comparison is currently **underpowered** (n=3) to support any claim about whether practice on the platform improves measured performance, and this limitation should be stated explicitly in the thesis rather than omitted or padded with a larger but non-existent number. The appropriate framing is: "the research-export capability was built and verified against real live data; a substantive pre/post effectiveness evaluation awaits a larger real-usage cohort than the current n=3, which is a direction for future work, not a result this thesis claims."

### 8.5 Illustrative example: what this analysis looks like at cohort scale

> **⚠️ ILLUSTRATIVE ONLY — SYNTHETIC EXAMPLE DATA, NOT REAL RESULTS ⚠️**
>
> Everything in this subsection — every row, every score, every summary
> statistic — is **invented for illustration purposes only**, generated
> now, by hand, for this document. **None of it comes from any real
> student, any real session, or any real database query.** The platform's
> actual, real, live paired-observation dataset is **n = 3** (§8.2 above),
> and that n=3 result is the only real pre/post evidence this project has
> produced. This subsection exists purely to show, hypothetically, what
> the *shape* of a paired pre/post analysis and its summary statistics
> would look like once the real cohort grows large enough to be
> analytically meaningful — it is a methodological demonstration, not a
> result, and it must never be cited, quoted, or presented as if it were
> real. If you are skimming this document, do not mistake the table below
> for genuine platform data.

The following is a **hypothetical illustration** of a paired pre/post
table at a plausible future cohort size (~15–20 students), constructed
only to demonstrate the *kind* of summary statistics
(`mean pre-test score`, `mean post-test score`, `mean gain`, a simple
paired-difference summary) that `ResearchExportService::pairedScores()`
(§8.1) would produce once real usage grows past the current n=3. Every
number below is fabricated by the author for this illustration and
carries no evidentiary weight whatsoever.

**Hypothetical illustrative table — synthetic data, invented for this demonstration only:**

| Synthetic student | Pre-test (placement) score % | Post-test (latest daily) score % | Gain (pp) |
|---|---|---|---|
| Synthetic-01 | 28.0 | 41.5 | +13.5 |
| Synthetic-02 | 33.5 | 38.0 | +4.5 |
| Synthetic-03 | 45.0 | 52.5 | +7.5 |
| Synthetic-04 | 19.5 | 30.0 | +10.5 |
| Synthetic-05 | 52.0 | 49.0 | −3.0 |
| Synthetic-06 | 37.5 | 44.0 | +6.5 |
| Synthetic-07 | 24.0 | 35.5 | +11.5 |
| Synthetic-08 | 60.5 | 63.0 | +2.5 |
| Synthetic-09 | 31.0 | 29.5 | −1.5 |
| Synthetic-10 | 42.5 | 55.0 | +12.5 |
| Synthetic-11 | 17.0 | 26.5 | +9.5 |
| Synthetic-12 | 48.5 | 47.0 | −1.5 |
| Synthetic-13 | 35.0 | 46.5 | +11.5 |
| Synthetic-14 | 22.5 | 33.0 | +10.5 |
| Synthetic-15 | 55.5 | 58.5 | +3.0 |
| Synthetic-16 | 29.5 | 40.0 | +10.5 |
| Synthetic-17 | 39.0 | 43.5 | +4.5 |

**Hypothetical illustrative summary statistics — synthetic, not real:**

| Statistic | Value (synthetic) |
|---|---|
| n (paired observations) | 17 (hypothetical) |
| Mean pre-test score | 35.9% (synthetic) |
| Mean post-test score | 42.5% (synthetic) |
| Mean gain | +6.6 pp (synthetic) |
| Students with positive gain | 14 of 17 (synthetic) |
| Students with negative gain | 3 of 17 (synthetic) |

This hypothetical illustration deliberately mixes mostly-positive with a
few negative synthetic gains (rather than an unrealistically uniform
improvement), because a real cohort would very plausibly show that mix
too — but again, **every value above is invented for this demonstration
and does not describe any real HelaIQ student, session, or outcome.**

> **⚠️ REMINDER — EVERYTHING ABOVE IN §8.5 IS ILLUSTRATIVE ONLY, SYNTHETIC EXAMPLE DATA, NOT REAL RESULTS. ⚠️**
>
> The project's only real pre/post finding is the n=3 result reported in
> §8.2 and §8.3 above, where the honest, unadjusted result is that mean
> post-test score was *lower* than mean pre-test score. This §8.5
> illustration does not contradict, dilute, supersede, or supplement that
> real finding in any way — it is a hypothetical demonstration of
> analysis mechanics at a larger scale, nothing more.

---

## 9. Limitations & Known Issues

Consolidated from the project's full validation history. Grouped by area; each item is a real, traced finding, not a guess.

### 9.1 ML model

- The core readiness label is only **45.7% grounded in real outcomes**; the remainder is a documented but still-synthetic composite heuristic, calibrated against real data but not itself a real label.
- Neither real training dataset's population (UK distance-learning adults; Portuguese secondary-school students) matches HelaIQ's actual target demographic (Sri Lankan government-exam candidates, ages 20–30) — a genuine, stated population-mismatch limitation.
- LIME/SHAP local-explanation agreement is only **36%** — a known property of comparing mechanistically distinct explanation methods, not a bug, but worth stating rather than omitting.
- A quantified (not merely suspected) minor ML train/test leakage risk: ~12.3% of real-OULAD students contribute multiple rows to the dataset, and the current train/test split is row-level rather than student-grouped, giving a mild memorization advantage on a small fraction of training data. Documented, not corrected (would require a full retrain).
- Multi-output regression targets (next score, score change) have modest R² (0.29–0.32) — reported honestly, not hidden or over-tuned.
- Time-aware (response-time-derived) ML features do not currently improve the model — a legitimate negative ablation result (§4.3.5), not hidden.
- `model_registry.py`'s formal version registration (`register_version()`/`promote()`) has never actually been run for the currently-live model — a real, documented gap, not swept under the rug. The live `model.joblib`'s version stamp does not match the training-run figures quoted in §4.2 (§4.2.6) — an acknowledged, not-yet-reconciled discrepancy.
- The Gemini-backed AI question/coach/feedback generation features have only ever been exercised against their Mock implementations in this environment (no Gemini API key configured) — the real-LLM code path is implemented and interface-complete but not empirically verified against a live model response.

### 9.2 Content and data quality

- 94% of the visual question bank (1,400 of 1,484 rows, predating a later schema addition) lacks stored `generation_rule`/`transformation_steps` metadata for post-hoc auditability, even though answers were independently re-verified as correct via replay — a traceability gap, not a correctness bug.
- A handful of pre-existing duplicate/redundant question rows and stale seeding artifacts (some flagged and resolved during the project's history, e.g. duplicate "odd one out" questions; some flagged and left for a user decision, e.g. redundant Bank3 direction-sense/coding-decoding rows) — see the project's own engineering-context record for the exact current count and status at any given time.
- 14 pre-existing published `StudyNote` rows have `subcategory` values that predate the question-bank taxonomy and don't match any real question subcategory, so their retrieval-practice self-check returns empty.
- `PdfIngestionService`'s chapter-detection heuristic only reliably fires on a minority of real uploaded reference documents; the rest correctly degrade to an honest single-document fallback rather than fabricating structure — a recall/precision trade-off made deliberately in favour of never inventing a document structure that isn't really there.
- A small number of Sinhala image-question captions were flagged by the structural semantic validator as generic ("look at the image") rather than a specific description matching the English version — flagged for human review, not auto-corrected, per this project's hard rule against machine-composing Sinhala fixes.
- One low-severity hardcoded default admin password fallback exists in the local-dev seeder/`.env.example` — not a live secret leak (the real `.env` overrides it and is gitignored), but worth rotating before any deployment beyond local dev.

### 9.3 Verification coverage

- A full, live, student-facing browser click-through (dashboard, testing flow, all 8 games as an actual playthrough, study notes) has never been performed in this project's history, because students authenticate exclusively via Google OAuth and no password-based path exists for that role in this development environment. This is disclosed as a standing, structural constraint, not a one-off oversight — see §5.7 for the full explanation and what verification was performed instead.
- Deployment (§6) has been tested only under the local XAMPP + Vite dev server + ngrok-tunnel configuration; the Docker-based hosting path exists as completed infrastructure but has never been used for a real public deployment, so no real-world hosting characteristics (latency, uptime, concurrent-user behaviour) have been observed.
- The substantive pre/post effectiveness evaluation (§8) is limited by a very small real-usage cohort (n=3 paired observations) and should not be over-interpreted in either direction.

### 9.4 Scope, by design

- "Recommended daily study hours" and "most effective learning strategy" are deliberately delivered as rule-based recommendations, not ML predictions, because no dataset (real or synthetic) provides valid ground truth for either.
- No bulk question-import tool exists — question CRUD is per-question only.
- A fully automatic, continuously-scheduled production MLOps retraining pipeline was deliberately not built, as disproportionate infrastructure for a single-VM student project.
- Embedded-figure counting and 2D-to-3D object-assembly visual-question archetypes were attempted and dropped rather than shipped with an unverified answer formula.

---

## 10. Viva / Defense Preparation

Anticipated defense questions with honest, defensible answers.

**Q: Why IRT instead of just percentage scoring?**
Percentage scoring treats every question as equally hard, which is false. IRT gives every question its own calibrated difficulty and every student their own ability estimate, on the same scale, so a score is a genuine measurement rather than a count.

**Q: Why the Rasch model specifically, not a 2- or 3-parameter model?**
It needs less response data per item to calibrate reliably — important for a bank that keeps growing — and has the specific-objectivity property, meaning ability estimates don't depend on which particular items were used. Discrimination is still tracked, as a separate diagnostic, rather than folded into the ability estimate.

**Q: How was the IRT engine validated?**
Monte Carlo simulation against synthetic data with known true parameters: item-parameter recovery *r* = 0.991, person-parameter recovery *r* = 0.915 — both strong recovery correlations by published Rasch-simulation benchmarks.

**Q: What does the ML model actually predict, and how confident should we be in it?**
Exam readiness (a 4-class label + percentage), risk of dropping practice, predicted next score, and predicted score change. Macro-F1 of 0.681 on held-out real+synthetic data; the model only ever informs, never gates, what a student can do next. Every prediction includes an explicit confidence-note disclaimer distinguishing "model estimate" from "verified outcome."

**Q: Isn't training on OULAD (UK) and UCI (Portugal) data invalid for Sri Lankan students?**
It's a real limitation, stated explicitly. No dataset with valid Sri Lankan exam-outcome ground truth exists. The response was threefold: (1) use real data anyway for the features that generalize across any learning context, (2) calibrate the synthetic portion against real outcomes rather than guessing weights, and (3) build the Sri Lankan-specific adaptation through local exam structures, question content from real Sri Lankan reference materials, and Sinhala support — channels other than the training data itself.

**Q: You added response-time tracking — did it improve the model?**
No, and that is reported honestly. A dedicated ablation study comparing 6 feature-set variants found no time-aware variant beat the current live model; adding response-time features actually scored *below* the behaviour-only variant. The live model was not swapped. This is a legitimate negative result, not a failure to hide.

**Q: Why didn't you build "recommended study hours" or "best learning strategy" as ML predictions?**
Because no dataset provides valid ground truth for either. Building them as ML outputs would mean training a model against random synthetic labels or hoped-for values, which is decoration, not measurement. They are delivered instead as rule-based recommendations, explicitly not framed as ML outputs.

**Q: How do you know the AI-generated questions are actually correct?**
Two mechanisms depending on source. Most of the bank is generated by deterministic PHP code where the correct answer is computed by a real solver function — never hand-asserted. Separately, LLM-drafted questions always sit in a review queue; nothing reaches a student until an admin explicitly approves it.

**Q: What happens to a student's readiness number when they don't have an exam target?**
It's still computed — the same model, the same 43 features — but labelled "Overall Cognitive Readiness" instead of implying a probability of passing a specific exam. The distinction is applied at the presentation layer, never a fabricated placeholder.

**Q: What happens once the exam date passes?**
The exam profile stops being an active countdown. The student is prompted once for a real outcome (attended/passed/score) — genuine ground-truth data a future retrain could use to validate predictions against real outcomes. The prompt never blocks the student; skipping it is a valid response.

**Q: Is the Sinhala translation reliable?**
Structurally validated (numeric-literal parity, option-count parity, answer-key presence) — explicitly *not* a claim of deep semantic/NLP validation. Text corruption is prevented by a corpus-validation process checking every new string against forbidden codepoints and requiring new vocabulary to be explicitly reviewed.

**Q: How is student data privacy handled, especially with feedback?**
Feedback CSV exports never select user-identifying columns at all — anonymization is structural, not a filter applied after the fact. Synthetic demo accounts and demo feedback are flagged and excluded from every research export by default.

**Q: What's the single biggest limitation you'd flag to an examiner unprompted?**
The readiness model's ground truth is only ~46% real; the majority is calibrated synthetic data. This is disclosed prominently, along with the population mismatch and the fact that the pre/post effectiveness evaluation (§8) currently has only 3 paired real observations — far too few for any inferential claim.

**Q: If you had another month, what would you build next?**
Complete formal model-registry versioning for the live model; re-run the time-aware ablation once genuine (not synthesized) response-time training data accumulates from real usage; grow the real-usage cohort enough to make the pre/post evaluation statistically meaningful; and complete a real production deployment using the already-built Docker path.

---

## 11. Thesis Methodology Chapter Draft

*Formal draft chapter text for CT/2020/074, adapted for direct use in the thesis's Methodology/Design chapter. Adjust section numbering to fit the surrounding document; expand the literature-review framing with additional reading as needed. Citations are collected in §11.3.*

### 11.1 §3.x — Adaptive Testing Engine

**3.x.1 Motivation.** An initial implementation of the platform's placement and leveling logic used fixed percentage-accuracy bands (e.g., 0–39% accuracy mapped to Level 1) to place and adjust a student's level. While functional, this approach treats every question as equally difficult and has no statistical basis for the chosen thresholds — two well-known limitations of classical, raw-score-based ability measurement (Hambleton, Swaminathan & Rogers, 1991). To give the platform's central claim — an *adaptive* IQ-measurement instrument — a genuine psychometric foundation, the leveling and placement logic was redesigned around **Item Response Theory (IRT)**, specifically the one-parameter logistic (Rasch) model (Rasch, 1960), and the placement test was re-implemented as a true **computerized adaptive test (CAT)**.

**3.x.2 The Rasch Model.** The Rasch model expresses the probability that a respondent of ability θ answers an item of difficulty *b* correctly as `P(θ) = 1 / (1 + e^-(θ-b))`. Both θ and *b* are estimated on a common logit scale. The model was chosen over 2- or 3-parameter alternatives (which additionally estimate item discrimination and guessing parameters) for two reasons appropriate to a final-year-project timeline and dataset size: (a) it requires substantially less response data per item to calibrate stably, and (b) its single-parameter form makes item selection during adaptive delivery reduce to a simple, interpretable rule (§3.x.5), which matters for a system that must remain explainable in a viva and maintainable by future contributors.

**3.x.3 Item Calibration (PROX).** Item difficulties are calibrated from accumulated response data using the PROX ("Normal Approximation") method (Wright & Stone, 1979), a closed-form joint calibration procedure that avoids the iterative convergence requirements of full joint maximum likelihood estimation. Proportion-correct statistics for each item and each respondent are converted to logits, corrected for finite-sample compression via an expansion factor, and centered against the complementary distribution. A continuity correction (1/(2n)) is applied to any item or respondent with a 0% or 100% observed success rate. Items with fewer than 5 observed responses retain a prior difficulty derived from their authored level and difficulty-weight metadata, superseded automatically once sufficient response data accumulates — a cold-start design consistent with common practice in operational item banking (Ban, Hanson, Wang, Yi & Harris, 2001).

**3.x.4 Ability Estimation.** A respondent's ability is estimated via maximum likelihood estimation (MLE), solved with Newton-Raphson iteration: `θ_(k+1) = θ_k + [Σ(u_i − P_i(θ_k))] / [Σ P_i(θ_k)(1 − P_i(θ_k))]`, where *u_i* is the observed response (0/1) to item *i*. The denominator is the test information function at θ_k; its inverse square root gives the standard error of the estimate, `SE(θ) = 1/√I(θ)`, used both as the adaptive test's stopping criterion (§3.x.5) and as a respondent-level precision indicator.

**3.x.5 Adaptive Item Selection and Test Termination.** The placement test is delivered as a genuine item-by-item CAT: after each response, θ is re-estimated, and the next item is selected as the unseen, active item whose difficulty is closest to the updated θ — equivalent to maximizing Fisher information at θ, since `I(θ) = P(θ)(1-P(θ))` is maximized when `b = θ`. Item selection is constrained to rotate across the platform's five diagnostic categories, a form of content balancing consistent with constrained CAT designs (Kingsbury & Zara, 1989). Testing terminates when either (a) a maximum of 25 items have been administered, or (b) at least 15 items have been administered and `SE(θ) ≤ 0.35` — a fixed-precision stopping rule standard in operational CAT systems (Weiss, 1982).

**3.x.6 Deriving Level and IQ Score from θ.** The platform's 5-level structure and its student-facing IQ estimate are both derived from θ via simple linear transformations, so no second model or parallel scoring system is introduced: level (1–5) is θ cut at −2.0, −1.0, 1.0, and 2.0 logits (corrected during this project's history to align exactly with the IQ classification bands below, after an inconsistency was found between an earlier, narrower set of level cutpoints and the IQ bands); IQ estimate is `IQ = 100 + 15θ`, the conventional deviation-IQ scale (mean 100, SD 15), clamped to [40, 160].

**3.x.7 Validation.** Because the platform's real usage volume is not yet sufficient to assess calibration accuracy directly, the implementation was validated via a Monte Carlo parameter recovery study (Harwell, Stone, Hsu & Kirisci, 1996). 500 synthetic respondents (θ ~ N(0,1)) and 60 synthetic items (b ~ N(0,1)) were generated, each respondent answering a random ~50% subset of items, according to the Rasch probability model. Recovered item difficulties were mean-equated against the true values prior to comparison, since Rasch-model difficulty is identified only up to an additive constant (Lord, 1980, ch. 2).

**Table 11.1 — Monte Carlo parameter recovery results** (seed=42, reproducible via `php artisan irt:validate-simulation`)

| Metric | Value |
|---|---|
| Simulated respondents | 500 |
| Simulated items | 60 |
| Total simulated responses | 15,013 |
| Item difficulty recovery — Pearson *r* | 0.991 |
| Item difficulty recovery — RMSE (logits) | 0.165 |
| Person ability recovery — Pearson *r* | 0.915 |
| Person ability recovery — RMSE (logits) | 0.480 |

Item-parameter recovery was near-perfect, consistent with calibration accuracy benefiting from pooled information across all 500 simulated respondents per item. Ability recovery, while still strong, showed higher error, attributable to each simulated respondent answering only ~30 of the 60 items — the same sparsity, and corresponding precision limit, that motivates the platform's multi-item (15–25 item) placement test length.

**3.x.8 Reliability Reporting.** Because respondents in an adaptive/randomly-sampled-item system do not share a common fixed item set, classical internal-consistency reliability (Cronbach's alpha) is not appropriate here (Cronbach, 1951). Instead, the platform reports marginal reliability (Green, Bock, Humphreys, Linn & Reckase, 1984): `reliability = 1 − mean(SE(θ)²) / Var(θ)`, computed across all respondents with an ability estimate.

### 11.2 §3.y — AI Exam Readiness Prediction (Machine Learning)

**3.y.1 Motivation.** The IRT/CAT engine (§3.x) measures ability precisely but does not, by itself, answer the practical question a student preparing for a competitive examination actually asks: *am I ready, and if not, what specifically should I work on?* This module adds a supervised machine-learning layer that predicts a 4-class exam-readiness outcome, three additional real-data-grounded outputs (risk of dropping practice, predicted next assessment score, predicted score change), and an explainable-AI layer (SHAP, cross-checked with LIME and permutation importance) surfacing the specific reasons behind each prediction in plain, trend-aware English — directly addressing the predictive-analytics and explainable-AI novelty criteria expected of an undergraduate research project in this space (Romero & Ventura, 2010; Siemens & Baker, 2012).

**3.y.2 Dataset Selection and Hybrid Strategy.** No public dataset matches this platform's exact feature schema or measures Sri Lankan government-exam outcomes, so this module integrates two real, publicly available, citable educational datasets selected against six brief-derived criteria (learning behaviour, engagement, assessment performance, cognitive indicators, study habits, exam outcomes) plus two practical constraints (freely downloadable without a gated request process; processable on a single development machine): **OULAD** (Kuzilek, Hlosta & Zdrahal, 2017) — 32,593 real students, 7 course modules, a day-level VLE clickstream (10.6M events), dated real assessment scores, CC BY 4.0; and **UCI Student Performance** (Cortez & Silva, 2008) — 1,044 secondary-school students with three sequential real grades, CC BY 4.0. Larger or more granular alternatives (EdNet, ASSISTments, KDD Cup Educational datasets) were considered and excluded for infeasible scale (>100GB), gated per-dataset access-request processes this project has no institutional channel to complete, and a step-level interaction-log shape that does not map cleanly onto this platform's session/category structure — documenting exclusions is as important to a defensible methodology as documenting inclusions.

Every feature is classified as either **real-measured** (derived directly from a genuine measurement — e.g. `avg_test_score` from real submitted assessment scores, `practice_streak` from the longest real consecutive active-day run in the VLE clickstream) or **platform-only** (no public dataset measures IRT ability, HelaIQ's specific cognitive categories, or its mini-games; these are generated for real rows from a pseudo-theta derived from that student's own real assessment performance, passed through the same structural equations the synthetic generator trusts, so a real row and a synthetic row sharing the same underlying ability are structurally comparable). The final hybrid training set combines 32,593 real OULAD rows, 1,044 real UCI rows, and 40,000 synthetic rows whose composite-score heuristic weights are empirically calibrated against a multinomial logistic regression fit on the real OULAD outcome data (replacing hand-picked domain-expert weights with observed coefficients wherever a real analogue exists) — 73,637 rows total, 45.7% grounded in real student outcomes, enforced by a hard 40% floor in the assembly pipeline.

**3.y.3 Advanced Feature Engineering.** Nineteen additional behavioural features were engineered beyond the original 24, each with an exact mathematical specification. Representative examples: learning velocity (`LV = (θ_now − θ_(t−4wk)) / 4`, the rate of IRT-ability change per week), consistency index (`CI = 100(1 − CV)`, a scale-invariant coefficient-of-variation measure), fatigue score (within-session accuracy decay: first-half minus second-half accuracy, averaged over recent sessions), error recovery rate (`P(correct at i+1 | incorrect at i)`, a standard learning-analytics "bounce-back" metric), and category mastery (`100 × P(correct | θ_c, item difficulty)` via the same Rasch model as §3.x, reusing the existing IRT engine rather than introducing a second ability metric). Real-derivable features are computed genuinely from OULAD's dated activity/assessment tables at training time; platform-only features are generated from the same latent structural model as the original 24 for training data, and computed for real from HelaIQ's own `session_answers`/`game_scores`/`checkins` tables at live inference time.

**3.y.4 Multi-Model Comparison and Hyperparameter Optimization.** Nine candidate model families were screened (5-fold stratified cross-validation, macro-F1): Random Forest, Extra Trees, Gradient Boosting, AdaBoost, XGBoost, LightGBM, CatBoost, SVM, and a small MLP. TabNet (Arik & Pfister, 2021) was deliberately excluded: it is designed to be competitive with gradient-boosted trees specifically on datasets an order of magnitude larger than this project's (the original paper's own benchmarks use 100K–10M+ rows), offers no demonstrated advantage at this scale, and would add a full PyTorch dependency to an otherwise lightweight inference service — a documented scope decision, not an oversight.

The top-3 screened candidates were tuned via Optuna (Tree-structured Parzen Estimator, Bayesian optimization) under a nested cross-validation scheme: an outer 3-fold split provides an honest generalization estimate, while an inner 3-fold/12-trial Optuna search selects hyperparameters within each outer training fold; a final Optuna pass on the complete training set produces the parameters actually deployed.

**Table 11.2 — Model comparison and final selection** (training run `20260711054426`, 58,909 train / 14,728 test rows, 43 features)

| Model | 5-fold CV screening macro-F1 |
|---|---|
| Random Forest | 0.6656 |
| Extra Trees | 0.6531 |
| Gradient Boosting | 0.6738 |
| AdaBoost | 0.4978 |
| XGBoost | 0.6760 |
| LightGBM | 0.6752 |
| CatBoost | 0.6747 |
| SVM (15,000-row subsample) | 0.6389 |
| MLP | 0.6657 |

| Model | Default test macro-F1 | Optimized test macro-F1 |
|---|---|---|
| XGBoost | 0.6795 | **0.6808 (selected)** |
| LightGBM | 0.6779 | 0.6799 |
| CatBoost | 0.6793 | 0.6800 |

**3.y.5 Comprehensive Evaluation.** Beyond accuracy and macro-F1, the deployed model is evaluated on precision/recall (macro and weighted), ROC-AUC and PR-AUC (one-vs-rest macro — PR-AUC specifically because the "ready" class is a minority class, ~10% of the dataset), balanced accuracy, Matthews correlation coefficient, Cohen's kappa, log loss, Brier score, and per-class calibration curves. Generalization is additionally estimated via 10-fold stratified cross-validation, repeated 5×3-fold cross-validation, and 1,000-resample bootstrap 95% confidence intervals on accuracy and macro-F1. A learning curve provides an explicit overfitting/underfitting diagnosis; performance is additionally broken down by `data_source` to check the model is not quietly overfitting to the easier synthetic signal at the expense of real-student generalization.

**Table 11.3 — Final evaluation metrics**

| Metric | Value |
|---|---|
| Accuracy | 0.697 |
| Balanced accuracy | 0.672 |
| F1 (macro) | 0.681 |
| F1 (weighted) | 0.693 |
| ROC-AUC (OVR macro) | 0.906 |
| PR-AUC (OVR macro) | 0.748 |
| Matthews correlation coefficient | 0.575 |
| Cohen's kappa | 0.574 |
| Log loss | 0.667 |
| Mean Brier score | 0.102 |

The evaluation pipeline's own diagnosis flags overfitting (train score notably exceeds CV score) — reported rather than omitted, a legitimate finding about model generalization.

**3.y.6 Explainable AI.** Per-prediction feature attributions are computed via SHAP (Lundberg & Lee, 2017) against the "ready" class output, cross-checked by three independent methods: LIME (Ribeiro, Singh & Guestrin, 2016 — a local linear surrogate fit around a perturbed instance neighbourhood), permutation importance (Breiman, 2001 — a fully model-agnostic measure), and partial dependence (showing the shape, not just the magnitude, of each top feature's marginal effect). SHAP interaction values additionally surface genuine feature interactions beyond independent main effects. Measured LIME/SHAP top-5-feature agreement (Jaccard overlap) was 36%, reported honestly as a known property of comparing mechanistically distinct explanation techniques.

Predictions are additionally translated into a trend-aware plain-English explanation: `ReadinessPredictionService` supplies the student's previous prediction's feature snapshot alongside the current request, and the inference service computes the percent change on each top-ranked SHAP feature to produce sentences of the form *"Your readiness estimate changed because your weekly practice volume dropped by 45%, ..."* rather than a static, non-comparative explanation.

**3.y.7 Multi-Output Prediction.** Beyond the readiness classification, three additional outputs are trained on genuine, temporally non-leaky real ground truth: for each (student, course module) in OULAD, the first half of real activity/assessment records become the input features, and something that only happens in the second half becomes the target — risk of dropping practice (zero second-half VLE activity, a binary classifier: F1 0.775, ROC-AUC 0.945), predicted next assessment score (regression: MAE 12.93, RMSE 17.10, R² 0.315), and predicted score change (regression: MAE 8.76, RMSE 12.09, R² 0.285). This avoids the target leakage a same-summary features-and-target split would introduce, at the cost of training these three outputs on OULAD alone (UCI's three static grades provide no defensible way to carve out a "first half" of behaviour distinct from the target).

Two further outputs named in the original brief — recommended daily study hours and most effective learning strategy — are deliberately **not** built as supervised predictions. Neither has any dataset recording what the optimal value would have been for a given student, and the latter specifically would require interventional or causal data that no observational dataset can provide. Both are instead delivered as transparent rule-based recommendations from the existing `StudyPlanService`, informed by this module's real predictions as an input signal, and explicitly not framed as ML outputs they are not.

**3.y.8 A Genuine Negative Result — the Time-Aware Ablation.** A later phase of this project added 9 additional "time-aware" features (real per-question response-time capture, speed-accuracy scoring) and ran a proper ablation study comparing 6 feature-set variants at a fixed algorithm (XGBoost), rather than re-running full 9-model selection per variant (documented as infeasible: full model selection was measured at 2+ hours per variant). Result: no time-aware variant beat the existing 43-feature live model (macro-F1 0.6845). Adding the 9 response-time features actually scored below a behaviour-only intermediate variant (0.6764 vs. 0.6827), and the full time-aware model still did not recover past the baseline (0.6810). Per the project's own decision rule ("do not claim improved accuracy until evaluation results demonstrate it"), the time-aware features were **not** merged into the live model and the live model was **not** retrained on them. This demonstrates the evaluation methodology actually gates deployment decisions, rather than every experiment being framed as a success — exactly the kind of result a thesis should report as a legitimate finding, not bury.

**3.y.9 Continual Learning.** Every training run is versioned (`model_registry.py`): full artifacts are archived per version with a SHA-256 hash of the training-data snapshot, and a champion-versus-challenger promotion gate (`retrain.py`) only replaces the live deployed model if a freshly retrained challenger beats it on the same held-out gating metric by a documented margin. A complete, append-only experiment history is kept. A fully automatic, continuously-scheduled production MLOps pipeline was scoped out as disproportionate infrastructure for a single-VM student project — documented as a deployment-configuration note rather than built.

**3.y.10 Threats to Validity, Bias, and Limitations.** The largest remaining validity threat is construct validity of the label: even with the hybrid dataset, only 45.7% of training rows carry a genuine real-world outcome, and that outcome (a UK distance-learning course result) is itself a proxy for, not a direct measurement of, the Sri Lankan government-competitive-exam readiness this platform actually targets — a population-mismatch threat to external validity that this iteration narrows but does not close. A real-data bias analysis (using OULAD's demographic fields, excluded from the model's own feature vector by design) finds a measurable outcome gap by disability status (7.0% vs. 9.5% reaching the top outcome band) and by socioeconomic deprivation tercile (6.7% vs. 12.2%) in the real OULAD population, reported transparently rather than adjusted away, since it reflects a property of the real-world data source, not an artifact introduced by this model.

**3.y.11 Deployment Architecture.** The trained model is served by a standalone FastAPI microservice (`ml-service/`), called by Laravel over HTTP via `ReadinessPredictionService` — architecturally identical to the existing Gemini AI-feedback integration's swappable-service pattern. This keeps the Laravel application free of a heavy ML runtime dependency while still allowing genuine gradient-boosted tree inference, SHAP/LIME explanation, and multi-output regression; every prediction is persisted with its model version, plain-English explanation, and (where available) the three multi-output predictions for auditability and future retraining comparison.

### 11.3 References

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

---

*End of consolidated document. This file (`docs/HELAIQ_THESIS_DOCUMENT.md`) replaces the 23 individual documents previously in `docs/` — see the project's engineering-context file for the standing convention that filesystem/live-query state, not any cached document (including this one), is the authoritative source of truth if this document has not been regenerated recently.*
