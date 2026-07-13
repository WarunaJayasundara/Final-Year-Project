# Mock Exam System

Mock exams did not exist in any form before the time-aware upgrade session
— this document covers what was built, from a standing start.

## 1. What a mock exam is

A `test_sessions` row with `session_type = 'mock'` and a real time limit —
structurally the same entity as a placement/daily/practice session, so it
reuses the existing generic `/sessions/{session}/answers`,
`/sessions/{session}/complete`, and `/sessions/{session}/report` endpoints
for everything after creation. Only session *creation* is mock-specific.

## 2. Creation and question allocation

`POST /api/mock-exams` (`MockExamController`) →
`QuestionSamplingService::sampleForMockExam()`, which builds a **weighted-
but-bounded** category allocation:

- 50% even split across the requested categories (or all categories, if
  "full syllabus" scope is chosen).
- 50% inverse-mastery-weighted toward the student's weaker categories.
- A hard floor per category, so no requested category is ever starved to
  zero questions even under aggressive weak-area weighting.

Setup (`MockExamSetupPage`) lets a student choose: number of questions,
duration in minutes, full-syllabus vs. selected-category scope, and
standard vs. adaptive difficulty (adaptive skews harder within a
student's own strong areas, to genuinely stress-test them rather than let
them coast on easy questions in categories they've already mastered).

## 3. Real-exam pacing integration

If a student's `exam_profiles` row has real exam structure filled in
(`exam_total_questions`, `exam_duration_minutes`), mock exams can be sized
to mirror the real thing, and `targetSecondsPerQuestion()` provides a real
pace comparison point (used by the readiness-gap panel and the
per-question expected-time chip in the session UI).

## 4. The countdown timer

`MockExamRunner` is the **first genuine countdown timer** anywhere in the
test-taking UI (regular practice/daily/placement sessions deliberately
show no countdown, to avoid inducing anxiety on non-timed practice). It:

- Counts down visibly for the full session duration.
- Shifts to the `warning` design token only in the final 60 seconds
  (rather than a constant red pulse for the whole exam, which the HelaIQ
  redesign explicitly moved away from as an anxiety-inducing pattern).
- **Auto-submits** the session when time expires, exactly like a real
  proctored exam.

## 5. Reporting

Mock exams reuse the same `reportPayload()` as every other session type —
per-question review with the correct answer's derivation shown (via
`Question::toClientArray($locale, true)`, the *only* call site in the
whole codebase that passes `$includeExplanation = true` — every other
call site defaults to `false`, since revealing the explanation before a
student answers would be a real information leak). Wrong answers can
additionally request a deeper Gemini/mock-generated explanation via the
existing `AiFeedbackServiceInterface` (see the "Explain" button in the
session report).

## 6. Study plan integration

`StudyPlanPage`'s daily-plan rows deep-link "Start" buttons directly into
the relevant practice mode; a `timed_mock_practice` activity block links
to `/test/mock` (a real pre-existing bug — it previously pointed at
`/test/daily` instead — was found and fixed this session). The Practice
hub page (`/test/practice`) also surfaces Mock Exam as a visually distinct
quick-start card (gold accent, "Timed" badge) alongside Daily Practice and
Weak-Area Practice, per the explicit requirement that mock exams be easy
to discover without crowding the top-level navigation with a 6th nav item.
