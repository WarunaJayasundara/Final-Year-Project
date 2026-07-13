# System Overview

**HelaIQ** is a Sri Lankan cognitive-training and IQ-development web
platform, built as the final-year project (CT/2020/074) of W.R. Jayasundara
at the University of Kelaniya. Full title: *"HelaIQ: An AI-Powered
Cognitive Training Platform for IQ Development in Sri Lanka."*

## 1. Problem and motivation

Sri Lankan government-exam candidates (SLAS, Development Officer, Grama
Niladhari, banking, police, teaching service, etc.) prepare for highly
competitive general-aptitude and reasoning sections with almost no
adaptive, data-driven practice tools available in Sinhala. Existing
resources are static PDF question banks with no notion of a candidate's
actual ability, no measurement of *when* they're ready, and no way to see
whether daily practice is closing the gap before the exam date.

HelaIQ addresses this with:

1. **A properly measured starting point** — an adaptive IRT/CAT placement
   test that estimates a real ability score (theta) rather than a raw
   percentage.
2. **Practice that targets weaknesses** — daily/practice sessions weighted
   toward a student's weakest categories, not random sampling.
3. **A readiness signal that means something** — a machine-learning model
   estimating whether a student is on track, distinguishing *general
   cognitive readiness* from *readiness for a specific named exam* (see
   [ML_READINESS_MODEL.md](ML_READINESS_MODEL.md)).
4. **Full bilingual support** — every student-facing surface exists in
   English and Sinhala, with a validated Sinhala corpus process to prevent
   text corruption (see [SINHALA_LANGUAGE_METHODOLOGY.md](SINHALA_LANGUAGE_METHODOLOGY.md)).

## 2. Who uses it

| Role | What they do |
|---|---|
| **Student** | Registers (Google OAuth or email/username+password), takes the placement test, gets daily/weak-area/timed practice recommendations, tracks IQ and readiness trends, plays cognitive-training mini-games, takes mock exams, reads AI-assisted theory notes, and can leave feedback. |
| **Admin** | Manages the question bank (manual, image, generated-pattern, and AI-drafted questions), reviews AI-generated content before it goes live, runs psychometric calibration, monitors cohort-wide research analytics, and reviews student feedback. |

## 3. Core feature list

- Adaptive IRT/CAT placement test (Rasch model)
- Daily and category practice sessions with weak-area weighting
- Mock exams with a real countdown timer and post-exam report
- Exam-readiness prediction (ML), distinguishing general vs. exam-specific
  vs. past-exam outcomes
- AI-assisted wrong-answer explanations (Gemini, with an honest rule-based
  fallback)
- 8 cognitive-training mini-games (memory, attention, reasoning, spatial)
- Gamification: XP, levels, badges, missions, leaderboard
- Self-learning study notes with spaced repetition and weak-area-triggered
  recommendations
- Government exam profile tracking with countdown, phase-aware study plan,
  and a real "did you attend / did you pass" outcome capture once the exam
  date passes
- Student feedback and rating system, with admin dashboard
- Full EN/SI bilingual UI
- Admin research analytics with a synthetic-vs-real data toggle so demo
  accounts never contaminate research numbers

## 4. Technology stack

| Layer | Stack |
|---|---|
| Backend | Laravel 9.19, PHP 8.0.11 |
| Frontend | React 19, TypeScript, Vite, Tailwind 4, shadcn/ui, TanStack Query v5, React Router v7, i18next |
| Database | MySQL |
| ML service | Python/FastAPI microservice (scikit-learn, XGBoost, LightGBM, CatBoost, SHAP, LIME) |
| Auth | Laravel Sanctum (cookie session), Google OAuth (student-only) + username/email+password, separate admin login |

## 5. How the pieces fit together

```
Browser (React SPA)
   │  same-origin XHR via Vite dev proxy (never talks to :8000 directly)
   ▼
Laravel API (:8000)  ──HTTP (server-to-server)──▶  ML microservice (:8100)
   │
   ▼
MySQL
```

The frontend never calls the ML service directly — Laravel's
`ReadinessPredictionService` extracts a feature vector from the student's
real activity, sends it to the ML service, persists the response, and only
then serves it to the browser. See
[SYSTEM_ARCHITECTURE.md](SYSTEM_ARCHITECTURE.md) for the full request flow
and [DATABASE_DESIGN.md](DATABASE_DESIGN.md) for the schema.

## 6. Documentation map

| File | Covers |
|---|---|
| SYSTEM_OVERVIEW.md | This file |
| SYSTEM_ARCHITECTURE.md | Layered architecture, request flow, service boundaries |
| DATABASE_DESIGN.md | Schema, key tables, relationships |
| IRT_METHODOLOGY.md | Rasch/IRT placement test, adaptive item selection |
| ML_READINESS_MODEL.md | What the readiness model predicts, inputs, evaluation |
| DATASET_METHODOLOGY.md | Real + synthetic training data strategy |
| TIME_AWARE_ANALYTICS.md | Response-time capture, speed-accuracy scoring, the ablation study result |
| QUESTION_GENERATION_SYSTEM.md | Manual/image/pattern/AI question authoring pipeline |
| SINHALA_LANGUAGE_METHODOLOGY.md | Corpus-safety process for all Sinhala text |
| PERSONALIZED_LEARNING_SYSTEM.md | Study plan, weak-area weighting, spaced repetition |
| MOCK_EXAM_SYSTEM.md | Mock exam generation and grading |
| GAME_DESIGN.md | The 8 cognitive-training games |
| TESTING_AND_VALIDATION.md | Automated test suite, IRT simulation validation, this session's live verification |
| DEPLOYMENT_GUIDE.md | LAN testing and production deployment checklist |
| THESIS_SIMPLE_EXPLANATION.md | Plain-language walkthrough of the whole project |
| VIVA_QUESTIONS_AND_ANSWERS.md | Anticipated defense questions with honest answers |
