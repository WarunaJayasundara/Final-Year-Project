# MindRise — System Documentation

**Web-Based Cognitive Training Platform for IQ Development**
Final Year Project CT/2020/074 — W.R. Jayasundara, University of Kelaniya

This document is the single reference for how the whole system is built, how its
pieces fit together, and where to look for detail on any one part. Three companion
documents cover the two AI/ML subsystems in depth and are cross-referenced
from Sections 6 and 7 rather than repeated here:

- [`IRT_ADAPTIVE_TESTING_EXPLAINED.md`](IRT_ADAPTIVE_TESTING_EXPLAINED.md) — plain-language walkthrough of the Rasch/IRT adaptive testing engine, written for viva prep.
- [`ML_EXAM_READINESS_EXPLAINED.md`](ML_EXAM_READINESS_EXPLAINED.md) — plain-language walkthrough of the AI exam-readiness prediction module (dataset, model comparison, SHAP explainability).
- [`THESIS_METHODOLOGY_DRAFT.md`](THESIS_METHODOLOGY_DRAFT.md) — formal methodology chapter draft covering both subsystems, with citations, ready to adapt into the thesis.

---

## 1. Project Overview

MindRise is a full-stack web platform that measures and trains cognitive
ability (colloquially "IQ") across five categories — memory, logical
reasoning, numerical ability, attention, and spatial/pattern recognition. It
combines:

- A **computerized adaptive placement test** built on Item Response Theory
  (Rasch model), which estimates a latent ability score (θ) and derives a
  level (1–5) and an IQ-scale estimate from it.
- **Daily and practice test sessions** that keep refining that ability
  estimate as students train.
- **AI-generated feedback** (Google Gemini) on wrong answers, explaining the
  correct reasoning in the student's chosen language.
- **Three mini-games** (Memory Match, Sequence Puzzle, Math Rush) that give
  additional, gamified cognitive training and feed into the student's
  activity streak.
- An **AI exam-readiness predictor**: a supervised ML model (XGBoost,
  selected by comparison against Random Forest and Gradient Boosting) that
  predicts Ready / Almost Ready / Needs Improvement / High Risk from a
  24-feature behavioural + ability profile, with SHAP-based explainable
  reasons shown to the student (e.g. "Excellent Logical Reasoning", "Low
  practice frequency").
- A **student dashboard** (progress charts, IQ estimate, streaks, game
  scores) and an **admin panel** (question bank CRUD, category management,
  user/role management, cohort analytics, CSV export for paired-score
  research, and a psychometrics validation dashboard for the IRT engine).
- **Bilingual UI and content** (English / Sinhala) throughout, including
  question text, answer options, and AI feedback.

The platform doubles as a research instrument: pre-test (placement) vs.
post-test (later sessions) scores can be exported and paired for statistical
analysis (e.g. paired t-test) to evaluate whether the training modules
produce a measurable ability gain.

---

## 2. Technology Stack

| Layer | Choice | Notes |
|---|---|---|
| Backend framework | Laravel **9.19** | Pinned because the dev machine runs PHP **8.0.11** (XAMPP); Laravel 10/11 require PHP 8.1+/8.2+ |
| Backend language | PHP **8.0.2+** | No "new in initializers" syntax (8.1+ feature) — nullable-default + null-coalescing pattern used instead throughout |
| Auth | `laravel/sanctum` (SPA cookie session) + `laravel/socialite` (Google OAuth) | Cookie-based, not token-based — first-party SPA |
| AI feedback | Google Gemini (`guzzlehttp/guzzle` REST calls) behind a swappable interface | See §10 |
| Database | MySQL (via XAMPP) | Database name `iq_platform` |
| Frontend framework | React **19** + TypeScript + Vite **8** | |
| Styling | Tailwind CSS **4** + shadcn/ui (Radix primitives) | Glassmorphism utilities (`.glass`, `.gradient-orb`) added in `index.css` |
| Theming | `next-themes` (`attribute="class"`, system-detected default) | Light/dark/system toggle in the Navbar — see §13 |
| Animation | `framer-motion` | `MotionConfig reducedMotion="user"` at the app root ties every animation to the OS `prefers-reduced-motion` setting |
| Data fetching | TanStack Query **v5** + axios (`withCredentials: true`) | Hook-level mutation callbacks used everywhere (see §13.3 gotcha) |
| Routing | React Router **v7** | |
| Forms/validation | react-hook-form + zod | |
| Charts | Recharts **3** | Dashboard and admin analytics |
| i18n | i18next + react-i18next + language-detector | EN/SI, see §12 |
| Toasts | sonner | |
| Testing | PHPUnit (backend), TypeScript compiler strict-mode (frontend) | No frontend test runner configured; correctness verified via `tsc --noEmit` + manual browser verification |
| ML pipeline | Python 3.11, scikit-learn, XGBoost, SHAP, FastAPI + uvicorn | Standalone `ml-service/` microservice, called from Laravel over HTTP — see §7 |

**Dev topology:** Laravel serves the API on `:8000` (`php artisan serve`);
Vite serves the SPA on `:5173` and proxies `/api/*` to the backend; MySQL
runs as `mysqld.exe` under XAMPP. None of the three are persistent services —
each must be started manually per session.

---

## 3. Architecture

```
┌─────────────────────────────┐        ┌──────────────────────────────┐
│   React SPA (Vite, :5173)   │  HTTP  │   Laravel API (:8000)         │
│  ─────────────────────────  │◄──────►│  ──────────────────────────   │
│  features/{auth,sessions,   │ cookie │  Controllers → Services →      │
│  dashboard,games,admin,     │ session│  Eloquent Models → MySQL       │
│  readiness}/                │        │  Sanctum (SPA auth)             │
│  TanStack Query + axios     │        │  Socialite (Google OAuth)      │
│  i18next (en/si)             │        └──────────────────────────────┘
└─────────────────────────────┘                │              │
                                                 ▼              ▼
                                   ┌──────────────────┐  ┌──────────────────────┐
                                   │  Google Gemini API │  │  ml-service (:8100)   │
                                   │  (wrong-answer     │  │  FastAPI + XGBoost +  │
                                   │   feedback + chat) │  │  SHAP (readiness)     │
                                   └──────────────────┘  └──────────────────────┘
```

The backend follows a **Controller → Service → Model** layering
convention: controllers stay thin (validate input, call a service, shape the
response); business logic — leveling, IRT calibration, ability estimation,
analytics aggregation, AI feedback — lives in `app/Services/**`, organized by
domain (`Irt/`, `Leveling/`, `Analytics/`, `AiFeedback/`).

### 3.1 Backend directory structure

```
backend/
  app/
    Console/Commands/        irt:calibrate, irt:validate-simulation, irt:backfill-theta
    Contracts/                AiFeedbackServiceInterface
    Http/Controllers/
      Auth/                   AuthController (Google OAuth, admin login, session, locale)
      Admin/                  CategoryController, QuestionController, UserManagementController,
                               LevelController, AnalyticsController
      Sessions/               TestSessionController (placement/daily/practice, adaptive + batch)
      Dashboard/               DashboardController
      Games/                   GameController
      Coach/                   CoachController (Gemini chat)
      ReadinessController, CheckinController (ML exam-readiness + daily check-in)
      ExamProfileController   (exam profile CRUD + study-plan endpoint)
      GamificationController  (XP/level summary, badges, missions, leaderboard)
    Models/                   User, Category, IqLevel, Question, TestSession, SessionAnswer,
                               UserProgressSnapshot, Game, GameScore, UserDailyCheckin, AiCoachLog,
                               ExamReadinessPrediction, ExamProfile, Badge, UserBadge,
                               XpLedgerEntry, MissionClaim
    Services/
      Irt/                     RaschMath, RaschCalibrationService, AbilityEstimationService,
                                AdaptiveItemSelectionService
      Leveling/                 LevelAdjustmentService
      Analytics/                IqScoreService, ItemAnalysisService, ResearchExportService (paired scores)
      AiFeedback/               MockAiFeedbackService, GeminiAiFeedbackService
      GeminiAiCoachService      (chat-style coaching, separate from wrong-answer feedback)
      Ml/                       FeatureExtractionService, ReadinessPredictionService
      Study/                   StudyPlanService (rule-based adaptive study planner)
      Gamification/            GamificationService, BadgeService, MissionService
    Http/Middleware/           EnsureUserHasRole
  database/
    migrations/                schema history (see §5)
    seeders/                   QuestionSeeder + per-category seeders, CategorySeeder, IqLevelSeeder,
                                GameSeeder, SuperAdminSeeder, BadgeSeeder
  routes/
    api.php                    all JSON endpoints (Sanctum-protected where applicable)
    web.php                    Google OAuth redirect/callback (must be "web" middleware, see comment in file)
  tests/
    Unit/Services/             RaschMathTest, LevelAdjustmentServiceTest, GamificationServiceTest
    Feature/                   AdaptivePlacementTest, ReadinessPredictionTest, ExamProfileTest,
                               GamificationTest, etc. (hits real dev DB, manual tearDown cleanup)

ml-service/                    standalone Python FastAPI microservice (not part of the Laravel app)
  generate_dataset.py           synthetic training-data generator
  train_model.py                trains/compares RF, Gradient Boosting, XGBoost; saves best + SHAP importances
  app.py                        FastAPI inference server (/predict, /health, /metadata)
  models/                       model.joblib, scaler.joblib, metadata.json (versioned artifacts)
  data/                         synthetic_student_dataset.csv
```

### 3.2 Frontend directory structure

```
frontend/src/
  features/
    auth/                     login, Google OAuth handling, admin login, auth context
    sessions/                 SessionRunner (batch), AdaptivePlacementRunner (CAT), types, api, hooks
    dashboard/                summary cards, progress charts, IQ estimate card
    games/                    MemoryMatch/, SequencePuzzle/, MathRush/, GameResultCard, api, hooks
    admin/                    questions, categories, users, analytics (incl. psychometrics) CRUD UIs
    readiness/                ReadinessCard (dashboard widget + check-in dialog), types, api, hooks
    examProfile/              ExamCountdown, ExamProfileDialog, types, api, hooks
    gamification/             XpWidget, MissionsCard, rewardToast (shared toast trigger), types, api, hooks
  pages/                      route-level page components (PlacementPage, DashboardPage,
                               StudyPlanPage, BadgesPage, LeaderboardPage, SessionReportPage,
                               AdminPsychometricsPage, ...)
  components/
    layout/                   MainLayout, Navbar (glass, theme toggle, mobile menu), Footer, ThemeToggle
    motion/                   FadeIn/FadeInStagger/FadeInItem, MotionCard (framer-motion primitives)
    theme-provider.tsx        next-themes wrapper (attribute="class", defaultTheme="system")
    ui/                       shadcn/ui primitives
  locales/{en,si}/            common.json, admin.json, dashboard.json, sessions.json, studyPlan.json,
                               gamification.json, ...
  lib/                        api.ts (axios instance), i18n.ts, queryClient.ts
```

---

## 4. Roles & Authentication

Flat `users.role` enum: `super_admin | admin | user`. No permissions tables —
three fixed roles are simple enough to gate directly in middleware.

- **user** — every Google OAuth signup lands here (`auth_provider='google'`).
  Places, trains, plays games, views own dashboard.
- **admin** — password login (`POST /api/admin/login`), manages the
  question bank, categories, views cohort analytics and psychometrics.
  Cannot manage other admin accounts.
- **super_admin** — everything admin can, plus create/promote/demote admin
  accounts (guarded against demoting/deleting the last super_admin). Seeded
  via `SuperAdminSeeder`.

Auth is **Sanctum SPA session/cookie** based (not bearer tokens), appropriate
for a first-party SPA served from a trusted origin
(`SANCTUM_STATEFUL_DOMAINS=localhost:5173`). `EnsureUserHasRole` middleware
gates `role:admin,super_admin` and `role:super_admin` route groups.

Google OAuth flow: `GET /api/auth/google/redirect` → Socialite → `GET
/api/auth/google/callback` → find-or-create `user`-role account →
`Auth::login()` → redirect to SPA. These two routes live under the **`web`**
middleware group in `routes/web.php`, not `api.php` — the callback is a
top-level browser redirect from Google with no XHR Origin/Referer for
Sanctum's stateful check to key off, so it needs an unconditional session
(see the code comment in `web.php` for the full reasoning).

---

## 5. Database Schema

| Table | Purpose | Notable columns |
|---|---|---|
| `users` | Accounts, all roles | `role`, `auth_provider`, `google_id`, `current_level_id` (FK), `locale`, `placement_completed_at`, **`theta_estimate`, `theta_se`** (IRT ability + precision) |
| `categories` | 5 fixed cognitive categories | `code`, `name_en/si`, `description_en/si` |
| `iq_levels` | 5 levels | `level_number` (1–5), `name_en/si` |
| `questions` | Question bank | `category_id`, `level_id`, `question_type` (mcq_text/mcq_image), `question_text_en/si`, `options` (JSON), `correct_option_key`, `explanation_en/si`, `difficulty_weight`, `irt_difficulty`, `irt_discrimination`, `irt_calibrated_at`, **`subcategory`, `solving_time_seconds`, `bloom_level`, `exam_tags` (JSON), `cognitive_skill`, `is_active`** |
| `test_sessions` | Placement/daily/practice sessions | `session_type`, `category_id` (practice only), `level_id`, `score_percent`, `level_before_id/level_after_id`, **`theta`, `theta_se`** |
| `session_answers` | Per-question responses within a session | `selected_option_key`, `is_correct`, `ai_feedback_text` (cached Gemini explanation), `ai_feedback_generated_at` |
| `user_progress_snapshots` | Daily rollups for progress charts | unique on `(user_id, snapshot_date, category_id)`, upserted on session completion |
| `games` | 3 fixed mini-games | `code`, `name_en/si`, `description_en/si` |
| `game_scores` | Per-play results | `score`, `duration_seconds`, `metadata` (JSON), `played_at` |
| `user_daily_checkins` | Self-reported study data (no other source) | `checkin_date`, `study_hours`, `motivation_score` (1-10), `attended`; unique on `(user_id, checkin_date)` |
| `ai_coach_logs` | One row per AI-coach chat message | `asked_at` — used only to derive an engagement feature/stat, not a transcript store |
| `exam_readiness_predictions` | History of ML predictions (not overwritten) | `features` (JSON snapshot), `readiness_percent`, `readiness_label`, `reasons` (JSON), `model_version`, `predicted_at` |
| `exam_profiles` | One-to-one government exam preparation profile | `exam_category` (fixed list, see §8), `exam_name`, `exam_date`, `daily_study_hours_target`, `target_score`; unique on `user_id` |
| `users.xp`, `users.coins` | Denormalized running gamification totals | fast reads; the audit trail lives in `xp_ledger` |
| `badges` | Fixed 14-badge achievement catalog (seeded) | `code`, `name_en/si`, `description_en/si`, `icon`, `xp_reward`, `coin_reward` |
| `user_badges` | Earned badges | unique on `(user_id, badge_id)`, `earned_at` |
| `xp_ledger` | Append-only XP/coin award audit trail | `xp_amount`, `coin_amount`, `reason` (e.g. `"badge:streak_7"`, `"session_complete:daily"`) |
| `mission_claims` | Claimed daily/weekly mission rewards | unique on `(user_id, mission_code, period_key)` — missions themselves are defined in code, not stored |

The bold columns in `questions`/`users`/`test_sessions` were added by
`2026_07_09_123146_add_irt_fields_to_questions_and_users_and_sessions.php`
for the Rasch/IRT engine (§6); `user_daily_checkins`, `ai_coach_logs`, and
`exam_readiness_predictions` were added by the exam-readiness ML module
(§7); `exam_profiles` was added by the study-planner module (§8), replacing
a minimal `users.target_exam_name`/`target_exam_date` pair from Phase 1
(dropped once this table existed — see that migration's comment);
`badges`/`user_badges`/`xp_ledger`/`mission_claims` and the `users.xp`/
`users.coins` columns were added by the gamification module (§9). Streaks
are **computed, not stored** — derived from distinct activity dates across
`test_sessions.completed_at` and `game_scores.played_at`.

---

## 6. Adaptive Testing Engine (IRT / Rasch Model)

This is the platform's core measurement instrument and its primary source
of technical novelty. Full derivations, worked examples, and citations live
in the two companion docs linked at the top of this file — this section is
a map of what exists and where.

**In one paragraph:** every student has a latent ability θ and every
question has a calibrated difficulty *b*, both on the same logit scale,
related by the Rasch model `P(correct) = 1/(1+e^-(θ-b))`. Item difficulties
are calibrated from accumulated response data via the **PROX** method
(closed-form, non-iterative). A student's θ is estimated from their answer
pattern via **maximum likelihood (Newton-Raphson)**. The placement test is a
genuine **computerized adaptive test (CAT)**: after every answer, θ is
re-estimated and the next question is chosen to be the one whose difficulty
is closest to the new θ (content-balanced across the 5 categories), stopping
once 25 items are reached or (after ≥15 items) `SE(θ) ≤ 0.35`. Level (1–5)
and the student-facing IQ estimate (`100 + 15θ`) are both simple linear
rescalings of the same θ — no second scoring system exists in parallel.

| Component | File |
|---|---|
| Core math (calibration, MLE, probability, information) | `backend/app/Services/Irt/RaschMath.php` |
| DB-backed calibration (writes `irt_difficulty`) | `backend/app/Services/Irt/RaschCalibrationService.php` |
| Ability estimation from a session or full history | `backend/app/Services/Irt/AbilityEstimationService.php` |
| Adaptive next-item selection + category rotation | `backend/app/Services/Irt/AdaptiveItemSelectionService.php` |
| Level cutpoints from θ | `backend/app/Services/Leveling/LevelAdjustmentService.php` |
| IQ estimate from θ | `backend/app/Services/Analytics/IqScoreService.php` |
| Item difficulty/discrimination reporting | `backend/app/Services/Analytics/ItemAnalysisService.php` |
| Placement session orchestration (CAT loop) | `backend/app/Http/Controllers/Sessions/TestSessionController.php` |
| Manual recalibration | `php artisan irt:calibrate` |
| Backfill for pre-IRT accounts | `php artisan irt:backfill-theta` |
| Monte Carlo validation (no real data needed) | `php artisan irt:validate-simulation --items=60 --persons=500 --answer-rate=0.5 --seed=42` → `storage/app/irt_simulation_report.md` |
| Unit tests | `backend/tests/Unit/Services/RaschMathTest.php`, `LevelAdjustmentServiceTest.php` |
| Feature tests (full placement flow) | `backend/tests/Feature/AdaptivePlacementTest.php` |
| Admin validation dashboard | `frontend/src/pages/admin/AdminPsychometricsPage.tsx` → `GET /api/admin/analytics/psychometrics`, `POST /api/admin/analytics/recalibrate` |

**Validation results** (Monte Carlo parameter recovery, seed=42, 500
simulated respondents × 60 items): item difficulty recovery r=0.991
(RMSE 0.165 logits), person ability recovery r=0.915 (RMSE 0.480 logits).
Full methodology and interpretation in `THESIS_METHODOLOGY_DRAFT.md` §3.x.7.

Reliability is reported as **marginal reliability**
(`1 − mean(SE(θ)²)/Var(θ)`) rather than Cronbach's alpha, since students
don't share a fixed item set — alpha's core assumption doesn't hold here.

Daily/practice sessions still use a fixed-size, pre-sampled batch (for UX
reasons — batch review, single submission) via
`TestSessionController::createBatchSession()`, but θ is recomputed from the
student's **full response history** (not just that session) every time one
completes, so level and IQ estimate stay consistent with the adaptive
placement test's scoring.

**Weak-area weighted daily sessions** (`WeakAreaWeightingService`, Phase 6):
daily sessions no longer split evenly across the 5 categories. Per-category
accuracy is computed from the student's full answer history (a category
needs ≥5 answered questions before its accuracy is trusted; otherwise it
defaults to a neutral 50%, so a brand-new student still gets an even
split), and the category allocation for a session is weighted by
`1 - accuracy` — a weaker category gets more questions next time. Weights
are floored at half of an even split so no category is ever starved to
zero, and rounding drift is reconciled onto the single weakest category so
the total always matches the requested question count exactly. This is
**intentionally not applied** to the placement test (which needs even
category coverage for an unbiased θ estimate) or practice sessions (already
single-category by the student's own choice). See
`backend/app/Services/Sessions/WeakAreaWeightingService.php` and
`backend/tests/Feature/WeakAreaWeightingTest.php`. Exam-type and
days-to-exam weighting are not yet implemented — see §17.

---

## 7. AI Exam Readiness Prediction (ML Module)

A supervised machine-learning layer sitting alongside the IRT engine,
predicting whether a student is **Ready / Almost Ready / Needs Improvement
/ High Risk** for an upcoming exam, with SHAP-based explainable reasons.
Full methodology, dataset design, and model comparison numbers live in
[`ML_EXAM_READINESS_EXPLAINED.md`](ML_EXAM_READINESS_EXPLAINED.md) — this
section is a map of what exists and where.

**In one paragraph:** `FeatureExtractionService` computes a fixed
24-feature vector per student (IRT theta, per-category accuracy, practice
frequency/streak, response time, self-reported study hours/motivation/
attendance, days until exam, etc.) purely from data the platform already
has, plus a small amount of new self-reported data captured via a daily
check-in. `ReadinessPredictionService` POSTs that vector to a standalone
**FastAPI microservice** (`ml-service/`, port 8100) — the same
swappable-external-service pattern as the Gemini integration — which runs a
pre-trained **XGBoost** classifier (selected by comparing Random Forest,
Gradient Boosting, and XGBoost on macro-F1) and returns a readiness
percentage, a class label, and the top-5 SHAP-attributed reasons. The
result is persisted as a new `exam_readiness_predictions` row (history, not
overwritten) so a readiness trend can be charted.

| Component | File |
|---|---|
| Feature extraction (24 features from existing + self-reported data) | `backend/app/Services/Ml/FeatureExtractionService.php` |
| HTTP client to the ML microservice + persistence | `backend/app/Services/Ml/ReadinessPredictionService.php` |
| Student-facing endpoints | `backend/app/Http/Controllers/ReadinessController.php`, `CheckinController.php` |
| Synthetic dataset generator (80,000 rows, documented composite-heuristic labels) | `ml-service/generate_dataset.py` |
| Model training/comparison + SHAP global importance | `ml-service/train_model.py` |
| Inference API (`/predict`, `/health`, `/metadata`) | `ml-service/app.py` |
| Saved model artifacts (versioned) | `ml-service/models/model.joblib`, `scaler.joblib`, `metadata.json` |
| Student dashboard widget | `frontend/src/features/readiness/ReadinessCard.tsx` |
| Feature test (mocks the ML HTTP call) | `backend/tests/Feature/ReadinessPredictionTest.php` |
| Admin ML overview | `GET /api/admin/analytics/ml-overview` (students ready/at-risk, average readiness, live model metrics) |

**Model comparison** (80,000 synthetic records, 80/20 stratified split):
XGBoost selected automatically (macro-F1 = 0.616, macro ROC-AUC = 0.856,
accuracy = 0.622) over Random Forest and Gradient Boosting. Full confusion
matrix, per-class precision/recall, and the top-8 globally important
features (led by `avg_test_score` and `theta`) are in the companion doc.

The three features with no objective source elsewhere on the platform —
`study_hours`, `motivation_score`, `attendance_percent` — are captured via
`user_daily_checkins` rather than fabricated; a student who hasn't checked
in recently gets neutral defaults instead of a failed prediction. Labels in
the training dataset are synthetic (a documented composite heuristic, not
real historical exam outcomes) since real usage volume can't yet support
training — this is disclosed, not hidden, and retraining on real outcomes
later requires no architecture change, only a real `label` column.

---

## 8. Government Exam Profile, Countdown & Smart Study Planner

Phase 2 of the post-supervisor-feedback roadmap (§17). Lets a student tell
the platform which competitive government examination they're preparing
for, then turns that into a live countdown and a personalized,
automatically-adapting study plan — deliberately implemented as a
**transparent rule-based engine, not a second ML model**: the inputs (days
remaining, weak categories, stated daily availability) and desired
behaviour ("get harder and more mock-test-heavy as the exam approaches")
are fully known upfront, so an auditable rule set is both simpler and more
defensible than training a model to approximate the same thing.

**In one paragraph:** a student sets up an `ExamProfile` (one of 12 fixed
Sri Lankan competitive exam categories, e.g. SLAS/Grama Niladhari/Banking/
Sri Lanka Police/Teaching Service, plus a free-text name for "Other", a
target date, daily study-hours availability, and an optional target score).
`StudyPlanService` derives a **preparation phase** from days-remaining
(`foundation` >60d → `practice` 30-60d → `intensive` 14-30d →
`final_revision` <14d → `exam_day` ≤1d), scales recommended daily question
counts and weekly mock-test counts by phase intensity × a per-exam
difficulty-weight heuristic, and produces a weak-category-weighted daily
time-block plan plus a 7-day weekly rotation (more mock-test days and less
"new content" as the exam nears). The dashboard shows a live countdown
(days/hours/minutes, a circular progress ring for time-elapsed-in-prep-
window, today's question goal) and a dedicated Study Plan page shows the
full phase timeline, weekly schedule, and today's time-boxed plan.

| Component | File |
|---|---|
| Exam profile model + fixed exam-category/difficulty-weight lookups | `backend/app/Models/ExamProfile.php` |
| Rule-based planner (phase, daily/weekly/timeline generation) | `backend/app/Services/Study/StudyPlanService.php` |
| Profile CRUD + study-plan endpoint | `backend/app/Http/Controllers/ExamProfileController.php` |
| `days_until_exam` ML feature now reads this table | `backend/app/Services/Ml/FeatureExtractionService.php::daysUntilExam()` |
| Dashboard countdown widget (circular progress, today's goal) | `frontend/src/features/examProfile/ExamCountdown.tsx` |
| Setup/edit dialog (exam category select, date, daily hours, target score) | `frontend/src/features/examProfile/ExamProfileDialog.tsx` |
| Study Plan page (phase timeline, weekly schedule, today's plan) | `frontend/src/pages/StudyPlanPage.tsx` |
| Feature tests (CRUD validation + phase transitions) | `backend/tests/Feature/ExamProfileTest.php` |

The `exam_profiles` table replaces the minimal `users.target_exam_name`/
`target_exam_date` columns added as a Phase-1 stopgap for the ML
`days_until_exam` feature (see the migration comments) — those columns were
dropped once this full profile existed, so there is exactly one place
exam-date data lives.

---

## 9. Gamification (XP, Coins, Badges, Missions, Leaderboard)

Phase 4 of the post-supervisor-feedback roadmap (§17). A transparent,
documented reward economy layered on top of the platform's existing
activity — deliberately **not** a randomized or hidden reward schedule, since
the platform's core claim is measuring cognitive ability honestly, and a
manipulative engagement layer would undercut that.

**In one paragraph:** every session completion and game score awards XP and
coins via a documented formula (`GamificationService`); XP accumulates
toward a "player level" on a standard triangular curve
(`xpForLevel(n) = 50·(n-1)·n`, so each level requires proportionally more
XP than the last) with fun rank titles (Novice → Legend). A fixed 14-badge
catalog (`BadgeService`) is evaluated after any action that could unlock one
— session completion, game score, exam-profile setup, readiness prediction —
spanning onboarding, streak milestones, score/volume mastery, IQ-level
progress, and two badges that deliberately tie into the platform's other AI
features (`exam_ready` unlocks from a "Ready" ML prediction, `study_planner`
unlocks from setting up a study plan). Daily/weekly missions
(`MissionService`) are defined in code and evaluated live from existing
session/game data — only the *claim* is persisted, both to prevent
double-claiming a period and to gate the reward behind an explicit action.
A cohort-wide leaderboard ranks students by XP.

| Component | File |
|---|---|
| XP/coin economy, level curve, session/game reward formulas | `backend/app/Services/Gamification/GamificationService.php` |
| Badge catalog evaluation | `backend/app/Services/Gamification/BadgeService.php` |
| Daily/weekly mission catalog + claim logic | `backend/app/Services/Gamification/MissionService.php` |
| Badge catalog seed data (14 badges) | `backend/database/seeders/BadgeSeeder.php` |
| Summary/badges/missions/leaderboard endpoints | `backend/app/Http/Controllers/GamificationController.php` |
| Dashboard XP/level progress widget | `frontend/src/features/gamification/XpWidget.tsx` |
| Dashboard missions card with claim buttons | `frontend/src/features/gamification/MissionsCard.tsx` |
| Shared reward-toast trigger (used by session/game/profile/readiness flows) | `frontend/src/features/gamification/rewardToast.ts` |
| Badges page (earned vs. locked grid) | `frontend/src/pages/BadgesPage.tsx` |
| Leaderboard page | `frontend/src/pages/LeaderboardPage.tsx` |
| Feature + unit tests | `backend/tests/Feature/GamificationTest.php`, `backend/tests/Unit/Services/GamificationServiceTest.php` |

**Reward formulas** (see `GamificationService` for the authoritative
implementation): a session completion awards `10 + round(score_percent×0.5)`
XP and `round(score_percent/10)` coins, plus a one-time `+100 XP/+50 coins`
bonus for the placement test specifically. A game score awards
`round(normalized%×0.3)` XP and `round(normalized%×0.1)` coins, where
`normalized%` rescales the raw score against a per-game ceiling (the same
approach as `FeatureExtractionService`'s ML feature normalization, kept
independently in sync since the two serve unrelated purposes).

Award hooks are wired into `TestSessionController::complete()`,
`GameController::submitScore()`, `ExamProfileController::store()`, and
`ReadinessController::predict()` — each returns any XP/coins/newly-earned
badges in its response (`rewards`/`new_badges` keys) so the frontend can
surface a toast immediately, without a second round-trip.

---

## 10. AI Feedback (Google Gemini)

Wrong-answer explanations and a conversational coach are both powered by
Gemini, behind a swappable interface so the platform never hard-depends on
one AI vendor:

```php
interface AiFeedbackServiceInterface {
    public function explainAnswer(Question $question, string $selectedOptionKey, string $locale): string;
}
```

- **`MockAiFeedbackService`** — locale-aware templated fallback built from
  the question's authored `explanation_en/si` fields; used if no Gemini key
  is configured.
- **`GeminiAiFeedbackService`** — Guzzle POST to Gemini's `generateContent`
  REST endpoint; builds a locale-aware prompt including the question,
  options, the student's selected answer, and the correct answer; returns a
  short explanation of *why* the correct option is right and why the
  student's choice was a common misconception, in the student's chosen
  language (EN or SI).
- Bound via `config('services.ai_feedback_driver')`
  (`AI_FEEDBACK_DRIVER=mock|gemini` in `.env`) in `AppServiceProvider`.
- **`GeminiAiCoachService`** — a separate, broader conversational service
  (`POST /api/coach/chat`) for open-ended study/motivation coaching, distinct
  from the per-answer explanation flow.

Feedback is generated **lazily and cached**: `POST
/api/sessions/{id}/answers/{answerId}/explain` only calls Gemini the first
time a student requests an explanation for a given wrong answer, then stores
the result in `session_answers.ai_feedback_text` so it's never regenerated.
The session report page displays a small "powered by Gemini" note beneath
AI-generated text for transparency.

---

## 11. AI Question Generation (Human-in-the-Loop)

Phase 5 addresses "AI question generation" honestly: rather than training a
generative model from scratch (infeasible for this project's scope and
dataset size), it reuses the same swappable-service pattern as AI feedback
(§10) to draft candidate questions with an LLM, and **never lets generated
content reach students without an explicit admin approval step**.

```php
interface AiQuestionGeneratorServiceInterface {
    public function generate(Category $category, IqLevel $level, ?string $examCategoryLabel, array $avoidQuestionTexts): array;
}
```

- **`MockAiQuestionGeneratorService`** — rule-based fallback (no API key
  required) that produces a genuinely valid, verifiably-correct MCQ per
  category: arithmetic (numerical_ability), odd-one-out from a pool of word
  groups (logical_reasoning), sequence-position recall (memory),
  letter-counting (attention), and geometric/numeric pattern continuation
  (spatial_pattern, the default). Its Sinhala fields intentionally reuse
  the English text with a documented reason in the class docblock — this is
  the offline path; natural Sinhala phrasing is Gemini's job.
- **`GeminiAiQuestionGeneratorService`** — builds a JSON-schema prompt
  (question text, 4 options, correct key, explanation, difficulty) mapped
  to Bloom's Taxonomy by IQ level, includes the exam-category context and a
  list of existing question texts to avoid duplicating. Validates the
  response shape (`isValidDraft()`) and falls back to the Mock service on
  any network error or malformed response, logged via `Log::warning`.
- Bound via `config('services.ai_question_generator_driver')`
  (`AI_QUESTION_GENERATOR_DRIVER=mock|gemini` in `.env`) in
  `AppServiceProvider`.

**Duplicate detection.** `QuestionDraftService::generateDrafts()` retries
generation up to 3 times per requested question, rejecting any candidate
whose Jaccard word-overlap with an existing bank question (or another draft
in the same batch) is ≥ 0.6. Tokenization strips a stopword list (frame
words like "how many times does the letter appear in") before comparing —
without this, two questions built from the same template but with entirely
different content (different letters, numbers, sequences) would share most
of their tokens and score as false-positive duplicates purely from shared
sentence structure, not shared meaning. Since this rejection is a real
content-safety feature, a batch can legitimately yield fewer drafts than
requested if the generator's output space is too narrow to produce that
many sufficiently distinct questions.

**Review pipeline.** Generated questions are stored as `AiGeneratedQuestion`
rows (`status: pending`) — a staging table entirely separate from the live
`questions` table. An admin reviews each draft (bilingual preview, correct
option highlighted, source badge showing `mock` or `gemini`) and either:
- **Approves** — `QuestionDraftService::approve()` copies the draft into a
  real `Question` row (`is_active = true`) and stamps
  `promoted_question_id` on the draft, or
- **Rejects** — the draft is marked `rejected` and never promoted.

An already-reviewed draft cannot be approved or rejected again (422).

**Endpoints** (admin/super_admin only): `GET /api/admin/ai-questions`
(paginated, filterable by status), `POST /api/admin/ai-questions/generate`
(`category_id`, `level_id`, `count` 1-10, optional `exam_category`), `POST
/api/admin/ai-questions/{aiQuestion}/approve`, `POST
/api/admin/ai-questions/{aiQuestion}/reject`. Frontend:
`frontend/src/pages/admin/AdminAiQuestionsPage.tsx`, reachable from the
admin nav ("AI Questions").

---

## 12. Bilingual Support (EN / SI)

Two independent layers, both required end-to-end:

- **UI strings** — i18next JSON namespaces under
  `frontend/src/locales/{en,si}/{common,admin,dashboard,sessions,...}.json`,
  detected via `i18next-browser-languagedetector` and switchable via a
  `<LocaleSwitcher>` that persists the choice to `localStorage` and syncs it
  server-side via `PATCH /api/auth/locale`.
- **Content** — questions, options, and explanations store parallel
  `_en`/`_si` columns directly on the `questions` row (not a separate
  translations table, since there are only ever two languages) — the admin
  question form requires both before it will save.

AI feedback also respects the student's `locale`, so Gemini's explanation
text is generated in whichever language the student is using.

---

## 13. Frontend Notes

### 13.1 Routing
Single React app, route-guarded rather than a separate admin build.
Student routes: `/`, `/login`, `/placement`, `/dashboard`, `/test/daily`,
`/test/practice`, `/session/:id/report`, `/games`, `/games/*`, `/profile`.
Admin routes (guarded by `RequireRole`): `/admin/dashboard`,
`/admin/questions`, `/admin/categories`, `/admin/users`,
`/admin/psychometrics`, `/admin/ai-questions`.

### 13.2 Adaptive placement UI
`AdaptivePlacementRunner.tsx` drives the sequential CAT experience: one
question at a time, answer → reveal (correct/incorrect highlighting,
options disabled) → explicit "Next" click → next adaptively-selected
question, mirroring the reveal-then-advance UX already used by the batch
`SessionRunner.tsx`. (A real bug where the UI skipped the reveal step and
jumped straight to the next question was found via live browser testing and
fixed by gating the advance behind a `pendingNextQuestion` state — see
git history on that file for the exact diff.)

### 13.3 TanStack Query gotcha (React 18/19 Strict Mode)
Per-call `.mutate(vars, {onSuccess, onError})` callbacks are gated by
`hasListeners()` internally and can be silently dropped under Strict Mode's
double-invoke behavior. **Hook-level** `useMutation({mutationFn, onSuccess,
onError})` callbacks always fire reliably — this pattern is used throughout
(e.g. `useRecalibrate` in `frontend/src/features/admin/analytics.ts`).

---

## 14. Mini-Games

Client-side game logic; the backend only ever receives a final score via
`POST /api/games/{code}/score`.

| Code | Name | Scoring |
|---|---|---|
| `memory_match` | Memory Match | `max(0, 1000 - moves×10 - seconds×2)` |
| `sequence_puzzle` | Sequence/Pattern Puzzle | correct×difficulty − time penalty, 10 rounds |
| `math_rush` | Mental Math Speed Rush | correct×10 − wrong×5, 60-second timer |
| `mental_rotation` | Mental Rotation Challenge | `correctCount×100 − floor(seconds/2)`, 8 rounds |
| `selective_attention` | Selective Attention Challenge | `correctCount×100 − floor(seconds/2)`, 8 rounds |

Each lives under `frontend/src/features/games/{Name}/` and generates its own
content client-side. Scores feed the dashboard's best-score/trend display,
the activity-streak calculation, and (see §9) XP/coin rewards and badge
evaluation.

- **Mental Rotation Challenge** (`frontend/src/features/games/MentalRotation/`)
  — a target shape (one of 3 hand-picked asymmetric polyomino-like shapes on
  a 4×4 grid) is shown alongside 4 candidates; exactly one is a true rotation
  of the target, the other 3 are mirrored-then-rotated distractors
  (`generator.ts`: `rotate90()`, `mirror()`, `rotateBy()`, `normalize()`).
  Tests spatial reasoning without any image assets — everything is drawn
  from a `Cell` coordinate grid.
- **Selective Attention Challenge** (`frontend/src/features/games/SelectiveAttention/`)
  — a visual-search task: a grid of arrow icons (CSS-rotated) all sharing a
  base rotation except one target rotated 90/180/270° differently; find it
  as fast as possible. Grid size scales 4→5→6 across rounds
  (`generator.ts`: `generateRound(round)`).

Both games are rule-based (no ML), matching the existing 3 games' pattern of
generating content entirely client-side and only reporting a final score.

---

## 15. Competitive Question Bank (Phase 6)

The original ~2,000-question bank was pitched at primary-school difficulty —
too simple for the platform's real target audience (20–30 year-old Sri
Lankan graduates preparing for competitive government/banking/university
recruitment exams). Phase 6 **completely replaced the active question bank**
with a **5,375-question, competitive-exam-grade bank** spanning all 5
categories × 5 levels, without altering the `questions` table's core shape
or the session/IRT engine that consumes it.

### 15.1 Retire, don't delete

`CompetitiveBankSeeder` (`php artisan db:seed --class=CompetitiveBankSeeder`)
flips every existing question's `is_active` to `false` — it never deletes
rows, because `session_answers` history references them (deleting would
break past students' reports and IRT calibration history). It then reseeds
the full new bank as active. All session/practice/placement selection logic
already filtered on `is_active`, so retired questions simply stop being
served without a single query change.

### 15.2 New metadata columns

Migration `2026_07_10_062508_add_competitive_metadata_to_questions.php`
added five nullable columns so the bank could carry the richer taxonomy the
brief asked for without a breaking schema change:

| Column | Purpose |
|---|---|
| `subcategory` | Fine-grained archetype, e.g. `matrix_reasoning`, `paper_folding`, `simple_interest`, `syllogisms` (30 distinct values across the bank) |
| `solving_time_seconds` | Expected time budget, scaled by level |
| `bloom_level` | Bloom's Taxonomy tag (`remember`/`apply`/`analyze`/`evaluate`) |
| `exam_tags` (JSON) | Government-exam context tags, e.g. `["gov_aptitude","banking_recruitment"]` |
| `cognitive_skill` | The underlying ability assessed, e.g. `mental-rotation`, `deductive-reasoning` |

### 15.3 Bank composition

| Category | Active questions | Question types |
|---|---|---|
| Logical Reasoning | 1,544 | mcq_text |
| Numerical Ability | 1,406 | mcq_text |
| Spatial & Pattern Recognition | 1,428 | 1,400 mcq_image + rest mcq_text |
| Attention | 500 | mcq_text |
| Memory | 500 | mcq_text |

New archetypes by subcategory family:
- **Image-based (spatial/abstract), 1,400 questions, all SVG-rendered:**
  matrix reasoning (Raven-style 3×3 grids), figure series completion, shape
  rotation (true rotation vs. mirrored distractors on a 4×4 polyomino grid,
  with a programmatic **chirality check** — see §15.4), mirror images of
  glyph strings, paper folding with punched holes (1 or 2 folds, hole
  positions reflected across the correct axis to compute the true answer),
  cube nets (opposite-face identification using two hand-verified fold
  layouts), and embedded-figure grid counting (closed-form square/rectangle
  counting formulas).
- **Numerical reasoning, ~1,000 questions:** profit/loss, averages,
  second-difference and mixed series (squares, cubes, primes, Fibonacci-like,
  alternating-step, affine recurrences), number matrices, work-and-time,
  simple interest, age problems, relative speed, percentages.
- **Logical + verbal reasoning, ~1,300 questions:** shift ciphers,
  alphabet-position codes, direction-sense walks (Pythagorean triples),
  categorical syllogisms, number classification (odd-one-out), letter
  series, number analogies, blood relations, ranking/position puzzles.
- **Memory + attention, ~1,000 questions:** digit spans up to 9 digits,
  4–6 item paired associations, target-letter counting in 5–9 word phrases,
  even/odd scanning of 8–12 number lists, misspelled-word detection.

### 15.4 Correctness-by-construction

Every archetype computes its answer programmatically rather than being
hand-authored, and several have an explicit self-check:
- **Shape rotation** — before seeding, each polyomino base is checked that
  no rotation of its mirror image equals any rotation of the original
  (`SpatialImageSeeder::rotationQuestions()`); a non-chiral base would make
  a "mirrored distractor" accidentally valid, so the seeder throws rather
  than seed an ambiguous question.
- **Paper folding** — the correct unfolded hole layout is computed by
  reflecting punched coordinates across the fold axis/axes; distractors are
  generated by deliberately wrong reflections (wrong axis, one fold instead
  of two, an extra spurious hole) and checked for distinctness from the
  answer.
- **Distractor visual distinctness** (`MatrixSeriesImageSeeder`) — every
  distractor must differ from the correct tile by at least one visually
  perceptible attribute, compared **modulo each shape's own rotational
  symmetry** (e.g. a square repeats every 90°, a circle has no distinct
  rotations at all) — otherwise a "different rotation" distractor could
  render pixel-identical to the correct answer.
- **Cross-seeder duplicate guard** — `NumericalBank2Seeder` and
  `LogicalVerbalBank2Seeder` load every active question's English text
  before generating, and silently skip any row whose text collides with
  the exam/advanced waves seeded just before them (this caught and dropped
  a real handful of RNG parameter collisions during development).

### 15.5 Sinhala safety

A past incident during Phase 5 (hand-composing novel Sinhala character by
character introduced garbled Unicode — see the AI Question Generation
section's design rationale) led to a standing rule: never freehand new
Sinhala prose. `backend/tools/validate_sinhala.py` enforces this
mechanically for every new seeder file:
- **Forbidden-codepoint scan** — flags any character from neighbouring
  Unicode blocks (Malayalam, Telugu, Kannada) or a replacement character,
  which is what corruption looks like in practice.
- **Corpus-membership check** — builds a whitelist of every Sinhala word
  already used (and rendering-verified) across the existing seeders and
  frontend locale files, then flags any word in a new file that isn't in
  that corpus. Three genuinely new words needed by the ranking/position
  archetype (වම් "left", දකුණු "right", පසින් "from the side") were
  individually reviewed and added to an explicit approved-list with a
  comment explaining why.

Run it via `python tools/validate_sinhala.py --all` (validates every
seeder) or against a single new file before seeding.

### 15.6 Quality gate

`tests/Feature/QuestionBankTest.php` asserts, against the live seeded
database: ≥5,000 active questions, all 25 category×level cells populated,
zero duplicate active question texts or image paths, ≥1,000 image-based
questions, structural validity (exactly 4 unique option keys, correct key
present) and bilingual completeness on a random sample, every sampled image
question's SVG file actually exists on disk, and ≥25 distinct subcategories
with ≥95% of questions carrying exam tags.

### 15.7 Admin Question Bank Stats dashboard

`GET /api/admin/analytics/question-bank` (`QuestionBankStatsService`) feeds
`frontend/src/pages/admin/AdminQuestionBankPage.tsx`
(`/admin/question-bank`, linked from the admin nav): total active/retired
counts, a question-type breakdown, a category × level matrix, a
subcategory breakdown per category, a Bloom's-level breakdown, and a count
of questions missing exam tags — a quick sanity check that a reseed landed
correctly without needing to open tinker.

---

## 16. API Reference (grouped)

```
Auth
  GET   /sanctum/csrf-cookie
  GET   /api/auth/google/redirect            (web middleware)
  GET   /api/auth/google/callback            (web middleware)
  POST  /api/admin/login
  POST  /api/auth/logout
  GET   /api/auth/me
  PATCH /api/auth/locale

Admin — users & roles (super_admin only for mutations)
  GET    /api/admin/users
  POST   /api/admin/users
  PATCH  /api/admin/users/{user}/role
  DELETE /api/admin/users/{user}

Admin — content (admin, super_admin)
  GET/POST/PATCH/DELETE  /api/admin/categories[/{category}]
  GET/POST/PATCH/DELETE  /api/admin/questions[/{question}]
  POST                   /api/admin/questions/{question}/image
  GET                    /api/admin/levels

Admin — analytics
  GET  /api/admin/analytics/overview
  GET  /api/admin/analytics/paired-scores
  GET  /api/admin/analytics/paired-scores.csv
  GET  /api/admin/analytics/psychometrics
  POST /api/admin/analytics/recalibrate
  GET  /api/admin/analytics/ml-overview
  GET  /api/admin/analytics/question-bank

Admin — AI question generation (draft -> review -> promote)
  GET  /api/admin/ai-questions
  POST /api/admin/ai-questions/generate
  POST /api/admin/ai-questions/{aiQuestion}/approve
  POST /api/admin/ai-questions/{aiQuestion}/reject

Sessions
  POST /api/sessions/placement/start          (adaptive CAT)
  POST /api/sessions/daily/start              (batch)
  POST /api/sessions/practice/start            (batch)
  GET  /api/sessions/{session}
  POST /api/sessions/{session}/answers
  POST /api/sessions/{session}/complete
  GET  /api/sessions/{session}/report
  POST /api/sessions/{session}/answers/{answer}/explain

Dashboard
  GET /api/dashboard/summary
  GET /api/dashboard/progress-history

Games
  GET  /api/games
  POST /api/games/{code}/score
  GET  /api/games/{code}/scores/me

Coach
  POST /api/coach/chat

Exam readiness (ML) + daily check-in
  POST /api/readiness/predict
  GET  /api/readiness/latest
  GET  /api/readiness/history
  GET  /api/checkins/today
  POST /api/checkins

Government exam profile + smart study planner
  GET  /api/exam-profile
  POST /api/exam-profile
  GET  /api/exam-profile/categories
  GET  /api/exam-profile/study-plan

Gamification: XP/levels, badges, missions, leaderboard
  GET  /api/gamification/summary
  GET  /api/gamification/badges
  GET  /api/gamification/missions
  POST /api/gamification/missions/{code}/claim
  GET  /api/gamification/leaderboard
```

---

## 17. Known Gaps / Not Yet Done

Kept here rather than in memory, since it will change quickly. This project
follows a phased roadmap agreed after supervisor feedback that the original
scope lacked novelty — **Phase 1 (AI Exam Readiness Prediction, §7), Phase 2
(Government Exam Profile + Countdown + Smart Study Planner, §8), Phase 3
(UI/UX redesign, §13), Phase 4 (Gamification, §9), Phase 5 (AI Question
Generation §11 + two new mini-games §14), and Phase 6 (competitive-grade
question bank redesign, §15) are all complete.** The full originally-agreed
roadmap has now shipped; remaining items are smaller loose ends:

- **`GeminiAiQuestionGeneratorService` is unverified against the live
  Gemini API** — no API key has been configured yet (`mock` remains the
  active driver), so it has only been exercised via its fallback path (a
  malformed/failed response falling back to the Mock generator), not a
  real successful generation.
- **Weak-area weighting (§6) covers category-level bias only.** Daily
  sessions now weight toward a student's weakest categories by accuracy,
  but do not yet factor in their `exam_profiles` exam type or the urgency
  of an approaching exam date — the placement CAT's item selection
  (`AdaptiveItemSelectionService`) is unaffected by design (needs even
  category coverage for an unbiased θ estimate).
- **No bulk question import.** The admin side has per-question CRUD
  (`/admin/questions`, §16 API reference), the Psychometrics page (§6), and
  now a **Question Bank Stats dashboard** (`/admin/question-bank`,
  `QuestionBankStatsService` — active/retired counts, by-type, by-category
  × level, by-subcategory, by-Bloom's-level, and an untagged-question
  count), but there is still no bulk CSV/JSON import tool for adding
  questions in batches outside the seeder system.

---

## 18. Running the Project Locally

1. Start MySQL: XAMPP Control Panel → Start "MySQL", or run its
   `mysqld.exe` directly.
2. Backend: `cd backend && php artisan serve` (port 8000).
3. Frontend: `cd frontend && npm run dev` (port 5173, proxies `/api/*` to
   the backend).
4. ML service (only needed for the exam-readiness feature):
   ```
   cd ml-service
   python -m venv venv
   ./venv/Scripts/python.exe -m pip install -r requirements.txt   # first time only
   ./venv/Scripts/python.exe generate_dataset.py                  # first time only, writes data/*.csv
   ./venv/Scripts/python.exe train_model.py                       # first time only, writes models/*
   ./venv/Scripts/python.exe -m uvicorn app:app --host 127.0.0.1 --port 8100
   ```
   Laravel reads its URL from `ML_SERVICE_URL` in `.env` (default
   `http://127.0.0.1:8100`). If this service isn't running, every other
   feature still works — only `/api/readiness/predict` returns a 503.
5. Visit `http://localhost:5173`.

**Backend tests:** `cd backend && php artisan test`
**Frontend type-check:** `cd frontend && npx tsc --noEmit -p tsconfig.json`

Useful one-off commands:
```
php artisan irt:calibrate                 # recalibrate item difficulties from response data
php artisan irt:backfill-theta            # backfill theta for pre-IRT accounts
php artisan irt:validate-simulation       # Monte Carlo validation (writes storage/app/irt_simulation_report.md)
php artisan db:seed                       # re-run all seeders (categories, levels, questions, games, super admin)
```

Retraining the ML model (e.g. after generating more/better synthetic data,
or once real exam-outcome data exists): re-run `generate_dataset.py` and
`train_model.py` inside `ml-service/`, then restart the `uvicorn` process —
the new `models/metadata.json` version is picked up automatically and
reported in every subsequent prediction.
