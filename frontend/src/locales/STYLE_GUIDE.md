# HelaIQ Sinhala Style Guide

This is the Sinhala "identity" of the product: one voice and one vocabulary across the student portal, the admin portal,
the question bank and the AI features. It is enforced by tooling (see the last section), not only by convention.

## 1. Voice

- **Register:** formal written Sinhala (ලිඛිත භාෂාව), polite and warm. Address the learner as **ඔබ**.
- **Buttons and menu items:** a short imperative ending in **-න්න** (`ආරම්භ කරන්න`, `සුරකින්න`) or a noun (`ප්‍රගතිය`). No full stop.
- **Sentences:** written endings (`කරයි`, `වේ`, `ඇත`, `කර ඇත`). **Never** spoken endings (`කළා`, `කරනවා`, `වෙනවා`) and never the spoken `මේ` (use `මෙම`).
- **Faithful, not literal:** every fact, number, condition and warning in the English must survive; sentence structure follows Sinhala, not English.
- **Numbers:** always Western digits (0-9). Keep `%`, units and `{{placeholders}}` exactly.
- **Allowed Latin words** (established loanwords): IQ, AI, ML, XP, CSV, UI/UX, SHAP, LIME, Google, Gemini, Rasch, point-biserial, Bloom, and the brand **HelaIQ**.
- **Length:** Sinhala runs about 1.3 to 1.6 times longer than English. Layouts must wrap (`flex-wrap`, no fixed widths); check every new screen at 375 px in Sinhala.

## 2. Vocabulary (use these words, everywhere)

| English | Sinhala | Avoid |
|---|---|---|
| Dashboard | උපකරණ පුවරුව | පුවරුව (alone) |
| Practice (drills) | අභ්‍යාසය | |
| Train / training | පුහුණු කරන්න / පුහුණුව | |
| Placement test | මට්ටම් නිර්ණය පරීක්ෂණය | ස්ථානගත කිරීම |
| Mock exam | ආදර්ශ විභාගය | මාදිලි විභාගය |
| Prediction / predicted | පුරෝකථනය / පුරෝකථනය කළ | අනාවැකි (means a prophecy) |
| Readiness | සූදානම | |
| Level | මට්ටම | |
| Progress | ප්‍රගතිය | |
| Streak | දින අඛණ්ඩතාව | |
| Study plan / notes | අධ්‍යයන සැලැස්ම / සටහන් | |
| Session | සැසිය | |
| Category | ප්‍රවර්ගය | |
| Area (of skill) | අංශය | ප්‍රදේශ (means a geographic region) |
| Badge | පදක්කම | බැඩ්ජ් |
| Leaderboard | ශ්‍රේණි පුවරුව | නායකත්ව පුවරුව (means "leadership board") |
| Daily check-in | දෛනික සටහන | චෙක්-ඉන් |
| Email | විද්‍යුත් තැපෑල | |
| AI coach | උපදේශක | කෝච් |
| Logical reasoning | තාර්කික චින්තනය | තාර්කික තර්කනය (redundant) |
| Cognitive | සංජානන | බුද්ධිමය |
| Selective attention | වරණීය අවධානය | තෝරාගත් අවධානය |
| Negative marking | ඍණ ලකුණු | |

Rank titles (XP ladder, `gamification.json` `widget.levelTitle.*`): 1 ආධුනික, 2 අධ්‍යාපනලාභී, 3 සාර්ථකත්වයට පත්වන්නා, 4 විද්වත්, 5 විශේෂඥ, 6 නිපුණ, 7 ප්‍රවීණ, 8 මහා ප්‍රවීණ, 9 ශූරයා, 10 අමරණීය.

## 3. What is translated where

| Text | Where it lives | How it reaches the screen |
|---|---|---|
| Interface labels | `frontend/src/locales/{en,si}/*.json` | i18next |
| ML reasons, feature names, the "why" sentence | `dashboard.json` `readiness.reasonText.*`, `featureLabel.*`, `explain.*` | built in the browser from the structured reasons, so it is Sinhala too (the ML service itself only speaks English) |
| Server errors shown to people | `common.json` `errors.api.*` via `lib/apiError.ts` | the server's English text is never displayed |
| Category, game and badge names | database columns `name_si`, `description_si` (seeded by `CategorySeeder`, `GameSeeder`, `BadgeSeeder`) | API |
| Questions, options, explanations | `questions.*_si` | API |
| Study plan phase labels and warnings | `StudyPlanService` | API (`label_si`, `message_si`) |
| AI coach and AI explanations | Gemini prompt (or the mock coach) | API |

## 4. How new Sinhala gets made (the process)

1. **Never hand-type new Sinhala.** Reuse an existing verified string or word first.
2. Draft with Gemini using the style guide above (the admin question form has a "Draft Sinhala from English" button that also injects `backend/resources/sinhala_glossary.json`).
3. **Gates** reject a draft automatically: characters from other scripts (machine drafts really do produce Kannada, Korean or Amharic letters), orphaned vowel signs, colloquial endings, changed placeholders, discouraged terms.
4. **Blind back-translation**: translate the Sinhala back to English without seeing the original and compare. Anything that drifts goes back for a rewrite.
5. **A person reads it.** Machine review cannot judge naturalness. `frontend/src/locales/REVIEW_LOG.md` lists every string that changed, with before and after, for a native reader to approve.

## 5. Enforcement

- `npm run check:locales` (frontend): key parity, placeholder parity, foreign scripts, orphaned signs, colloquial endings, discouraged terms.
- `python backend/tools/validate_sinhala.py --all`: every Sinhala word in the seeders must be in the verified corpus.
- `php artisan sinhala:audit`: every active question, option and explanation.
- `SinhalaTextGuard` blocks corrupted Sinhala when an admin saves a question or approves an AI draft.

## Typography (how Sinhala is set on screen)

- Font: Noto Sans Sinhala (variable, real weights) in the same stack as Geist, so Latin words and digits inside Sinhala sentences stay consistent.
- Size and leading: body about 17 px with 1.8 leading, small text about 13-15 px with 1.7; display sizes are slightly smaller than the Latin ones. Sinhala carries marks above and below the line and clips at Latin leading.
- No letter-spacing and no forced upper case on Sinhala. Do not put Sinhala in a fixed-height, `overflow-hidden` box: use `min-h-*` instead.
- All of this lives in one place, the `html:lang(si)` block in `frontend/src/index.css`; components should not set Sinhala-specific sizes themselves.
