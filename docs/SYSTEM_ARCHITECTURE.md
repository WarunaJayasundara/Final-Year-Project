# System Architecture

## 1. Layered overview

```
┌─────────────────────────────────────────────────────────────┐
│  React 19 SPA (Vite, TypeScript, Tailwind 4, shadcn/ui)      │
│  - features/*: domain modules (auth, sessions, readiness,     │
│    examProfile, feedback, gamification, games, admin, ...)    │
│  - pages/*: route components                                  │
│  - TanStack Query v5 for all server state                     │
└───────────────────────┬─────────────────────────────────────┘
                         │ same-origin XHR ("/api/*"), Vite dev
                         │ proxy forwards to :8000 server-side
┌───────────────────────▼─────────────────────────────────────┐
│  Laravel 9 API (PHP 8.0.11)                                   │
│  - Http/Controllers: thin, one responsibility per action       │
│  - Services/: business logic (Irt/, Ml/, Analytics/,           │
│    Sessions/, Study/, Gamification/, AiFeedback/,               │
│    AiQuestionGeneration/, QuestionBank/)                        │
│  - Contracts/: interfaces for swappable external integrations   │
│  - Sanctum cookie-session auth                                  │
└───────────────────┬───────────────────────┬─────────────────┘
                     │                       │ HTTP (Guzzle,
                     ▼                       │ server-to-server only)
┌────────────────────────────┐   ┌──────────▼────────────────┐
│  MySQL (iq_platform)        │   │  ML microservice (FastAPI) │
│  - questions, test_sessions,│   │  - /predict, /health,       │
│    session_answers, users,  │   │    /metadata,                │
│    exam_profiles,           │   │    /evaluation-report,       │
│    exam_readiness_          │   │    /explainability-report,   │
│    predictions, feedback,   │   │    /duplicate-check           │
│    study_notes, ...         │   │  - scikit-learn/XGBoost/      │
└──────────────────────────────┘   │    LightGBM/CatBoost model  │
                                    └────────────────────────────┘
```

## 2. Why the frontend never talks to the ML service directly

The ML microservice has no auth of its own — it trusts whatever feature
vector it's given. If the browser could call it directly, a student could
submit a fabricated feature vector and get an arbitrary readiness score.
Instead, `ReadinessPredictionService` (Laravel) is the *only* caller: it
computes the feature vector itself from the student's real database rows
(`FeatureExtractionService`), so a student can only influence their score
by actually practicing.

## 3. The swappable-service pattern

Every external/replaceable dependency in the backend follows the same
shape, used for AI feedback, AI question generation, and study-note
generation:

```
Contracts/XServiceInterface.php     ← the interface controllers depend on
Services/X/MockXService.php         ← works with zero config, always available
Services/X/GeminiXService.php       ← real implementation, config-driven
AppServiceProvider::register()      ← binds the interface to Mock or Gemini
                                       based on config('services.x_driver')
```

This means the whole platform runs and demos correctly with **zero API
keys configured** (everything falls back to the Mock implementation), and
switching to a real Gemini-backed feature is a single `.env` change
(`AI_FEEDBACK_DRIVER=gemini`, `GEMINI_API_KEY=...`) with no code change.
The ML readiness prediction follows the same *shape* but calls an HTTP
microservice instead of swapping a PHP class, since PHP has no first-class
gradient-boosting/SHAP implementation.

## 4. Request flow example: exam readiness prediction

1. Student clicks "Run prediction" (`ReadinessCard.tsx`).
2. `POST /api/readiness/predict` → `ReadinessController::predict()`.
3. `FeatureExtractionService::extract()` builds a 43-value feature vector
   from the student's real `test_sessions`, `session_answers`,
   `game_scores`, `user_daily_checkins`, `exam_profiles`, and previous
   predictions.
4. `ReadinessPredictionService::predictFor()` posts that vector (plus the
   previous prediction's snapshot, for trend explanations) to the ML
   service's `/predict`.
5. The ML service runs the live XGBoost model, computes SHAP-based reasons
   and a plain-English trend explanation, and returns the result.
6. Laravel persists it as a new `exam_readiness_predictions` row (history
   is never overwritten) and returns it to the frontend.
7. `ReadinessController::present()` adds a presentation-layer
   `readiness_type` (`general` vs `exam_specific`) computed from whether
   the student currently has an active, not-yet-due exam profile — this is
   the *only* place that distinction is made; the model itself is
   unaware of it.

## 5. Authentication architecture

- **Students**: Google OAuth (`auth_provider = 'google'`) or
  username/email + password (`auth_provider = 'password'`). Both land the
  same `role = 'user'` account. A student who registered with a password
  and later signs in with Google on the *same, Google-verified* email gets
  that Google identity linked to their existing account automatically —
  see `AuthController::handleGoogleCallback()`'s docblock for the exact
  security reasoning (only that direction is safe; the reverse is never
  done automatically).
- **Admins**: a separate `POST /api/admin/login` endpoint, email+password
  only, restricted to `role IN ('admin','super_admin')`. Never reachable
  via Google.
- **Session**: Sanctum SPA cookie session (not bearer tokens) — the
  frontend fetches a CSRF cookie before any mutating request
  (`ensureCsrfCookie()` in `lib/api.ts`) and Axios attaches it
  automatically via `xsrfCookieName`/`xsrfHeaderName`.

## 6. Frontend architecture

- **Routing**: a single `App.tsx` route tree, guarded by composable
  wrapper routes — `<RequireAuth>`, `<RequirePlacement>`,
  `<RequireRole roles={[...]}>`. Admin routes live under `/admin/*` inside
  their own `AdminLayout` (persistent sidebar), separate from the
  student-facing `MainLayout` (top nav).
- **Server state**: TanStack Query v5 everywhere, no ad-hoc `fetch` +
  `useState`. Every feature module exports typed API functions
  (`api.ts`), a matching set of hooks (`useX.ts`), and its own
  `types.ts`.
- **i18n**: i18next with one JSON namespace per feature area under
  `src/locales/{en,si}/`, loaded eagerly at boot (`lib/i18n.ts`).
- **Design tokens**: Tailwind 4 CSS-variable theme (`index.css`) —
  `--primary`, `--success`, `--warning`, `--brand-gold`, a 5-color chart
  palette — consistent across light/dark and reused by every page rather
  than one-off hex values.

## 7. Deployment topology (development)

All three services run on the same machine during development:
Laravel on `:8000`, Vite on `:5173`, the ML service on `:8100`. None of
them are persistent OS services — each needs manual restart after a
reboot or a long idle period. See
[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for LAN testing and production
deployment.
