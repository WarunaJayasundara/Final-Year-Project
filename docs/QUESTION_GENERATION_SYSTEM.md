# Question Generation System

How the ~6,500-question active bank was built and how it keeps growing,
across four distinct authoring modes.

## 1. Four ways a question enters the bank

| Mode | How | Admin route |
|---|---|---|
| **Manual (text)** | Admin fills a stepped wizard form with live preview. | `AdminQuestionNewPage` → `QuestionWizard` |
| **Manual (image)** | Admin uploads an image asset for the question stem/options. | Same wizard, image mode |
| **Pattern-generated (visual)** | `SvgFigureBuilder` deterministically renders matrix-reasoning, rotation, mirror-image, paper-folding, cube-net, grid-counting, chart, and boolean-overlay figures as SVG — no external image assets, no LLM. | `AdminVisualGeneratorPage` |
| **AI-drafted (Gemini/mock)** | `AiQuestionGeneratorServiceInterface` generates a draft (question + options + correct answer + explanation), which sits in `ai_generated_questions` until an admin reviews and approves it. | `AdminAiQuestionsPage` |

Only mode 4 ever bypasses direct admin authorship at creation time — and
even then, nothing reaches the live `questions` table without an explicit
admin approval action (`AiQuestionController::approve()`).

## 2. Deterministic seeders — the bulk of the bank

Most of the ~6,500 active questions come from deterministic PHP seeders
(`database/seeders/Questions/Bank2` through `Bank5`), not hand-authoring
or AI generation. Every seeder follows the same `BuildsQuestions`
trait/`insertRows()` pattern: **the correct answer is always computed by a
real solver function**, never asserted or hand-picked — e.g. blood-relation
puzzles are solved by literally walking a constructed family graph,
seating-arrangement puzzles are verified for a *unique* solution by
brute-forcing all constraint permutations before being accepted, Venn
Boolean-overlay questions compute the real PHP-set relation rather than
applying general syllogism-inference rules. This "generate forward, verify
backward" pattern is why the bank can scale to thousands of rows without
manual answer-checking, and why difficulty (`difficulty_weight`, now
correctly spanning levels 1–5 after a bug fix — see §5) can be assigned
confidently per archetype.

Archetype families, by bank:

- **Bank2**: the original competitive-exam-grade replacement for an
  earlier primary-school-level bank (which is deactivated, not deleted).
- **Bank3**: blood relations, direction sense, coding-decoding,
  calendar/clock reasoning, seating arrangement, data interpretation,
  statement-sufficiency critical reasoning — archetypes identified as
  missing by analyzing 22 uploaded reference PDFs.
- **Bank4**: adult-level, Level 4–5-targeted archetypes (multi-statement
  truth-teller logic, multi-constraint seating combining height+age,
  concrete Venn-set consistency, chained multi-operation word problems,
  fixed-template weaken/strengthen critical-reasoning passages) — added
  after a supervisor review flagged existing content as reading too
  primary-school-level.
- **Bank5**: visual/chart archetypes (boolean shape-overlay via a new
  `combineCells()` panel type, real bar/pie/line chart data-interpretation
  questions).

## 3. Visual question generation (`SvgFigureBuilder`)

Every image-based question is a server-rendered SVG, not a static asset —
`renderPanel()` dispatches to one of 8 panel types (matrix, rotation,
mirror, paper-fold, cube-net, grid-count, chart, boolean-overlay), each
with chirality and duplicate self-checks so, e.g., a mirror-image question
can't accidentally present two visually-identical "different" options. A
real rendering bug was found and fixed this session (a screenshot-reported
issue where 5-character option labels overflowed their panel boundaries
into neighbouring options) — `renderText()` now computes font size
dynamically from string length instead of using a fixed size, plus a
defensive `clipPath` was added to every panel type. Already-seeded SVGs
generated before the fix retain the old rendering until the bank is
regenerated (a documented, not-yet-executed follow-up).

## 4. AI-drafted questions

- **Interface**: `AiQuestionGeneratorServiceInterface`, with `Mock` (works
  with zero config) and `Gemini` (real LLM, config-driven) implementations
  — the same swappable-service pattern used throughout the backend.
- **Source context**: an optional bounded excerpt/topic summary from an
  uploaded reference PDF (`PdfIngestionService`), never raw document text
  — copyright-safe by construction.
- **Duplicate detection**: two independent signals — a Jaccard text-overlap
  check plus a TF-IDF-cosine check via the ML service's
  `/duplicate-check` endpoint, degrading gracefully if the ML service is
  down.
- **Review gate**: every draft is `pending → approved/rejected` by an
  admin before promotion into `questions`; bulk-approve is available for
  reviewing many drafts at once.
- **Verified status, honestly**: `GeminiAiQuestionGeneratorService` has
  never been exercised against a live Gemini API key in this project (no
  key configured) — only the Mock-fallback path has been tested. This is
  stated plainly rather than implied to be fully verified.

## 5. Difficulty and calibration

`difficulty_weight` (1–5, set at seed time from the archetype's known
complexity) was found to have a real formula bug — the original
`max(1, min(3, ceil(level/2)))` only produced 3 distinct values across 5
IQ levels, meaning Level 5 wasn't reliably harder than Level 3. Fixed to
track level directly. Independently, `irt_difficulty` is calibrated from
real response data (PROX, see [IRT_METHODOLOGY.md](IRT_METHODOLOGY.md)) —
`difficulty_weight` is the seed-time design intent, `irt_difficulty` is
the empirically observed difficulty; the two are expected to correlate but
are not the same number, and both are tracked.

## 6. Known, documented scope cuts

- **Embedded-figure counting and 2D-to-3D object assembly** archetypes
  were attempted and dropped — no correctness-guaranteed closed-form
  solver could be built with confidence, and the project's standing rule
  is never to ship a question type with an unverified answer formula.
- **Boolean-overlay (30/60 target) and Venn-consistency (36/100 target)**
  archetypes in Bank5 undershot their row-count targets — both are a real
  combinatorial ceiling of the generator (Venn: only 4 category-triples ×
  3×3 relation kinds = 36 distinct possible texts by design), not a bug.
