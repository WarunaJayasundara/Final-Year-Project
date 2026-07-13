# Database Design

MySQL, database name `iq_platform` (internal name predates the HelaIQ
rebrand — see CLAUDE.md §1). 26 tables, all managed through Laravel
migrations (no manual schema changes). This document groups them by
purpose rather than listing every column — see the migration files in
`backend/database/migrations/` for exact column definitions.

## 1. Identity and access

| Table | Purpose |
|---|---|
| `users` | Single table for students and admins, distinguished by `role` (`super_admin \| admin \| user`). Carries `username`, `email`, `date_of_birth`, `password` (nullable — Google-only accounts have none), `google_id`, `auth_provider`, `theta_estimate`/`theta_se` (current ability), `current_level_id`, `xp`/`coins`, `is_demo_user`. |
| `password_resets` | Standard Laravel token table for the forgot-password flow. |
| `personal_access_tokens` | Sanctum's table (unused for the cookie-session flow this app actually uses, kept for framework completeness). |

## 2. Question bank

| Table | Purpose |
|---|---|
| `categories` | The 5 fixed cognitive domains (attention, logical_reasoning, memory, numerical_ability, spatial_pattern). |
| `iq_levels` | The 5 difficulty/ability levels (1–5), each with an EN/SI name. |
| `questions` | The live question bank (~6,500 active rows). Carries `category_id`, `level_id`, `options` (JSON), `correct_option_key`, `explanation_en/si`, `irt_difficulty`/`irt_discrimination`/`irt_response_count`/`irt_calibration_status` (Rasch calibration lifecycle), `learned_expected_time_seconds`/`time_calibration_status` (response-time calibration lifecycle), `subcategory`, `exam_tags` (JSON), `solving_time_seconds`, `bloom_level`, `cognitive_skill`, plus provenance columns (`source_document_id`, `source_type`, `generation_method`, `validation_status`, `quality_score`), Sinhala translation-quality columns, and `is_active` (old/retired questions are deactivated, never deleted, to preserve `session_answers` foreign-key integrity). |
| `ai_generated_questions` | Draft staging table for AI-authored questions — mirrors most of `questions`' columns, plus a review workflow (`status`, `reviewed_by`, `rejection_reason`). Approved drafts are promoted into `questions`, never edited in place. |
| `source_documents` | Uploaded reference PDFs used for topic suggestion and study-note generation, plus extracted-topic/chapter metadata. |

## 3. Testing and scoring

| Table | Purpose |
|---|---|
| `test_sessions` | One row per placement / daily / practice / mock session. Carries `session_type`, `level_before_id`/`level_after_id`, `theta`/`theta_se` (this session's ability estimate), `correct_count`/`score_percent`, `started_at`/`completed_at`. |
| `session_answers` | One row per question answered within a session. Carries `selected_option_key`, `is_correct`, `answered_at`, `response_time_ms`/`time_performance_ratio`/`answered_within_expected_time` (time-aware upgrade), `ai_feedback_text`/`ai_feedback_generated_at` (Gemini/mock explanation cache). |
| `user_progress_snapshots` | Daily accuracy snapshots per student, optionally per category (`category_id` nullable = overall), used to plot IQ/accuracy trend charts without re-aggregating raw answers on every dashboard load. |

## 4. Exam readiness and planning

| Table | Purpose |
|---|---|
| `exam_profiles` | A student's target exam. `status` (`active \| completed`) plus outcome fields (`outcome_attended`, `outcome_passed`, `outcome_score`, `outcome_recorded_at`) let a student have one active profile and any number of past ones (see §7 of the platform brief — general vs. exam-specific vs. past-exam readiness). Optional real-exam structure (`exam_total_questions`, `exam_duration_minutes`, `pass_mark`, `negative_marking`, `exam_sections`) drives pace targets and mock-exam sizing. |
| `exam_readiness_predictions` | Append-only history of every ML prediction ever run for a student — never overwritten, so the dashboard can plot a readiness trend. Carries the exact feature vector sent (`features` JSON) alongside the model's output. |
| `user_daily_checkins` | Self-reported daily study hours/motivation/attendance — the 3 model features with no platform instrumentation. |

## 5. Gamification

| Table | Purpose |
|---|---|
| `games` | The 8 mini-games (code, EN/SI name/description). |
| `game_scores` | One row per play, `metadata` JSON for game-specific detail (e.g. cognitive-switching-cost for Cognitive Command Center). |
| `badges`, `user_badges` | Badge catalogue and per-student unlocks. |
| `xp_ledger` | Append-only XP transaction log (never a mutable running total alone — every XP grant is traceable to its source). |
| `mission_claims` | Daily/weekly mission completion + claim state. |

## 6. Self-learning content

| Table | Purpose |
|---|---|
| `study_notes` | Published/draft teaching notes, structured into `content_en/si`, `learning_objective`, `worked_example`, `key_technique`, `common_mistakes` (all bilingual). |
| `study_note_reviews` | Spaced-repetition scheduling per student per note (simplified SM-2 — see [PERSONALIZED_LEARNING_SYSTEM.md](PERSONALIZED_LEARNING_SYSTEM.md)). |

## 7. Feedback

| Table | Purpose |
|---|---|
| `feedback` | Student-submitted ratings (overall, UI, question quality, Sinhala quality, usefulness — all 1–5) plus free-text comment/suggestion, `locale`, `status` (`new \| reviewed`), `is_demo_feedback`. |

## 8. AI coaching

| Table | Purpose |
|---|---|
| `ai_coach_logs` | Chat-widget conversation history (student ↔ Gemini/mock coach). |

## 9. Key design decisions

- **Retire, never delete** — questions, exam profiles, and badges that
  become obsolete are deactivated/archived, not removed, because
  `session_answers`, `exam_readiness_predictions`, and similar history
  tables hold foreign keys into them. Deleting would either cascade-destroy
  real history or require nullable FKs everywhere.
- **History tables are append-only** — `exam_readiness_predictions` and
  `xp_ledger` are never updated in place; every new fact is a new row, so
  trends can be plotted and past states can be audited.
- **`is_demo_user` / `is_demo_feedback`** — the only two boolean flags that
  exist purely to separate synthetic UI-testing data from real research
  data. Every research-facing query in `ResearchExportService` filters
  them out by default (see [TESTING_AND_VALIDATION.md](TESTING_AND_VALIDATION.md)
  and CLAUDE.md's demo-data generator notes).
- **No `RefreshDatabase` in tests** — the test suite runs against the real
  development database with explicit `tearDown()` cleanup per test file,
  a deliberate project convention (not an oversight) so that IRT
  calibration and other stateful services are exercised against realistic
  data volumes rather than an empty in-memory database.
