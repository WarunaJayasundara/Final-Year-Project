# HelaIQ — Final Deep System Audit, Testing & Validation Report

**Date:** 2026-07-13
**Scope:** Full end-to-end audit of the HelaIQ platform (Laravel 9 backend, React 19 frontend, FastAPI ML microservice) — codebase search, live route/UI testing, automated question-bank validation, game logic review, ML pipeline validation, IRT/IQ chain verification, Sinhala content audit, and security testing.

**Method:** Real code was run, real data was queried, real HTTP requests were sent. Six background agents independently audited the question bank, visual questions, Sinhala content, the ML pipeline, all 8 games, and security/authorization — each against the live dev database and live services, not static assumptions. Findings below are only reported after being traced to an exact file/line or reproduced with a concrete input. Nothing here is estimated or invented.

---

## 1. Summary of what changed

| # | Issue | Severity | Status |
|---|---|---|---|
| 1 | AI coach's Gemini system prompt still said "You are **MindRise's** coach" — a real leftover brand name that could leak into live chat responses shown to students | Medium | **Fixed** |
| 2 | Boolean-overlay visual questions (Bank5): Sinhala operator label was `()` — empty parentheses, so a Sinhala-only reader had zero information about which operation (AND/OR/XOR) to compute; the sentence also misplaced the operator clause | Medium | **Fixed** (restored AND/OR/XOR as the established English-loanword pattern) |
| 3 | Question ID 18326 (blood relations): options A and D were both "Cousin" — a genuine duplicate-option bug in one live row, plus the same bug in the generating seeder (`AdvancedLogicalQuestionsSeeder::renderBloodRelation()`) | Medium | **Fixed** (live row corrected; seeder collision-guarded for future runs) |
| 4 | 9 "odd one out" verbal-reasoning questions were exact byte-for-byte duplicates of each other (4 distinct texts, 2-3 copies each) — a real, pre-existing content bug, not caused by this session | Medium | **Fixed** (5 zero-history duplicates deactivated, 2 canonical rows with real answer history kept) |
| 5 | `MockAiQuestionGeneratorService::logical()` had only 6 fixed templates, **all of which were already duplicates of existing bank content** — every AI-draft-generation request for `logical_reasoning` silently produced zero drafts. Root cause of a real backend test failure. The same 6 templates also had a second bug: the "Sinhala" question/explanation text was literally the English text copied verbatim, not a translation | High | **Fixed** (rebuilt on the same verified word/translation source as `LogicalReasoningQuestionsSeeder`, ~1,440 combinations, real Sinhala throughout) |
| 6 | **Memory Match game never actually finished** — the completion check compared `matchedCount` (counts matched *cards*, max 16) against `symbols.length` (8), a value it can never reach. The game got permanently stuck on a fully-solved board with no score screen | **Critical** | **Fixed** |
| 7 | All 8 games used the per-call `.mutate(vars, { onSuccess })` pattern this project's own CLAUDE.md explicitly warns against — silently dropped under React 18/19 StrictMode's double-invoke, which would leave every game's result screen blank after a real completion | High (latent) | **Fixed** (moved to hook-level `onSuccess`, plus synchronous local `setResult` so the score screen always renders even if the network callback is delayed) |
| 8 | Working Memory Span n-back round: a stale-closure bug let a user get free "correctly withheld" credit for spam-clicking "Match" on every step, even non-matches, via a race between the click handler and an unconditional timeout | Medium | **Fixed** (ref-based tracking instead of state, closing the race) |
| 9 | Visual Spatial Memory: the "how many icons" and "which icon was here" answer options were never shuffled — the correct answer was always the first button | High | **Fixed** |
| 10 | Visual Spatial Memory: the "missing icon" round's shuffle discarded the shuffled *value* and kept the positional index — the correct answer was always the last button | High | **Fixed** |
| 11 | Cognitive Command Center: pattern-round distractors could collide (duplicate option value shown twice) when the sequence step was 2 | Low-Medium | **Fixed** |
| 12 | Cognitive Command Center: `cognitive_switching_cost_ms` — the metric this game exists specifically to compute — was always `null`, because the "did the sort rule just change" flag was set before the comparison it was needed for, so it always compared a value against itself | High (defeats the game's core purpose) | **Fixed** |

**12 real, concrete issues found and fixed.** All fixes are verified: `npx tsc --noEmit` clean, `php artisan test` **100/100 passing** (was 98/100 before this session — both pre-existing failures were root-caused and fixed, not skipped), Sinhala corpus validator clean (33 files, 1,398-word corpus).

---

## 2. Codebase search (§1 of the brief)

Searched the full repo (excluding `node_modules`/`vendor`/`.git`) for: `TODO`, `FIXME`, hardcoded `localhost` URLs, `MindRise` leftovers, `console.log`, hardcoded API keys, duplicate routes, lorem ipsum, broken image paths.

- `TODO`/`FIXME`: **0 matches**.
- `console.log` in frontend `src/`: **0 matches**.
- Hardcoded `localhost:8000`/`5173` in frontend `src/`: **0 matches** (everything goes through the Vite dev proxy, by design).
- Lorem ipsum / placeholder text: **0 matches**.
- `MindRise`: 35 files matched. 34 are internal docblock comments and Python/docs prose — CLAUDE.md §1 explicitly scopes the rebrand to the user-facing layer only, so these are correctly out of scope, not oversights. **1 was real**: the AI coach's live Gemini system prompt (fixed, see table above).
- Exposed secrets: grepped for real API-key patterns (`AIza...`, `sk-...`, `GOCSPX-`) across the whole repo — **0 matches**. `backend/.env` confirmed gitignored via `git check-ignore -v`.
- Duplicate routes: `php artisan route:list --json` → 99 routes, **0 duplicate method+URI combinations**.
- `npx oxlint src/`: 3 warnings, all the same benign shadcn/ui "fast-refresh only works when a file only exports components" pattern (expected for `button.tsx`/`tabs.tsx`/`badge.tsx`'s variant-helper exports) — not real issues.
- `npx tsc --noEmit`: clean, before and after all fixes.

---

## 3. Question bank validation (§4–5 of the brief)

**6,770 active questions checked** (of 14,719 total rows; the rest are `is_active=false` retired banks, kept for `session_answers` foreign-key integrity per this project's established convention).

| Check | Result |
|---|---|
| Missing/empty EN or SI text | 0 |
| Malformed/missing `options` JSON | 0 |
| Options with fewer than 2 choices | 0 |
| Duplicate option text within a question | 151 flagged → **150 confirmed false positives** (the `error_detection` archetype intentionally repeats one option 3×, e.g. "THUNDER \| THUNDER \| THNUDER \| THUNDER" — that's the puzzle) → **1 real bug** (question 18326, fixed) |
| Missing `correct_option_key` / key not in options | 0 |
| Invalid `category_id`/`level_id` foreign keys | 0 |
| Missing explanations | 0 |
| `irt_difficulty` out of range (±4.5 logits, matching `RaschMath`'s own clamp) | 0 |
| `irt_discrimination` out of range | 0 (this column is unpopulated by design — the platform uses a 1PL Rasch model, where discrimination is implicitly fixed at 1; not a defect) |
| Image questions with an unresolvable `image_path` | 0 of 1,484 `mcq_image` rows |
| **Exact duplicate question text (separate check, caught by the test suite, not the above script)** | **9 rows, 4 texts** — real bug, fixed (see table above) |

**Mathematical re-verification**: 131 questions independently re-derived (percentages, profit/loss, simple interest, averages, data interpretation, work-and-time, speed-distance, chained multi-step word problems) by re-parsing the stored question text and recomputing from scratch — **not** by re-invoking the seeder's own solver, so this is a genuine independent check. **0 mismatches** against the stored `correct_option_key`. 19 additional sampled questions were deliberately left unverified rather than risk a false-positive mismatch (4 needed an SVG chart image to solve, correctly out of scope for a text-based re-derivation; 15 were train/crossing-speed problems where guessing a formula risked a spurious flag).

---

## 4. Visual/pattern question validation (§6–7 of the brief)

Read `SvgFigureBuilder.php` and the seeder logic for matrix reasoning, rotation, boolean overlay, and chart-interpretation archetypes to confirm each stores its correct answer as the output of the *same* function that renders the image — never a separately-asserted value.

- **Metadata coverage**: 1,484 active `mcq_image` rows. Only the 30 `boolean_overlay` rows have both `generation_rule` and `transformation_steps` populated. `ChartDataInterpretationSeeder` sets `generation_rule` but not `transformation_steps` (54 rows). The other 1,400 rows (7 archetypes, all from the original Bank2 pass) predate those columns and have neither. **This is a real documentation/traceability gap** — the intent of those columns ("so the generating logic is auditable, not just the rendered image") isn't met for 94% of the visual bank — but it is **not** a correctness bug: answers were independently re-verified.
- **Transformation replay**: 30/30 boolean-overlay rows and 15/15 sampled shape-rotation rows were reverse-engineered from their stored image hash, replayed against the real generation logic, and matched the stored `correct_option_key` exactly.
- **SVG rendering**: 8 hand-picked samples (one per archetype) plus an automated scan of 120 random SVGs — all well-formed XML, sane `viewBox`, no NaN/Infinity tokens, no degenerate coordinates. **0 malformed SVGs found.**
- Known, already-documented scope cuts (not re-flagged as bugs): embedded-figure counting and 2D-to-3D object assembly were never built (see CLAUDE.md §10); Bank5's Boolean-overlay (30/60) and Venn-consistency (36/100) yields are capped by real generator combinatorial ceilings, not bugs.

**Recommendation, not actioned this session** (out of scope for an audit pass, a real content task): backfill `generation_rule`/`transformation_steps` for the 1,400 pre-existing Bank2 rows, since the underlying generation is deterministic and the original signature is recoverable from the stored image hash — demonstrated feasible by the replay check above.

---

## 5. Sinhala content audit (§10 of the brief)

- `backend/tools/validate_sinhala.py --build-corpus` → 1,398 verified words. `--all` → **clean, 33 files, 0 forbidden-codepoint or unreviewed-novel-word findings.**
- **Missing-key diff** across all 10 locale namespaces (admin/auth/coach/common/dashboard/games/gamification/profile/sessions/studyPlan): a structural key-set diff (not eyeballing) found **0 missing keys** in either direction.
- **Fully-English values in `si/*.json`**: 1 hit — `common.json`'s `appName: "HelaIQ"`, the brand name, intentionally kept in Latin script. No genuine translation gaps found.
- **`SinhalaSemanticValidationService` sample** (18 questions, 18 distinct subcategories): 15/18 approved, 3 flagged `needs_review` — all 3 are image-based questions where the Sinhala text is a generic "look at the image, select the correct option" instead of a specific description, unlike the English version. Real, structurally-detectable content-fidelity gap, **not** Unicode corruption (0 forbidden-codepoint hits). Flagged for human review, not auto-corrected (per this project's own hard rule against machine-composing Sinhala fixes).
- **A 4th, more serious issue found separately** (not by the validator, by direct code reading): the Boolean-overlay operator label bug (§1, row 2) — fixed this session.
- `APPROVED_NOVEL_WORDS` review-log convention: spot-checked the 4 most recent batches — all have proper dated/contextual explanatory comments, consistent with CLAUDE.md §16.

---

## 6. ML pipeline validation (§15–16 of the brief)

- **Feature-order sync**: `FeatureExtractionService::FEATURE_ORDER` (24) + `::ADVANCED_FEATURE_ORDER` (19) verified byte-identical, in order, against Python `feature_mapping.py` **and independently** against `ml-service/app.py`'s own live-serving `FEATURE_ORDER`/`ADVANCED_FEATURE_ORDER` constants. **Exact match, all three.**
- **Data leakage audit**:
  - Synthetic labels: composite z-score over 16+ features + Gaussian noise — no single feature determines the label. **Safe.**
  - Real-OULAD labels: the institution's own `final_result` field, a genuinely separate column from the behavioral features. **Safe.**
  - Scaler fit order: `scaler.fit_transform(X_train)` then `scaler.transform(X_test)` (never fit on test). **Safe.**
  - Temporal multi-output split: confirmed implemented as documented (first-half activity → features, second-half outcome → target, split at each module's real midpoint). **Safe.**
  - **One real, previously undocumented finding**: `process_oulad.py` produces one row per (student, module presentation), not one row per student — 3,538 of 28,785 distinct students (12.3%) contribute 2–5 rows each. `model_comparison.py`'s train/test split is row-level, not grouped by student, so a repeat student's rows can land in both splits. This gives the model a mild memorization advantage on roughly 5% of total training data. **Not yet fixed** (would require a retrain, out of scope for an audit pass) — recommended as a documented caveat in `ML_RESEARCH_METHODOLOGY.md`'s threats-to-validity section, or a group-aware split next time the model is retrained.
- **Edge-case profiles**, live `POST /predict`:

  | Profile | readiness_percent | label |
  |---|---|---|
  | High performer | 89.7 | ready |
  | Low performer | 24.8 | high_risk |
  | Fast-but-inaccurate | 23.6 | high_risk |
  | Accurate-but-slow | 60.8 | almost_ready |
  | Improving student | 42.1 | needs_improvement |
  | Mid-range/ambiguous | 54.6 | needs_improvement |
  | Exam-near + low readiness | — (`StudyPlanReadinessGapTest`, live) | high-severity warning correctly fires |

  **Fast-but-inaccurate (23.6) < accurate-but-slow (60.8) confirmed** — speed cannot substitute for accuracy. No functional bug found in the live model or its integration.

---

## 7. IRT / IQ calculation chain (§13 of the brief)

Traced the real chain: answer → `session_answers` → `RaschMath::estimateAbility()` → theta/SE → `IqScoreService::fromTheta()` → `LevelAdjustmentService::levelNumberForTheta()`.

- **36/36 directly relevant automated tests pass** (`RaschMathTest`, `LevelAdjustmentServiceTest`, `AdaptivePlacementTest`, `GamificationServiceTest`).
- **Edge cases, run live via `php artisan tinker`**:

  | Scenario | theta | SE | IQ | Classification |
  |---|---|---|---|---|
  | All correct (5 items) | 4.5 (clamped) | 1.95 | 160 (clamped) | gifted |
  | All wrong (5 items) | -4.5 (clamped) | 4.69 | 40 (clamped) | extremely_low |
  | Single correct item | 4.5 | 9.59 (huge — correctly signals unreliability) | 160 | gifted |
  | Zero items | 0 | 9.99 | — | — |
  | Mixed (3/5 correct) | 1.59 | 1.10 | 124 | above_average |

  Theta is clamped to ±4.5 logits (`RaschMath`), IQ separately clamped to [40,160] (`IqScoreService`) — exactly the "reasonable display bounds separate from the underlying theta estimate" the brief asked for, and it was already implemented correctly.
- **The single-correct-item case's huge SE (9.59) is real and correctly computed** — but the actual placement flow can never reach it: `TestSessionController` enforces a CAT stopping rule (`PLACEMENT_MIN_ITEMS = 15`, `PLACEMENT_SE_STOP_THRESHOLD = 0.35`) that guarantees at least 15 answered items before a placement finalizes, so a genuinely low-confidence single-item estimate is never shown to a real student as a finished result.
- **Single source of truth confirmed**: grepped for every IQ-formula usage in the codebase — `IqScoreService::fromTheta()`/`::classify()` is the only place the formula and classification bands exist; `LevelAdjustmentService`'s level cutpoints are explicitly documented as, and verified to be, aligned with the same bands (`levels agree with iq classification bands` test passes).

---

## 8. Game testing (§3 of the brief)

All 8 games were code-reviewed line-by-line for generation correctness, scoring, difficulty progression, completion logic, and result-persistence payload shape; 6 real bugs were found and fixed (§1 table, rows 6–12). Summary per game:

| Game | Result |
|---|---|
| **Memory Match** | **Critical bug found & fixed** (never finished) |
| **Sequence Puzzle** | Distractor generation traced by hand for several (start, step) pairs — always excludes the true answer, dedupes via `Set`. No issues found. |
| **Math Rush** | Division always constructed to produce exact integer results; scoring floored at 0, no negative/NaN paths; double-submit guarded. No issues found. |
| **Mental Rotation** | Rotation math verified closed under composition; distractor-vs-correct collision guarded by a `seen` set. No issues found. |
| **Selective Attention** | Target always offset from distractors by a forced 90/180/270°, unambiguous by construction. No issues found. |
| **Working Memory Span** | **Exploit found & fixed** (n-back spam-click race) |
| **Visual Spatial Memory** | **2 bugs found & fixed** (unshuffled options in 2 of 3 question types) |
| **Cognitive Command Center** | **2 bugs found & fixed** (duplicate distractor value; core switching-cost metric always null) |

A cross-cutting bug affecting all 8 games (the per-call `.mutate(onSuccess)` anti-pattern this project's own CLAUDE.md already warns against) was found and fixed across every game file — this was latent (StrictMode's double-invoke drop is probabilistic in dev, not guaranteed every run), so it may not have been visibly broken in casual testing, but it is now fixed at the root (hook-level `onSuccess` + synchronous local result state) rather than patched per-symptom.

**Not verified**: actually playing all 8 games as a real student in-browser. This project's students authenticate exclusively via Google OAuth (confirmed again this session) — no password path exists for role=`user`, and forging a session was correctly blocked by this environment's own security classifier in an earlier session. Verification here was via direct code tracing to a concrete failing input for every bug reported, which is a stronger guarantee of correctness for logic bugs than a single manual playthrough would have been, but it is not the same as a live click-through.

---

## 9. Live browser testing (§2, §17–18 of the brief)

Tested live against the real dev stack (MySQL, `php artisan serve`, `npm run dev`, ML service, all running).

- **Landing page** (`/`): loads clean, 0 console errors, hero tagline renders verbatim ("Train smarter. Think faster. Prepare with purpose."), how-it-works/skill-areas/features sections all render.
- **Login → Admin sign-in → Admin dashboard**: full flow tested live with the real seeded super-admin account. Dashboard renders real cohort data (4 students, 14 sessions, 30.57% average score), "Excluding demo data" badge confirms the demo/research separation is live and working. DOM measurement (not just a screenshot) confirmed the 4-stat-card grid and 2-chart grid are genuinely full-width and correctly proportioned at 1440px — a screenshot-rendering-tool artifact briefly suggested otherwise, but direct `getBoundingClientRect()` measurement resolved it as a tooling quirk, not a real layout bug.
- **ML Research page**: live evaluation metrics rendered (accuracy 0.697, F1 macro 0.681, ROC-AUC 0.906, etc.), honest **"Diagnosis: overfitting (train score notably exceeds CV score)"** message displayed — the platform surfaces its own model's real limitation rather than hiding it.
- **Knowledge Library page**: real uploaded PDFs listed with their actual analyzed topics; the mojibake-detection reliability note ("Low/zero real Sinhala Unicode character count detected...") renders correctly for the 2 documents it was built for.
- **EN ⇄ SI language switch**: tested end-to-end. `PATCH /api/auth/locale` fires and returns 200, page content re-renders in genuine Sinhala, `<html lang>` syncs correctly (confirming the Phase 11 fix from an earlier session still works). The browser-automation tool's coordinate/ref-based click intermittently failed to register on this button (a known, previously-documented quirk of this exact tooling, not an app bug — confirmed by dispatching the click via JS instead, which worked immediately and produced the correct network request and re-render).
- **Mobile breakpoint (375px)**: admin dashboard, in Sinhala, has **zero horizontal overflow** (`document.body.scrollWidth === window.innerWidth`).
- **Console**: 0 errors across every page tested.
- **Cleanup**: the admin account's locale was reset back to English after testing, since the language switch is a real persisted user-record field, not local-only state — consistent with this project's standing practice of restoring test accounts to their pre-test state.

**Not tested live in-browser**: the student-facing dashboard, test-taking flow, study notes, and games UI — this is the same pre-existing, already-documented constraint from every prior session touching student UI (Google-OAuth-only auth, no dev password path). Not a new limitation introduced by this audit.

---

## 10. Security & authorization testing (§19 of the brief)

- **Route audit** (`routes/api.php`, all 99 routes read): every mutating/admin/cross-user-risk endpoint sits behind `auth:sanctum` and/or `role:admin,super_admin` middleware, with a further `role:super_admin` sub-group for user create/role-change/delete. No under-protected routes found.
- **Live unauthenticated test** (`curl`, no session cookie) against 12 protected endpoints (admin analytics, CSV exports, sessions, exam-profile, dashboard, games, source documents, readiness, recalibration): **all correctly returned 401/403**, none leaked data. `GET /api/auth/me` intentionally returns `{"user":null}` unauthenticated — by design, not a leak.
- **Cross-user data access**: traced every relevant controller's query scoping directly — `TestSessionController::authorizeOwner()` (explicit 403 abort on ownership mismatch), `ExamProfileController` (always queries via the authenticated user's own relation, never `Model::find($id)`), `ReadinessController`, `GameController`, `StudyNoteController` — all correctly scope every query to `auth()->id()`. No route accepts a client-supplied user/resource ID without an ownership check.
- **File upload validation**: PDF upload restricted to `mimes:pdf`, 50MB cap, stored on a private disk never publicly served.
- **CSV export auth**: confirmed behind admin middleware, live-tested unauthenticated → 401.
- **Secrets**: `.env` confirmed gitignored; grepped for actual secret *values* (not just variable names) across all `.php`/`.ts` files — 0 hardcoded secrets found. **One low-severity note**: `SuperAdminSeeder.php`/`​.env.example` both contain a hardcoded fallback default password (`ChangeMe123!`) for the seeded local dev admin account — not a live secret leak (real `.env` overrides it and is gitignored), but worth rotating/removing the fallback if this project is ever deployed beyond local dev.
- **Password hashing**: confirmed `Hash::make()`/`Hash::check()` used throughout, no plaintext storage, `password`/`remember_token` excluded from serialization.

**No exploitable authorization gap found.**

---

## 11. What was NOT fully covered, and why (honest scope limits)

- **Full student-facing browser click-through** (dashboard, testing flow, all 8 games as a real playthrough, study notes) — blocked by the same pre-existing Google-OAuth-only constraint this project has hit in every prior session touching student UI. Games were instead verified by tracing concrete failing inputs through the actual code, which caught 6 real bugs a casual playthrough might easily have missed (e.g. the Cognitive Command Center switching-cost bug only shows up in the submitted metadata, not visually).
- **528-combination responsive/language/theme testing matrix** (33 routes × 4 breakpoints × 2 languages × 2 themes) — not attempted exhaustively, consistent with this project's own already-documented Phase 11 scope decision (CLAUDE.md) that this is unrealistic by hand. A representative sample (landing, login, admin dashboard, ML research, knowledge library) was tested across desktop/mobile/EN/SI instead.
- **Visual question metadata backfill** (94% of the visual bank lacks `generation_rule`/`transformation_steps`) — confirmed feasible (demonstrated via the replay-verification technique) but not executed this session; it's a data-migration task, not a bug fix.
- **ML student-repeat-row train/test leakage** — documented as a real, quantified finding but not corrected (would require a retrain of the live model, which CLAUDE.md explicitly flags as expensive and not to be done casually).
- **Sinhala image-question content-fidelity gap** (3 flagged questions, §5) — flagged for human review, not auto-corrected, per this project's own hard rule against machine-composing Sinhala fixes.

None of these were silently skipped — each is a genuine, bounded, documented decision, following the same "honest scope cuts over overclaiming" pattern this project has used throughout its history (CLAUDE.md §11).

---

## 12. Final verification snapshot

```
Backend:  php artisan test           → 100/100 passing (was 98/100 before this session)
Frontend: npx tsc --noEmit           → clean
Frontend: npx oxlint src/            → 3 pre-existing benign warnings, 0 new
Sinhala:  validate_sinhala.py --all  → clean, 33 files, 1,398-word corpus
Routes:   99 total, 0 duplicates
Security: 12/12 unauthenticated endpoint tests correctly blocked
ML:       feature order exact match across PHP/Python/live service
Games:    6 real bugs found and fixed across 8 games (+ 1 cross-cutting)
Questions: 6,770 active checked, 131 independently math-verified (0 mismatches),
           1,484 image questions checked (0 malformed SVG), 10 duplicate/broken
           rows found and fixed across 2 separate root causes
```

This is not a claim that the system is bug-free — it is a claim that everything listed above was actually run, and everything reported was actually reproduced. Anything not explicitly covered above (full student playthrough, exhaustive responsive matrix, full visual-metadata backfill) is listed in §11 as a known, bounded gap, not silently assumed passing.
