# Question Bank Sinhala Fidelity Audit

Method: a stratified random sample of active text questions was translated **back to English from the Sinhala alone**, and compared with the original English
(numbers, names, conditions, option values and order, explanation). Machine-run and machine-judged, so a person should still read the flagged groups; it finds
meaning defects reliably and cannot judge naturalness.

## Result 1: before corrections

* Sample: **300** questions across every category and subcategory.
* Equivalent: **268**. Flagged: **32** (10.7%).

Flagged by group (count in the sample): `3|` 1, `2|` 1, `3|age_problems` 9, `3|speed_distance` 1, `2|syllogisms` 2, `2|blood_relations` 3, `2|direction_sense` 9, `2|coding_decoding` 3, `2|statement_sufficiency` 3.

### What was real, and fixed (live rows, seeders, content snapshot re-exported)

| Defect | Rows fixed |
|---|---|
| **Age problems**: "when *Nimal* was born" was written as "when *Nimal's father* was born", so the question was self-contradictory | 160 |
| Age problems: the typo `මව්කගේ` for "mother's" (`මවගේ`) | 160 |
| **Statement sufficiency**: the options "Statement I alone is sufficient, **but II alone is not**" lost the second half, making options A and B indistinguishable for a Sinhala reader | 147 |
| **Direction sense**: the "which direction" question was a telegraphic fragment ("P: north 15, south 15, east 8. What?") with no actual question | 80 |
| Direction sense: the word *shortest* was missing from "shortest distance" | 90 |
| **Coding-decoding**: telegraphic template ("each letter goes forward 6 places. SUN = ?") rewritten as complete questions | 150 |
| **Syllogisms**: "athletes" and "cricketers" were both `ක්‍රීඩකයන්` (the premise became a tautology); "performers" was rendered as "actors" | 16 + 26 |

### Flagged but correct (kept)

* `blood_relations`: "V is not male" is `V පිරිමි නොවේ`, which is right; the checker's back-translation wrongly said "not female".
* Two items where the checker returned no back-translation at all.
* Word-choice notes such as "travellers" as `සංචාරකයන්` (tourists) are acceptable for an aptitude exam.

## Result 2: after corrections

Re-sample of the corrected groups (age, direction sense, statement sufficiency, coding-decoding, syllogisms): **75** questions, equivalent **67**, flagged **8**.

* #20577 (3|age_problems): The back-translation changes 'sibling, 7 years younger' to 'younger brother/sister, who is 7 years younger than Kanchana', altering the condition.
* #20599 (3|age_problems): The back-translation changes 'sibling, 3 years younger' to 'younger brother/sister, who is 3 years younger than Kanchana', altering the condition.
* #20602 (3|age_problems): The back-translation changes 'sibling, 4 years younger' to 'younger brother/sister, who is 4 years younger than Kanchana', altering the condition.
* #21290 (2|syllogisms): The term 'craftsmen' in the original was translated as 'artisans' in the back-translation.
* #21324 (2|syllogisms): The term 'performers' in the original was translated as 'artists' in the back-translation.
* #24103 (2|statement_sufficiency): The option list order and text have been altered in the back-translation compared to the original.
* #24209 (2|statement_sufficiency): The option list order and text have been altered in the back-translation compared to the original.
* #24211 (2|statement_sufficiency): The option list order and text have been altered in the back-translation compared to the original.

## Result 3: the other question families (second audit)

A fresh stratified sample of **300 active text questions from the 29 category/subcategory groups the first audit did not cover** (everything except age problems, direction sense,
statement sufficiency, coding-decoding and syllogisms) went through the same blind back-translation: **300 of 300 equivalent, 0 flagged**.

That result was checked before being trusted: the same comparison step was run on 12 real rows whose Sinhala had been deliberately damaged (a number changed, two options swapped, a
sentence dropped) and it flagged **12 of 12**, so the clean result is not a lenient checker. Limits: it uses a small model for both steps, judges meaning and structure and not
naturalness, and a paraphrase that keeps every number and condition passes by design.

## Not covered

About 600 of 6,759 questions have now been sampled (first audit 300, second audit 300), and only text questions (image questions have short Sinhala captions). Unsampled rows are not
proven fault-free. A native reader should still skim a few of each family.
