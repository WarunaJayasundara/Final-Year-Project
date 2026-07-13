# Testing and Validation

## 1. Automated backend test suite

`cd backend && php artisan test` — **98 of 100 tests passing** as of this
write. No `RefreshDatabase`; every test file runs against the real
development MySQL database with explicit `tearDown()` cleanup that deletes
exactly the rows it created — a deliberate project convention, not an
oversight (see CLAUDE.md §11).

Coverage includes: exam-profile CRUD + outcome/history flow, readiness
prediction persistence (including the research-grade and time-aware
additive fields), response-time calibration lifecycle, speed-accuracy
scoring, spaced-repetition scheduling, study-note recommendation matching,
weak-area weighting (including phase-aware sharpening), mock-exam
creation, and the Rasch calibration/IRT simulation commands.

### The 2 known-failing tests (pre-existing, not introduced this session)

- `AiQuestionGenerationTest::approving_a_draft_promotes_it_to_a_live_question`
  — receives a 404 instead of 200 when approving a generated draft.
- `QuestionBankTest::test_no_duplicate_questions` — finds 4 duplicate
  active text questions, ties to a previously-flagged, still-unresolved
  data-quality item (~810/405 stale Bank3 rows from an earlier seeding
  run — see CLAUDE.md §12 item 6, awaiting a user decision on whether to
  deactivate them).

Both were confirmed present *before* any change made in this session (git
diff showed neither touched file in scope), so they are reported honestly
as pre-existing rather than silently left unmentioned.

## 2. Frontend type checking

`cd frontend && npx tsc --noEmit` — clean, run after every change made in
this session. No component test runner (Vitest/Jest) exists in this
project; frontend correctness is verified via type-checking plus live
browser verification (§4), matching how every prior phase of this project
was verified.

## 3. IRT/CAT simulation validation

`php artisan irt:validate-simulation` — Monte Carlo validation against
synthetic students/items with known true parameters: **item-parameter
recovery r = 0.991, person-parameter (ability) recovery r = 0.915**. See
[IRT_METHODOLOGY.md](IRT_METHODOLOGY.md) §6 for the full method.

## 4. Sinhala corpus validation

`python tools/validate_sinhala.py --build-corpus && --all` — clean across
all 33+ scanned files (every Bank2–Bank5 seeder plus every frontend locale
namespace) as of this write, 1,398 verified words in the corpus. Run after
every Sinhala string added this session.

## 5. Live browser verification performed this session

The following flows were driven end-to-end in a real browser (not just
type-checked), with network requests inspected to confirm the actual
HTTP status codes, not just that the UI rendered:

| Flow | Verified |
|---|---|
| Student registration | `POST /api/auth/register` → 201, redirected to placement test |
| Username/email + password login | `POST /api/auth/login` → 200, correct user returned from `/api/auth/me` |
| Logout | `POST /api/auth/logout` → 200 |
| Student feedback submission | `POST /api/feedback` → 201, appears correctly in admin dashboard |
| Admin feedback review | `POST /api/admin/feedback/{id}/review` → 200, status updates live |
| Anonymized feedback CSV export | Confirmed no user-identifying columns in the actual response body |
| Demo-data exclusion toggle | Cohort/ML-overview numbers verified to change correctly between "excluding" (4 real students) and "including" (34, +30 demo) |
| Practice hub | Quick-start cards, weak-area deep link (populated with a real weakest category from live student data), balanced category grid |
| Exam-profile general/exam-specific readiness framing | Confirmed a demo student *without* an exam profile shows "Overall Cognitive Readiness", not exam-specific framing |
| 5-item student nav restructure | Confirmed Dashboard/Learn/Practice/Games/Progress render correctly, Mock Exam/Daily Practice no longer separate top-level items |

## 6. A tooling quirk found during this session (not an app bug)

This environment's browser-automation `computer` tool's ref-based
`left_click` intermittently failed to register clicks on certain plain
`<button>` elements (star-rating buttons, "Mark reviewed", form submit
buttons) — confirmed by checking `document.querySelectorAll` state and
network request logs showing no request fired. Clicking the same visual
element by raw screenshot-pixel coordinate instead of by element `ref`
worked reliably every time. This was a browser-automation-tool behavior,
not a defect in the application (confirmed by direct `fetch()` calls
succeeding against the same endpoints) — recorded here so a future session
doesn't waste time re-diagnosing it as an app bug.

## 7. What was **not** independently browser-verified

Per this project's standing, previously-documented constraint (hit in
every prior session that touched student-only UI): students authenticate
exclusively via Google OAuth in the real flow; a genuine end-to-end
Google-OAuth click-through was not performed (this session used
direct-password demo/test accounts and API-level login instead, which
exercises the same backend auth logic but not the Google redirect flow
itself). Games' internal gameplay engines (adaptive staircases,
reaction-time measurement) were not driven interactively this session —
their UI wrapper (`GameStartScreen`) was verified structurally in an
earlier session, and their backend scoring endpoints are covered by the
automated test suite, but a live play-through of, e.g., Cognitive Command
Center's rule-switching logic was not performed in this session.

## 8. Manual end-to-end journeys covered across the project's history

Per CLAUDE.md's accumulated session record: full placement→dashboard→
exam-profile→study-plan→weak-area-practice→wrong-answer-explanation→
games→theory→mock-exam→readiness-prediction pipeline has been exercised
via `tinker`-driven service calls and admin-side browser verification in
prior sessions (student-only UI verified via type-checking + structural
review, per the Google-OAuth constraint above). This session added live
browser verification specifically for every *new* feature built in this
session (§5).
