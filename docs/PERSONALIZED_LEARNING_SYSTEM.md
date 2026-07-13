# Personalized Learning System

How HelaIQ decides what a student should practice next — the rule-based
layer that sits alongside (and is deliberately kept separate from) the ML
readiness model.

## 1. Why this is rule-based, not ML

`StudyPlanService` is explicitly **not** a machine-learning component.
Recommending "practice weak category X for Y minutes today" is a
deterministic function of a student's own measured accuracy per category —
there is no need for, and no valid ground truth to train, a model for
this. Keeping it rule-based also means every recommendation is
explainable in one sentence, which matters for student trust.

## 2. Weak-area weighting

`WeakAreaWeightingService::allocationFor()` biases question allocation in
daily sessions toward categories where the student's accuracy is lowest —
inverse-accuracy weighting with a floor (no category is ever starved to
zero questions, even a student's strongest area still gets minimal
coverage so skills don't atrophy). As an exam date approaches,
`allocationFor()` accepts an optional `$phase` parameter that *sharpens*
(never overrides) this weighting via a per-phase exponent — the closer the
exam, the more aggressively the plan concentrates on weak areas.
`StudyPlanService::determinePhase()` (foundation → practice → intensive →
final_revision → exam_day) is `public static` specifically so
`TestSessionController::startDaily()` can reuse the exact same phase
boundary logic without duplicating it.

## 3. The study plan itself

`GET /exam-profile/study-plan` returns: current phase, days/weeks
remaining, weakest categories, a recommended daily question count and
weekly mock-test count, a full daily plan (today's specific blocks), a
7-day weekly schedule, a phase timeline, and — when an exam is genuinely
near and the current pace can't close the gap — a `readiness_gap` warning
block. That warning **never guarantees an outcome**; it fires only when
three conditions all hold: the exam is near, the readiness gap is
meaningful, and the current plan is mathematically insufficient to close
it in the remaining time.

## 4. No exam profile? Weak-area practice instead

A student without an active exam profile does not see exam-phase framing
at all — `StudyPlanPage` renders a distinct panel (`NoExamWeakAreaPanel`)
showing their top 2 weakest categories with direct "start practicing"
buttons and a mock-exam suggestion, plus a prompt to set up an exam
profile. This is the direct implementation of the requirement that a
non-exam-targeting user should still be steered toward practicing their
weak areas, not shown an empty or misleading exam-countdown UI.

## 5. Self-learning study notes

`StudyNote` records are structured (not just a paragraph): `content_en/si`
(intro/concept), `learning_objective`, `worked_example`, `key_technique`,
`common_mistakes`, all bilingual. The Mock generator is deliberately
honest about its limits — it cannot summarize source text it doesn't
understand, so instead of fabricating prose it either surfaces real
keyword-matched topics as a labelled index, or (when generating from a
theory-book source) pulls a **real worked example from the linked
subcategory's own question bank** rather than inventing one. The Gemini
generator, when configured, receives a bounded excerpt with an explicit
"don't reproduce verbatim, this may be copyrighted" instruction.

## 6. Spaced repetition

`SpacedRepetitionService` implements a **simplified SM-2** algorithm
(again/hard/good/easy grading, ease-factor and interval adjustment) —
documented as simplified, not claiming full Anki-grade sophistication.
`study_note_reviews` tracks per-student-per-note scheduling; `GET
/study-notes/due-today` surfaces what's due.

## 7. Weak-area-triggered recommendations

`StudyNoteRecommendationService` extends
`WeakAreaWeightingService::categoryAccuracy()`'s exact query pattern down
to subcategory grain, matching a student's single weakest subcategory to a
published study note ("you struggled with X — learn it now"). A
retrieval-practice endpoint (`GET
/study-notes/{id}/practice-questions`) then surfaces 2–3 real bank
questions **with answers and explanations included** — a deliberate
difference from the proctored assessment flow, since this is an unscored
self-check tool, not a test.

## 8. Known limitation

14 pre-existing published `StudyNote` rows have `subcategory` values
(e.g. `iq_theory`) that predate the question-bank taxonomy alignment and
don't match any real `questions.subcategory` value — their
retrieval-practice returns empty. Confirmed working correctly for every
taxonomy-aligned subcategory (the ones all new/future notes use). Not
fixed, since it would require either manual admin correction or a risky
guessing migration — flagged for a future pass rather than silently
patched over.

## 9. Documented scope cut

This is retrieval-practice + spaced-repetition + weak-area-triggered
recommendation, not a brief's full 10-step guided→independent→interleaving
pipeline. Guided and independent practice deliberately reuse the existing
adaptive practice-session infrastructure (daily/practice sessions) rather
than building a second, parallel practice system.
