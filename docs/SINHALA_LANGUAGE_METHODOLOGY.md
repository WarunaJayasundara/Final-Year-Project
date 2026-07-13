# Sinhala Language Methodology

How full bilingual (EN/SI) support is implemented and, more importantly,
how Sinhala text is protected from a specific real failure mode this
project hit twice.

## 1. The problem this process exists to prevent

Sinhala is written in a complex Brahmic script (conjunct consonants,
vowel signs, virama) that is easy to corrupt when composed
character-by-character from memory rather than copied from a verified
source — the wrong combining character, a stray codepoint from a visually
similar script (Malayalam/Telugu/Kannada share Unicode block
neighbourhoods with Sinhala), or garbled glyph ordering. This happened
twice during this project's history: once while hand-composing Bank5
seeder text, and again while first attempting to hand-type the Sinhala
terminology glossary — both times self-caught and discarded before being
committed, which is exactly why the process below is now mandatory rather
than a suggestion.

## 2. The corpus-validation tool

`backend/tools/validate_sinhala.py`:

1. **`--build-corpus`** scans every seeder file (recursively, `glob("**/*.php")`
   — a real bug where the original non-recursive glob silently excluded
   every `Bank2`–`Bank5` subdirectory was found and fixed this project) and
   every frontend locale JSON file, extracting every distinct Sinhala word
   into a verified-word corpus (`sinhala_corpus.json`, 1,398+ words as of
   this session).
2. **`--all`** validates all scanned content against two checks:
   - **`FORBIDDEN_RE`** — a regex catching stray codepoints from
     Malayalam/Telugu/Kannada Unicode blocks, the concrete signature of a
     corruption incident.
   - **Novel-word review** — for seeder files (which use
     `UNDER_REVIEW_MARKERS`, e.g. `Bank2`/`Bank3`/`Bank4`/`Bank5`), any
     word not already in the corpus must be explicitly listed in
     `APPROVED_NOVEL_WORDS` with a one-line comment explaining what it
     means and why it's needed. Frontend locale files are validated
     unconditionally (their content is taken as already-reviewed once
     added, no separate approval gate).

## 3. The mandatory workflow for any new Sinhala text

1. **Prefer reuse** — search the existing corpus/locale files for an
   already-verified phrase before writing anything new.
2. **If a genuinely new word is unavoidable**, verify it is a standard
   dictionary word (not a corrupted string), then add it to
   `APPROVED_NOVEL_WORDS` with a review-log comment.
3. **Never hand-compose novel Sinhala from memory, character by
   character.** New text should come from: reusing verified vocabulary, a
   verbatim copy from an already-read source (e.g. an uploaded PDF's own
   text), or a programmatically-extracted set of already-reviewed source
   pairs (see §4).
4. **Rebuild and validate** before considering any Sinhala change done:
   `python tools/validate_sinhala.py --build-corpus`, then
   `python tools/validate_sinhala.py --all`.

## 4. The glossary — built programmatically, not hand-typed

`backend/resources/sinhala_glossary.json` (terminology used in AI-question
prompts, e.g. direction sense / coding-decoding / blood relations /
time-speed-distance terms) was built **programmatically** from
already-reviewed source pairs (`CategorySeeder`, matching-key locale
files) via a Python extraction script for the base 48-entry version, and
extended with a 26-entry `exam_guide_topics_v2` section extracted
**verbatim** (copied character-for-character from PDF text already read
into the working session, never retyped) from a real uploaded Environmental
Officer exam guide. Both approaches deliberately avoid freehand
composition.

## 5. Structural translation-equivalence validation

`SinhalaSemanticValidationService` checks EN/SI question pairs for
structural equivalence — numeric-literal parity, option-count parity,
answer-key presence — and records `translation_status` /
`translation_quality_score` / `sinhala_review_status` /
`semantic_equivalence_score` on both `questions` and
`ai_generated_questions`. This is explicitly a **structural heuristic**,
not a claim of deep NLP semantic understanding — stated as such in the
service's own docblock, to avoid overclaiming what it actually checks.

## 6. Typography

`@fontsource/noto-sans-sinhala` is loaded and applied via a `:lang(si)`
CSS rule, so Sinhala text renders in a dedicated font rather than
whatever fallback the OS happens to provide (the app's primary English
typeface, Geist Variable, has no Sinhala glyphs at all). A related bug —
`<html lang>` was hardcoded to `"en"` and never updated when the user
switched language, meaning the `:lang(si)` rule could never actually fire
— was found and fixed by wiring an `i18n.on('languageChanged', ...)`
listener to keep `document.documentElement.lang` in sync.

## 7. Technical/acronym loanwords

Terms like SHAP, LIME, F1, ML, AI are kept as English loanwords embedded
in Sinhala sentences (e.g. "AI ප්‍රශ්න", "ML පර්යේෂණ") — an established,
deliberate precedent, not a shortcut taken because translating them was
too hard.

## 8. Current coverage

Every Sinhala string added across every session of this project — seeders
(Bank2–Bank5, ~150+ novel words with review-log entries), all frontend
locale namespaces, the terminology glossary — passes `--all` cleanly
against the current corpus. This document should be re-read (and the
process re-followed) before any future session adds new Sinhala text.
