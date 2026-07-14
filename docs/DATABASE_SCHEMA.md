# HelaIQ — Database Schema Reference

This is the full column-level companion to `docs/HELAIQ_THESIS_DOCUMENT.md`
§3.7 ("Database schema — grouped overview"), which only gives a coarse,
grouped table listing. This file is the appendix-grade reference: every
table, every column, its type, nullability/default, and foreign keys —
built by reading every migration file directly, not inferred or guessed.

Source of truth: `backend/database/migrations/*.php` (46 files, spanning
the initial Laravel scaffold through the most recent schema change as of
this write). A table's **true current shape** is the union of its
`Schema::create(...)` migration plus every later `add_*_to_*_table`
migration against it — this document already performs that union so each
table below is shown in its final, current form, not just its
as-created form. Where a later migration changed an earlier decision
(e.g. dropped a unique constraint), that is called out explicitly rather
than silently reflected.

---

## Database connection / configuration

- **Engine**: MySQL, run locally via **XAMPP** (not a managed/cloud
  database in the current dev setup).
- **ORM**: Laravel's **Eloquent** — no alternative ORM or query-builder
  layer is used anywhere in the codebase.
- **Schema source of truth**: Laravel **migrations**
  (`backend/database/migrations/*.php`). There is no separate hand-maintained
  SQL dump or schema file — the migration history *is* the schema
  definition, applied in timestamp order via `php artisan migrate`.
- **Database name**: `iq_platform` (a local dev database name, not a
  secret — safe to state literally).
- **Connection env vars** (`backend/.env` — names only, per this
  project's own established convention of never printing secret values):
  - `DB_CONNECTION` (set to `mysql`)
  - `DB_HOST`
  - `DB_PORT`
  - `DB_DATABASE` (value: `iq_platform`)
  - `DB_USERNAME`
  - `DB_PASSWORD` (name only — value never reproduced here, `.env` is
    gitignored)

## Relationship / integrity conventions (recap)

Two conventions recur throughout the schema below and are documented in
full in the main thesis document's §3.7 — recapped briefly here so this
file is self-contained:

1. **Retire, never delete.** Rows that become obsolete (questions, exam
   profiles, badges) are soft-retired via a status/`is_active` flag, never
   hard-deleted, because history tables (`session_answers`,
   `exam_readiness_predictions`, etc.) hold foreign keys into them.
2. **Some history tables are append-only.** `exam_readiness_predictions`
   and `xp_ledger` are never updated in place — every new fact is a new
   row, enabling trend/audit views over time.

---

## Table count

**25 tables** total across all migrations (verified by grepping every
`Schema::create(...)` call across all 46 migration files) — 3 of which
are Laravel-framework tables (`password_resets`, `failed_jobs`,
`personal_access_tokens`) rather than application domain tables, leaving
**22 application/domain tables**. (Note: the main thesis document's §3.7
overview table states "26 tables" — this is a minor pre-existing
miscount in that summary sentence; the migrations directory itself
resolves unambiguously to 25 `Schema::create` calls. See this document's
closing note for the correction now applied to §3.7.)

Grouped the same way as the main document's §3.7:

| Group | Tables |
|---|---|
| Identity & access | `users`, `password_resets`, `personal_access_tokens` |
| Question bank | `categories`, `iq_levels`, `questions`, `ai_generated_questions`, `source_documents` |
| Testing & scoring | `test_sessions`, `session_answers`, `user_progress_snapshots` |
| Exam readiness & planning | `exam_profiles`, `exam_readiness_predictions`, `user_daily_checkins` |
| Gamification | `games`, `game_scores`, `badges`, `user_badges`, `xp_ledger`, `mission_claims` |
| Self-learning content | `study_notes`, `study_note_reviews` |
| Feedback | `feedback` |
| AI coaching | `ai_coach_logs` |
| Framework (Laravel internals) | `failed_jobs` |

---

## Identity & access

### `users`

Created `2014_10_12_000000`; altered by 6 later migrations (`current_level_id`,
IRT fields, gamification fields, target-exam fields added-then-removed,
username/DOB/demo-flag).

| Column | Type | Nullable / Default | Notes |
|---|---|---|---|
| `id` | bigint, PK | — | |
| `name` | string | required | |
| `username` | string | nullable, unique | added `2026_07_13` |
| `email` | string, unique | required | |
| `date_of_birth` | date | nullable | added `2026_07_13` |
| `email_verified_at` | timestamp | nullable | |
| `password` | string | nullable | nullable because Google-only accounts have none |
| `google_id` | string, unique | nullable | |
| `avatar_url` | string | nullable | |
| `auth_provider` | enum(`google`,`password`) | default `password` | |
| `role` | enum(`super_admin`,`admin`,`user`) | default `user` | flat RBAC |
| `is_demo_user` | boolean | default `false` | added `2026_07_13`; marks synthetic demo-data accounts, excluded from research exports by default |
| `locale` | enum(`en`,`si`) | default `en` | |
| `placement_completed_at` | timestamp | nullable | |
| `current_level_id` | FK → `iq_levels.id` | nullable, `nullOnDelete` | added `2026_07_07` |
| `theta_estimate` | float | nullable | added `2026_07_09` (IRT ability estimate) |
| `theta_se` | float | nullable | added `2026_07_09` (standard error of θ) |
| `xp` | unsigned int | default `0` | added `2026_07_09` |
| `coins` | unsigned int | default `0` | added `2026_07_09` |
| `remember_token` | string | nullable | Laravel default |
| `created_at` / `updated_at` | timestamp | — | |

**Historical note**: `target_exam_name`/`target_exam_date` were added to
`users` in migration `2026_07_09_141716`, then dropped again three
migrations later (`2026_07_09_155052`) once `exam_profiles` was created as
the real home for exam-preparation data. Not present in the final schema
— included here only because a naive read of migration filenames alone
(without checking the later drop) would incorrectly imply they still
exist.

### `password_resets`

Laravel default. `email` (string, **primary key**), `token` (string),
`created_at` (timestamp, nullable). No `id` or `updated_at`.

### `personal_access_tokens`

Laravel Sanctum default. `id`, `tokenable_type` + `tokenable_id`
(polymorphic `morphs('tokenable')`), `name`, `token` (string(64), unique),
`abilities` (text, nullable), `last_used_at` (timestamp, nullable),
`expires_at` (timestamp, nullable), timestamps.

---

## Question bank

### `categories`

`id`, `code` (string, unique), `name_en`, `name_si`, `description_en`
(text, nullable), `description_si` (text, nullable), `icon` (string,
nullable), timestamps. 5 fixed rows (seeded), unchanged by any later
migration.

### `iq_levels`

`id`, `level_number` (unsigned tinyint, unique), `name_en`, `name_si`,
timestamps. 5 fixed rows (seeded).

### `questions`

Created `2026_07_07_034615`; altered by **7** later migrations — the most
heavily evolved table in the schema. Shown here in final unioned form.

| Column | Type | Nullable / Default | Notes |
|---|---|---|---|
| `id` | bigint, PK | — | |
| `source_document_id` | FK → `source_documents.id` | nullable, `nullOnDelete` | added `2026_07_11` |
| `category_id` | FK → `categories.id` | required, `cascadeOnDelete` | |
| `level_id` | FK → `iq_levels.id` | required, `cascadeOnDelete` | |
| `question_type` | enum(`mcq_text`,`mcq_image`) | default `mcq_text` | |
| `subcategory` | string(60), indexed | nullable | added `2026_07_10` (Phase 6 exam taxonomy) |
| `question_text_en` | text | required | |
| `question_text_si` | text | required | |
| `image_path` | string | nullable | |
| `generation_rule` | string(60) | nullable | added `2026_07_12` (visual-question generation metadata) |
| `transformation_steps` | json | nullable | added `2026_07_12` |
| `visual_complexity_score` | float | nullable | added `2026_07_12` |
| `options` | json | required | |
| `correct_option_key` | char(1) | required | |
| `explanation_en` | text | nullable | |
| `explanation_si` | text | nullable | |
| `difficulty_weight` | unsigned tinyint | default `1` | |
| `solving_time_seconds` | unsigned smallint | nullable | added `2026_07_10` |
| `learned_expected_time_seconds` | float | nullable | added `2026_07_12`; platform-observed timing vs. authored estimate |
| `time_sample_count` | unsigned int | default `0` | added `2026_07_12` |
| `time_calibration_status` | string(20) | default `uncalibrated` | added `2026_07_12`; lifecycle `uncalibrated → provisional → calibrated` |
| `bloom_level` | string(20) | nullable | added `2026_07_10` |
| `exam_tags` | json | nullable | added `2026_07_10` |
| `cognitive_skill` | string(60) | nullable | added `2026_07_10` |
| `irt_difficulty` | float | nullable | added `2026_07_09`; Rasch logit-scale difficulty (b) |
| `irt_discrimination` | float | default `1.0` | added `2026_07_09`; fixed for 1PL/Rasch |
| `irt_calibrated_at` | timestamp | nullable | added `2026_07_09` |
| `irt_response_count` | unsigned int | default `0` | added `2026_07_11` |
| `irt_calibration_status` | string(20) | default `uncalibrated` | added `2026_07_11`; lifecycle `uncalibrated → provisional → calibrated` (calibrated at ≥30 responses) |
| `is_active` | boolean | default `true` | retire-never-delete flag |
| `created_by` | FK → `users.id` | nullable, `nullOnDelete` | |
| `source_type` | string(30) | default `original` | added `2026_07_11`; `original`\|`book_inspired`\|`past_paper_inspired`\|`theory_derived` |
| `generation_method` | string(30) | default `manual` | added `2026_07_11`; `manual`\|`seeder`\|`ai_mock`\|`ai_gemini`\|`admin_pdf_pipeline` |
| `learning_objective` | text | nullable | added `2026_07_11` |
| `difficulty_reason` | text | nullable | added `2026_07_11` |
| `quality_score` | float | nullable | added `2026_07_11`; documented heuristic composite, not an ML confidence value |
| `validation_status` | string(20) | default `draft` | added `2026_07_11`; `draft`\|`auto_validated`\|`human_approved`\|`rejected` — pre-existing rows backfilled to `human_approved` |
| `translation_status` | string(20) | default `pending` | added `2026_07_12` |
| `translation_quality_score` | float | nullable | added `2026_07_12` |
| `sinhala_review_status` | string(20) | default `pending` | added `2026_07_12` |
| `reviewed_by` | FK → `users.id` | nullable, `nullOnDelete` | added `2026_07_12` (questions lacked this; `ai_generated_questions` already had it) |
| `semantic_equivalence_score` | float | nullable | added `2026_07_12` |
| `created_at` / `updated_at` | timestamp | — | |

Index: `(category_id, level_id, is_active)`.

### `ai_generated_questions`

Draft staging table — never served to students directly; a row is
copied into `questions` only on admin approval. Created
`2026_07_10_040014`; altered by 4 later migrations.

| Column | Type | Nullable / Default | Notes |
|---|---|---|---|
| `id` | bigint, PK | — | |
| `source_document_id` | FK → `source_documents.id` | nullable, `nullOnDelete` | added `2026_07_11` |
| `category_id` | FK → `categories.id` | required, `cascadeOnDelete` | |
| `level_id` | FK → `iq_levels.id` | required | |
| `question_type` | string(20) | default `mcq_text` | |
| `question_text_en` | text | required | |
| `question_text_si` | text | required | |
| `options` | json | required | |
| `correct_option_key` | char(1) | required | |
| `explanation_en` | text | nullable | |
| `explanation_si` | text | nullable | |
| `difficulty_weight` | unsigned tinyint | default `2` | |
| `solving_time_seconds` | unsigned smallint | nullable | added `2026_07_12`; a real gap closed — this table never got the column when `questions` did in Phase 6 |
| `source` | string(20) | default `mock` | |
| `status` | string(20) | default `pending` | draft/pending → approved/rejected |
| `generated_by` | FK → `users.id` | nullable, `nullOnDelete` | |
| `reviewed_by` | FK → `users.id` | nullable, `nullOnDelete` | present from initial create |
| `reviewed_at` | timestamp | nullable | |
| `promoted_question_id` | FK → `questions.id` | nullable, `nullOnDelete` | set once a draft is approved and copied into `questions` |
| `source_type` | string(30) | default `original` | added `2026_07_11` |
| `generation_method` | string(30) | default `manual` | added `2026_07_11` |
| `learning_objective` | text | nullable | added `2026_07_11` |
| `difficulty_reason` | text | nullable | added `2026_07_11` |
| `quality_score` | float | nullable | added `2026_07_11` |
| `validation_status` | string(20) | default `draft` | added `2026_07_11` (no backfill — this table has no pre-existing rows to backfill) |
| `translation_status` | string(20) | default `pending` | added `2026_07_12` |
| `translation_quality_score` | float | nullable | added `2026_07_12` |
| `sinhala_review_status` | string(20) | default `pending` | added `2026_07_12` |
| `semantic_equivalence_score` | float | nullable | added `2026_07_12` |
| `generation_rule` | string(60) | nullable | added `2026_07_12` |
| `transformation_steps` | json | nullable | added `2026_07_12` |
| `visual_complexity_score` | float | nullable | added `2026_07_12` |
| `created_at` / `updated_at` | timestamp | — | |

Index: `(status, category_id)`.

### `source_documents`

Admin-uploaded reference PDFs used as generation inspiration — never a
source of verbatim question text.

| Column | Type | Nullable / Default | Notes |
|---|---|---|---|
| `id` | bigint, PK | — | |
| `title` | string | required | |
| `document_type` | string(40) | default `other` | `past_paper`\|`iq_book`\|`exam_guide`\|`theory_book`\|`other` |
| `exam_type_tags` | json | nullable | |
| `year` | string(10) | nullable | |
| `uploaded_by` | FK → `users.id` | required, `cascadeOnDelete` | |
| `file_path` | string | required | |
| `page_count` | unsigned smallint | nullable | |
| `analysis_status` | string(20) | default `pending` | `pending`\|`analyzing`\|`analyzed`\|`failed` |
| `extracted_topics` | json | nullable | keyword-frequency heuristic, not deep NLP |
| `detected_patterns` | json | nullable | |
| `extracted_theory_concepts` | json | nullable | populated by the chapter-segmentation "knowledge map" layer |
| `reliability_note` | text | nullable | |
| `created_at` / `updated_at` | timestamp | — | |

Index: `(analysis_status)`.

---

## Testing & scoring

### `test_sessions`

Created `2026_07_07_034627`; altered by 3 later migrations.

| Column | Type | Nullable / Default | Notes |
|---|---|---|---|
| `id` | bigint, PK | — | |
| `user_id` | FK → `users.id` | required, `cascadeOnDelete` | |
| `session_type` | enum(`placement`,`daily`,`practice`,`mock`) | required | `mock` added `2026_07_12` via raw `ALTER TABLE` (Laravel schema builder can't alter a MySQL enum's value list without doctrine/dbal) |
| `category_id` | FK → `categories.id` | nullable, `nullOnDelete` | |
| `level_id` | FK → `iq_levels.id` | required | |
| `total_questions` | unsigned smallint | required | |
| `time_limit_seconds` | unsigned int | nullable | added `2026_07_12`; used by mock exams |
| `correct_count` | unsigned smallint | default `0` | |
| `score_percent` | decimal(5,2) | nullable | |
| `theta` | float | nullable | added `2026_07_09`; session-specific ability estimate |
| `theta_se` | float | nullable | added `2026_07_09` |
| `started_at` | timestamp | required | |
| `completed_at` | timestamp | nullable | |
| `level_before_id` | FK → `iq_levels.id` | nullable, `nullOnDelete` | |
| `level_after_id` | FK → `iq_levels.id` | nullable, `nullOnDelete` | |
| `created_at` / `updated_at` | timestamp | — | |

Index: `(user_id, session_type, completed_at)`.

### `session_answers`

Created `2026_07_07_034637`; altered by 1 later migration.

| Column | Type | Nullable / Default | Notes |
|---|---|---|---|
| `id` | bigint, PK | — | |
| `test_session_id` | FK → `test_sessions.id` | required, `cascadeOnDelete` | |
| `question_id` | FK → `questions.id` | required, `cascadeOnDelete` | |
| `selected_option_key` | char(1) | nullable | |
| `is_correct` | boolean | default `false` | |
| `answered_at` | timestamp | nullable | |
| `response_time_ms` | unsigned int | nullable | added `2026_07_12`; real client-captured per-question timing |
| `time_performance_ratio` | float | nullable | added `2026_07_12` |
| `answered_within_expected_time` | boolean | nullable | added `2026_07_12` |
| `ai_feedback_text` | text | nullable | |
| `ai_feedback_generated_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | — | |

Unique: `(test_session_id, question_id)`.

### `user_progress_snapshots`

`id`, `user_id` (FK → `users.id`, `cascadeOnDelete`), `snapshot_date`
(date), `level_id` (FK → `iq_levels.id`), `category_id` (FK →
`categories.id`, nullable, `nullOnDelete`), `accuracy_percent`
(decimal(5,2)), `questions_answered` (unsigned smallint), timestamps.
Unique: `(user_id, snapshot_date, category_id)`. Unchanged since creation.

---

## Exam readiness & planning

### `exam_readiness_predictions`

Append-only history — one row per prediction run, never overwritten.
Created `2026_07_09_141709`; altered by 2 later migrations.

| Column | Type | Nullable / Default | Notes |
|---|---|---|---|
| `id` | bigint, PK | — | |
| `user_id` | FK → `users.id` | required, `cascadeOnDelete` | |
| `features` | json | required | the full feature vector sent to the ML service |
| `readiness_percent` | decimal(5,2) | required | |
| `readiness_label` | enum(`ready`,`almost_ready`,`needs_improvement`,`high_risk`) | required | |
| `reasons` | json | required | top-5 SHAP reasons |
| `risk_of_dropping_practice_probability` | decimal(4,3) | nullable | added `2026_07_10` |
| `at_risk_of_dropping_practice` | boolean | nullable | added `2026_07_10` |
| `predicted_next_assessment_score` | decimal(5,2) | nullable | added `2026_07_10` |
| `predicted_score_change` | decimal(5,2) | nullable | added `2026_07_10` |
| `plain_english_explanation` | text | nullable | added `2026_07_10` |
| `time_management_readiness_percent` | decimal(5,2) | nullable | added `2026_07_12`; only populated when optional pace signals are sent |
| `predicted_score_range` | json | nullable | added `2026_07_12`; ± the multi-output model's held-out RMSE |
| `model_version` | string(50) | required | |
| `predicted_at` | timestamp | required | |
| `created_at` / `updated_at` | timestamp | — | |

Index: `(user_id, predicted_at)`.

### `exam_profiles`

Created `2026_07_09_154831` as one-row-per-user; later migration
(`2026_07_13_053845`) **dropped the one-row-per-user unique constraint**
in favor of a plain index, so a student can now accumulate multiple
profiles over time (one `active` + any number of `completed` ones for
exam history) — a real structural change worth flagging explicitly.

| Column | Type | Nullable / Default | Notes |
|---|---|---|---|
| `id` | bigint, PK | — | |
| `user_id` | FK → `users.id` | required, `cascadeOnDelete` | indexed (not unique — see above) |
| `status` | enum(`active`,`completed`) | default `active` | added `2026_07_13` |
| `exam_category` | string(50) | required | historically a fixed list; current student-facing flow always stores `'other'` (see main thesis doc §7.11-era note) |
| `exam_name` | string(150) | nullable | |
| `exam_date` | date | nullable | |
| `daily_study_hours_target` | decimal(4,1) | default `1.5` | |
| `target_score` | unsigned tinyint | nullable | student's own stated goal, distinct from `pass_mark` |
| `exam_total_questions` | unsigned smallint | nullable | added `2026_07_12` |
| `exam_duration_minutes` | unsigned smallint | nullable | added `2026_07_12` |
| `pass_mark` | unsigned tinyint | nullable | added `2026_07_12` |
| `negative_marking` | boolean | nullable | added `2026_07_12` |
| `exam_sections` | json | nullable | added `2026_07_12` |
| `outcome_attended` | boolean | nullable | added `2026_07_13` |
| `outcome_passed` | boolean | nullable | added `2026_07_13` |
| `outcome_score` | integer | nullable | added `2026_07_13` |
| `outcome_recorded_at` | timestamp | nullable | added `2026_07_13` |
| `created_at` / `updated_at` | timestamp | — | |

### `user_daily_checkins`

`id`, `user_id` (FK → `users.id`, `cascadeOnDelete`), `checkin_date`
(date), `study_hours` (decimal(4,1), default `0`), `motivation_score`
(unsigned tinyint, default `5`), `attended` (boolean, default `true`),
timestamps. Unique: `(user_id, checkin_date)`. Unchanged since creation —
captures the 3 self-reported ML features with no objective platform
instrumentation source.

---

## Gamification

### `games`

`id`, `code` (string, unique), `name_en`, `name_si`, `description_en`
(text, nullable), `description_si` (text, nullable), timestamps.
Unchanged since creation.

### `game_scores`

`id`, `user_id` (FK → `users.id`, `cascadeOnDelete`), `game_id` (FK →
`games.id`, `cascadeOnDelete`), `score` (integer), `duration_seconds`
(unsigned int), `metadata` (json, nullable — schemaless, holds
per-game-specific data e.g. cognitive-switching-cost metrics), `played_at`
(timestamp), timestamps. Index: `(user_id, game_id, played_at)`.
Unchanged since creation.

### `badges`

`id`, `code` (string(50), unique), `name_en`/`name_si` (string(100)),
`description_en`/`description_si` (string(200)), `icon` (string(50)),
`xp_reward` (unsigned int, default `0`), `coin_reward` (unsigned int,
default `0`), timestamps. Fixed catalog, seeded. Unchanged since creation.

### `user_badges`

`id`, `user_id` (FK → `users.id`, `cascadeOnDelete`), `badge_id` (FK →
`badges.id`, `cascadeOnDelete`), `earned_at` (timestamp), timestamps.
Unique: `(user_id, badge_id)`. Unchanged since creation.

### `xp_ledger`

Append-only audit trail behind `users.xp`/`users.coins` (which are
denormalized running totals). `id`, `user_id` (FK → `users.id`,
`cascadeOnDelete`), `xp_amount` (integer, default `0`), `coin_amount`
(integer, default `0`), `reason` (string(100) — a short code like
`session_complete` or `badge:streak_7`), timestamps. Index: `(user_id,
created_at)`. Unchanged since creation.

### `mission_claims`

Records a claimed daily/weekly mission reward; missions themselves are
defined in code, not stored. `id`, `user_id` (FK → `users.id`,
`cascadeOnDelete`), `mission_code` (string(50)), `period_key` (string(20)
— e.g. a date for daily, an ISO week for weekly, preventing
double-claiming), `xp_awarded`/`coin_awarded` (unsigned int),
`claimed_at` (timestamp), timestamps. Unique: `(user_id, mission_code,
period_key)`. Unchanged since creation.

---

## Self-learning content

### `study_notes`

Created `2026_07_11_180000`; altered by 1 later migration (structured
sections, `2026_07_12_070000`).

| Column | Type | Nullable / Default | Notes |
|---|---|---|---|
| `id` | bigint, PK | — | |
| `source_document_id` | FK → `source_documents.id` | required, `cascadeOnDelete` | only generated from `theory_book`-type documents |
| `category_id` | FK → `categories.id` | nullable, `nullOnDelete` | |
| `subcategory` | string(60) | nullable | 14 pre-existing rows have values predating the question-bank taxonomy (documented data-quality gap) |
| `learning_objective_en` | text | nullable | added `2026_07_12` |
| `learning_objective_si` | text | nullable | added `2026_07_12` |
| `title_en` | string | required | |
| `title_si` | string | required | |
| `content_en` | text | required | intro/concept section (original single-blob field, kept for backward compatibility) |
| `content_si` | text | required | |
| `worked_example_en` | text | nullable | added `2026_07_12`; pulled from a real linked-subcategory question, never invented |
| `worked_example_si` | text | nullable | added `2026_07_12` |
| `key_technique_en` | text | nullable | added `2026_07_12` |
| `key_technique_si` | text | nullable | added `2026_07_12` |
| `common_mistakes_en` | text | nullable | added `2026_07_12` |
| `common_mistakes_si` | text | nullable | added `2026_07_12` |
| `key_concepts` | json | nullable | |
| `generation_method` | string(20) | default `mock` | `mock`\|`gemini` |
| `status` | string(20) | default `draft` | `draft`\|`published`\|`rejected` |
| `generated_by` | FK → `users.id` | nullable, `nullOnDelete` | |
| `reviewed_by` | FK → `users.id` | nullable, `nullOnDelete` | |
| `reviewed_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | — | |

Index: `(status, category_id)`.

### `study_note_reviews`

Per-student spaced-repetition schedule (simplified SM-2), created lazily
on first review rather than pre-populated for every published note.

| Column | Type | Nullable / Default | Notes |
|---|---|---|---|
| `id` | bigint, PK | — | |
| `user_id` | FK → `users.id` | required, `cascadeOnDelete` | |
| `study_note_id` | FK → `study_notes.id` | required, `cascadeOnDelete` | |
| `ease_factor` | float | default `2.5` | SM-2 ease factor |
| `interval_days` | unsigned smallint | default `1` | |
| `review_count` | unsigned int | default `0` | |
| `last_result` | string(10) | nullable | `again`\|`hard`\|`good`\|`easy` |
| `next_review_at` | date | required | |
| `created_at` / `updated_at` | timestamp | — | |

Unique: `(user_id, study_note_id)`. Index: `(user_id, next_review_at)`.

---

## Feedback

### `feedback`

`id`, `user_id` (FK → `users.id`, `cascadeOnDelete`), `overall_rating`
(unsigned tinyint), `ui_rating` / `question_quality_rating` /
`sinhala_quality_rating` / `usefulness_rating` (unsigned tinyint,
nullable), `comment` (text, nullable), `suggestion` (text, nullable),
`locale` (enum(`en`,`si`)), `status` (enum(`new`,`reviewed`), default
`new`), `reviewed_at` (timestamp, nullable), `reviewed_by` (FK →
`users.id`, nullable, `nullOnDelete`), `is_demo_feedback` (boolean,
default `false` — synthetic demo-data flag, excluded from research
exports by default), timestamps. Index: `(status)`. Unchanged since
creation.

---

## AI coaching

### `ai_coach_logs`

Deliberately minimal — one row per AI-coach chat message, used only to
derive an `ai_coach_usage_count` ML feature and an admin engagement stat,
not a full chat transcript store. `id`, `user_id` (FK → `users.id`,
`cascadeOnDelete`), `asked_at` (timestamp), timestamps. Index: `(user_id,
asked_at)`. Unchanged since creation.

---

## Framework tables (Laravel internals, not application domain data)

### `failed_jobs`

Laravel default queue-failure log. `id`, `uuid` (unique), `connection`
(text), `queue` (text), `payload` (longtext), `exception` (longtext),
`failed_at` (timestamp, `useCurrent()`).

---

## Correction applied to the main thesis document

While building this reference, the main document's §3.7 stated **"26
tables"** — a direct grep of every `Schema::create(...)` call across all
46 migration files resolves unambiguously to **25**. §3.7 has been
corrected to state 25 and now points here for the full column-level
detail (see this document's own "Table count" section above for the
grouped breakdown, including the note that 3 of the 25 are Laravel
framework tables rather than application domain tables).
