# Sinhala Review Log

Every Sinhala string changed in the interface pass, for a **native Sinhala reader to approve**. The changes were drafted by a language model
(Gemini), filtered by automatic gates (foreign scripts, orphaned signs, colloquial endings, placeholders), and checked by blind back-translation.
None of that can judge naturalness, so this log is the hand-off. If a line looks wrong, edit the value in `frontend/src/locales/si/<file>.json`
(or the seeder for the database rows), run `npm run check:locales`, and tell the next session.

* Interface strings changed or added: **384** across 10 files.
* Database strings changed: **19** (categories, games, badges).
* Blind back-translation verdicts: 377 equivalent, 5 flagged (reviewed and kept, see notes below).
* The verified word corpus (`backend/tools/sinhala_corpus.json`) grew from 1,397 to about 1,680 words; every new word appears in the strings below.

## Flagged by the back-translation check (kept, with reason)

| String | Note |
|---|---|
| `admin.json|mlResearch.bestModel` | Changed 'Deployed model' to 'Control score' or similar divergence in translation. (kept after manual review) |
| `admin.json|mlResearch.table.score` | Changed 'Gating score' to 'Control score'. (kept after manual review) |
| `auth.json|signingIn` | Added specific app name 'HelaIQ'. (kept after manual review) |
| `dashboard.json|readiness.featureLabel.spatial_score` | Added 'and pattern recognition' which expands the original intended meaning. (kept after manual review) |
| `games.json|hub.subtitle` | Translation significantly alters 'streak' to 'integrity'. (kept after manual review) |

## Database rows

| Row | Before | After |
|---|---|---|
| `categories|logical_reasoning|name` | තාර්කික තර්කනය | තාර්කික චින්තනය |
| `categories|numerical_ability|description` | ගණිතමය ගැටළු, සංඛ්‍යා ශ්‍රේණි, අනුපාත සහ ප්‍රතිශත. | අංකගණිතය, සංඛ්‍යා ශ්‍රේණි, අනුපාත සහ ප්‍රතිශත. |
| `games|memory_match|description` | හැකි ඉක්මනින් ගැලපෙන කාඩ්පත් සොයාගන්න. | හැකි ඉක්මනින් ගැලපෙන යුගල සොයා ගැනීමට කාඩ්පත් හරවන්න. |
| `games|math_rush|description` | තත්පර 60ක් තුළ වේගවත් ගණිත ගැටළු විසඳන්න. | තත්පර 60ක වේගවත් ගණිත අභ්‍යාසයකි. ඔබට කොපමණ ප්‍රමාණයක් විසඳිය හැකිද? |
| `games|mental_rotation|description` | ඉලක්ක හැඩයේ සැබෑ භ්‍රමණය වන හැඩය තෝරන්න, දර්පණ රූපය නොවේ. | ඉලක්ක හැඩයේ දර්පණ රූපය නොව, එහි සැබෑ භ්‍රමණය වන හැඩය තෝරන්න. |
| `games|selective_attention|name` | තෝරාගත් අවධාන අභියෝගය | වරණීය අවධාන අභියෝගය |
| `games|working_memory_span|description` | ඉලක්කම් මතකය, ආපසු මතක කිරීම සහ 2-back යාවත්කාලීන කිරීම - වැඩිහිටි මට්ටමේ ක්‍රියාකාරී මතක පුහුණුව. | ඉලක්කම් මතකය, ආපසු සිහිපත් කිරීම සහ 2-back යාවත්කාලීන කිරීම - වැඩිහිටි මට්ටමේ ක්‍රියාකාරී මතක පුහුණුව. |
| `games|visual_spatial_memory|description` | දර්ශන විස්තර මතක තබාගෙන ජාල අනුක්‍රම ආපසු ටැප් කරන්න - දෘශ්‍ය හා අවකාශීය මතක පුහුණුව. | දර්ශන විස්තර මතක තබාගෙන ජාල අනුක්‍රම ආපසු තට්ටු කරන්න - පර්යේෂණ මට්ටමේ දෘශ්‍ය හා අවකාශීය මතක පුහුණුව. |
| `games|cognitive_command_center|name` | බුද්ධිමය විධාන මධ්‍යස්ථානය | සංජානන විධාන මධ්‍යස්ථානය |
| `badges|first_placement|description` | ඔබේ ස්ථානගත කිරීමේ පරීක්ෂණය සම්පූර්ණ කරන්න. | ඔබේ මට්ටම් නිර්ණය පරීක්ෂණය සම්පූර්ණ කරන්න. |
| `badges|streak_3|description` | දින 3ක අඛණ්ඩ පුහුණු ගණනයකට ළඟා වන්න. | දින 3ක දෛනික අභ්‍යාස අඛණ්ඩතාවක් ළඟා කර ගන්න. |
| `badges|streak_7|description` | දින 7ක අඛණ්ඩ පුහුණු ගණනයකට ළඟා වන්න. | දින 7ක දෛනික අඛණ්ඩතාවක් ළඟා කර ගන්න. |
| `badges|streak_14|name` | සති දෙකේ අවධානය | සති දෙකේ ඒකාග්‍රතාව |
| `badges|streak_14|description` | දින 14ක අඛණ්ඩ පුහුණු ගණනයකට ළඟා වන්න. | දින 14ක දෛනික අඛණ්ඩතාවක් ළඟා කර ගන්න. |
| `badges|streak_30|description` | දින 30ක අඛණ්ඩ පුහුණු ගණනයකට ළඟා වන්න. | දින 30ක දෛනික අඛණ්ඩතාවක් ළඟා කර ගන්න. |
| `badges|perfect_score|description` | ඕනෑම පරීක්ෂණ සැසියකින් 100% ලකුණු ලබා ගන්න. | ඕනෑම පරීක්ෂණ සැසියකින් 100%ක ලකුණු ලබා ගන්න. |
| `badges|level_3_reached|description` | IQ මට්ටම 3 ට ළඟා වන්න. | IQ මට්ටම 3 වෙත ළඟා වන්න. |
| `badges|level_5_reached|description` | වේදිකාවේ උපරිම IQ මට්ටම වන 5 ට ළඟා වන්න. | වේදිකාවේ උපරිම IQ මට්ටම වන 5 වෙත ළඟා වන්න. |
| `badges|exam_ready|description` | AI විභාග සූදානම් අනාවැකියෙන් "සූදානම්" තත්ත්වයට ළඟා වන්න. | AI විභාග සූදානම් පුරෝකථනයෙන් "සූදානම්" තත්ත්වයට ළඟා වන්න. |

## admin.json  (68)

| Key | English | Before | After |
|---|---|---|---|
| `feedback.subtitle` | Ratings and comments submitted by students. | සිසුන් විසින් එවන ලද ශ්‍රේණිගත කිරීම් සහ අදහස්. | සිසුන් විසින් ඉදිරිපත් කරන ලද ශ්‍රේණිගත කිරීම් සහ අදහස්. |
| `feedback.distribution` | Rating distribution | ශ්‍රේණිගත කිරීමේ බෙදාහැරීම | ශ්‍රේණිගත කිරීම් ව්‍යාප්තිය |
| `feedback.topTermsNote` | A simple word-frequency count over comments and suggestions - not sentiment analysis. | අදහස් සහ යෝජනා පිළිබඳ සරල වචන-සංඛ්‍යාතමය ගණනයක් - හැඟීම් විශ්ලේෂණයක් නොවේ. | අදහස් සහ යෝජනා ඇසුරින් කළ සරල වචන සංඛ්‍යාත ගණනයක් - හැඟීම් විශ්ලේෂණයක් නොවේ. |
| `feedback.markReviewed` | Mark reviewed | සමාලෝචනය කළා ලෙස සලකුණු කරන්න | සමාලෝචනය කළ බවට සලකුණු කරන්න |
| `feedback.pagination` | Page {{current}} of {{last}} | පිටුව {{current}} න් {{last}} | පිටුව {{current}} / {{last}} |
| `dashboard.exportCsvFailed` | Could not export the CSV. Please try again. | CSV අපනයනය කළ නොහැකි විය. නැවත උත්සාහ කරන්න. | CSV අපනයනය කළ නොහැකි විය. කරුණාකර නැවත උත්සාහ කරන්න. |
| `dashboard.placementCompleted` | Placement completed | ස්ථානගත කිරීම සම්පූර්ණයි | මට්ටම් නිර්ණය පරීක්ෂණය සම්පූර්ණයි |
| `questions.pagination` | Page {{current}} of {{last}} - {{total}} questions total | පිටුව {{current}} න් {{last}} - මුළු ප්‍රශ්න {{total}} | පිටුව {{current}} / {{last}} - මුළු ප්‍රශ්න සංඛ්‍යාව {{total}} කි |
| `questions.modeVisual` | Visual generator | දෘශ්‍ය ප්‍රශ්න උත්පාදනය | දෘශ්‍ය ප්‍රශ්න උත්පාදකය |
| `questions.visualGen.title` | Pattern / visual question generator | රටා / දෘශ්‍ය ප්‍රශ්න උත්පාදනය | රටා / දෘශ්‍ය ප්‍රශ්න උත්පාදකය |
| `questions.visualGen.patternMatrixSoon` | Matrix reasoning (coming soon) | Matrix තර්කනය (ඉදිරියේදී) | න්‍යාසික තාර්කික චින්තනය (ඉදිරියේදී) |
| `questions.visualGen.patternFoldingSoon` | Paper folding (coming soon) | Paper folding (ඉදිරියේදී) | කඩදාසි නැමීම (ඉදිරියේදී) |
| `users.new` | New admin | නව පරිපාලකයෙක් | නව පරිපාලක |
| `users.table.placementDone` | Placement done | ස්ථානගත කිරීම සම්පූර්ණයි | මට්ටම් නිර්ණය පරීක්ෂණය සම්පූර්ණයි |
| `users.table.placementPending` | Placement not started | ස්ථානගත කිරීම ආරම්භ කර නැත | මට්ටම් නිර්ණය පරීක්ෂණය ආරම්භ කර නැත |
| `form.translateTitle` | Sinhala text | _new_ | සිංහල පෙළ |
| `form.translateHint` | Write the English first, then draft the Sinhala automatically. A machine draft must be read and approved before it can be saved. | _new_ | පළමුව ඉංග්‍රීසි ලියන්න, ඉන්පසු ස්වයංක්‍රීයව සිංහල කෙටුම්පතක් සකස් කර ගන්න. සුරැකීමට පෙර යන්ත්‍රගත කෙටුම්පත කියවා අනුමත කළ යුතුය. |
| `form.translateButton` | Draft Sinhala from English | _new_ | ඉංග්‍රීසි භාෂාවෙන් සිංහල කෙටුම්පත් කරන්න |
| `form.translating` | Translating... | _new_ | පරිවර්තනය කරමින් පවතී... |
| `form.translateOverwrite` | This replaces the Sinhala text already in the form. Continue? | _new_ | මෙය දැනට පෝරමයේ ඇති සිංහල පෙළ ප්‍රතිස්ථාපනය කරයි. ඉදිරියට යන්නද? |
| `form.translateNothing` | Every Sinhala field already has text, so nothing was drafted. Clear a field to draft it again. | _new_ | සෑම සිංහල ක්ෂේත්‍රයකම දැනටමත් පෙළ අඩංගු වන බැවින්, කිසිවක් කෙටුම්පත් කර නැත. එය නැවත කෙටුම්පත් කිරීමට කරුණාකර ක්ෂේත්‍රයක් හිස් කරන්න. |
| `form.translateNeedsEnglish` | Enter the English question text first. | _new_ | පළමුව ඉංග්‍රීසි ප්‍රශ්න පෙළ ඇතුළත් කරන්න. |
| `form.translateFailed` | Automatic translation is not available right now. | _new_ | ස්වයංක්‍රීය පරිවර්තනය දැනට ලබා ගත නොහැක. |
| `form.machineDraftNotice` | Machine draft. Read every Sinhala field and correct anything unnatural or unclear. | _new_ | යන්ත්‍රගත කෙටුම්පතකි. සෑම සිංහල ක්ෂේත්‍රයක්ම කියවා ස්වභාවික නොවන හෝ අපැහැදිලි යමක් වේ නම් නිවැරදි කරන්න. |
| `form.reviewLabel` | I have read the Sinhala text. It is correct and easy to understand. | _new_ | මම සිංහල පෙළ කියවා ඇත. එය නිවැරදි වන අතර තේරුම් ගැනීමට පහසුය. |
| `form.reviewRequired` | Tick the review box to confirm the machine-drafted Sinhala. | _new_ | යන්ත්‍රයෙන් සකස් කළ සිංහල පෙළ තහවුරු කිරීමට සමාලෝචන කොටුව සලකුණු කරන්න. |
| `form.sinhalaBlocked` | Fix the corrupted Sinhala text marked in red before saving. | _new_ | සුරැකීමට පෙර රතු පැහැයෙන් සලකුණු කර ඇති දූෂිත සිංහල පෙළ නිවැරදි කරන්න. |
| `form.examTagsPlaceholder` | Comma-separated, e.g. slas, gov_aptitude | උදා: slas, gov_aptitude | කොමාවකින් වෙන් කර දක්වන්න, උදා: slas, gov_aptitude |
| `form.wizard.checkSinhala` | Sinhala text passes the integrity check | _new_ | සිංහල පෙළ අඛණ්ඩතා පරීක්ෂණය සමත් වේ |
| `form.wizard.checkSinhalaReviewed` | Machine-drafted Sinhala has been reviewed | _new_ | යන්ත්‍ර මඟින් සැකසූ සිංහල පාඨය සමාලෝචනය කර ඇත |
| `form.wizard.checkOptions` | All answer options have text (English and Sinhala) | සියලුම විකල්පවල පෙළ (ඉංග්‍රීසි සහ සිංහල) ඇත | සියලුම පිළිතුරු විකල්පවල පෙළ (ඉංග්‍රීසි සහ සිංහල) ඇත |
| `aiQuestions.count` | How many | කීයක් | ප්‍රමාණය |
| `aiQuestions.examContextNone` | General (no specific exam) | සාමාන්‍ය (විශේෂිත විභාගයක් නැත) | සාමාන්‍ය (විශේෂිත විභාගයක් නොමැත) |
| `aiQuestions.generateSuccess` | Generated {{count}} draft question(s) for review. | සමාලෝචනය සඳහා කෙටුම්පත් ප්‍රශ්න {{count}}ක් උත්පාදනය කරන ලදී. | සමාලෝචනය සඳහා කෙටුම්පත් ප්‍රශ්න {{count}}ක් උත්පාදනය කර ඇත. |
| `aiQuestions.generateNone` | No drafts were created. The AI service may be busy, or the offline generator has no Sinhala wording for this category. Try again in a minute. | _new_ | කෙටුම්පත් කිසිවක් සාදා නොමැත. AI සේවාව කාර්යබහුල විය හැක, නැතහොත් නොබැඳි උත්පාදක යන්ත්‍රය සතුව මෙම ප්‍රවර්ගය සඳහා සිංහල වචන නොමැත. මිනිත්තුවකින් නැවත උත්සාහ කරන්න. |
| `aiQuestions.selectedCount` | {{count}} selected | තෝරාගත් ප්‍රශ්න: {{count}} | {{count}}ක් තෝරාගෙන ඇත |
| `aiQuestions.approveSelected` | Approve selected | තෝරාගත් ප්‍රශ්න අනුමත කරන්න | තෝරාගත් ඒවා අනුමත කරන්න |
| `aiQuestions.sourceDocumentNone` | None (freeform generation) | කිසිවක් නොමැත (සාමාන්‍ය උත්පාදනය) | කිසිවක් නොමැත (නිදහස් උත්පාදනය) |
| `aiQuestions.sinhalaNeedsReview` | Sinhala needs review | සිංහල සමාලෝචනය අවශ්‍ය | සිංහල සමාලෝචනය අවශ්‍ය වේ |
| `knowledgeLibrary.subtitle` | Upload reference PDFs (past papers, IQ/aptitude books, exam guides) as topic and style inspiration for question generation - source material only, never copied verbatim. | ප්‍රශ්න උත්පාදනය සඳහා ලේඛන උඩුගත කරන්න. | ප්‍රශ්න උත්පාදනය සඳහා මාර්ගෝපදේශ ලෙස යොමු PDF ගොනු (පසුගිය විභාග ප්‍රශ්න පත්‍ර, IQ සහ නැඹුරුතා පොත්, විභාග මාර්ගෝපදේශ) උඩුගත කරන්න. මේවා මූලාශ්‍ර ද්‍රව්‍ය පමණක් වන අතර, කිසි විටෙකත් වචනයෙන් වචනය පිටපත් නොකෙරේ. |
| `knowledgeLibrary.uploadTitle` | Upload a document | ලේඛනය උඩුගත කරන්න | ලේඛනයක් උඩුගත කරන්න |
| `knowledgeLibrary.documentTypePastPaper` | Past paper | විභාග ලේඛනය | පසුගිය ප්‍රශ්න පත්‍රය |
| `knowledgeLibrary.documentTypeIqBook` | IQ / aptitude book | IQ පොත | IQ / යෝග්‍යතා පොත |
| `knowledgeLibrary.documentTypeExamGuide` | Exam guide | විභාග සූදානම් ලේඛනය | විභාග මාර්ගෝපදේශය |
| `knowledgeLibrary.year` | Year (optional) | වර්ෂය | වර්ෂය (අත්‍යවශ්‍ය නොවේ) |
| `knowledgeLibrary.statusAnalyzed` | Analyzed | සම්පූර්ණ | විශ්ලේෂණය කරන ලදී |
| `knowledgeLibrary.matchedTopics` | Matched topics (keyword heuristic) | තේමා | සමපාත වූ තේමා (මූලපද අනුමාන ක්‍රමය) |
| `knowledgeLibrary.knowledgeMap` | Knowledge map (chapters) | පරිච්ඡේදය | දැනුම් සිතියම (පරිච්ඡේද) |
| `knowledgeLibrary.moreChapters` | more chapters | තවත් | තවත් පරිච්ඡේද |
| `knowledgeLibrary.generateFromDocument` | Generate questions from this document | මෙම ලේඛනය සඳහා ප්‍රශ්න උත්පාදනය කරන්න | මෙම ලේඛනයෙන් ප්‍රශ්න උත්පාදනය කරන්න |
| `knowledgeLibrary.noDocuments` | No documents uploaded yet. | ලේඛන උඩුගත කර නොමැත. | තවමත් ලේඛන උඩුගත කර නොමැත. |
| `knowledgeLibrary.reliabilityNote` | Reliability note | විශ්වසනීයත්වය - සටහන | විශ්වසනීයතා සටහන |
| `knowledgeLibrary.generateNoteSuccess` | Study note draft generated - review it below. | සටහන් කෙටුම්පත උත්පාදනය කරන ලදී. | අධ්‍යයන සටහන් කෙටුම්පත උත්පාදනය කරන ලදී - පහතින් එය සමාලෝචනය කරන්න. |
| `knowledgeLibrary.generateNoteError` | Could not generate a study note from this document. | සටහන උත්පාදනය කළ නොහැකි විය. | මෙම ලේඛනයෙන් අධ්‍යයන සටහනක් උත්පාදනය කළ නොහැකි විය. |
| `knowledgeLibrary.draftNotesTitle` | Draft study notes awaiting review | සමාලෝචනය අපේක්ෂිත සටහන් කෙටුම්පත් | සමාලෝචනය සඳහා රැඳී සිටින අධ්‍යයන සටහන් කෙටුම්පත් |
| `knowledgeLibrary.noDraftNotes` | No draft study notes waiting for review. | සමාලෝචනය සඳහා කෙටුම්පත් නොමැත. | සමාලෝචනය කිරීමට නියමිත අධ්‍යයන සටහන් කෙටුම්පත් කිසිවක් නොමැත. |
| `knowledgeLibrary.publishSuccess` | Study note published - now visible to students. | සටහන ප්‍රකාශයට පත් කරන ලදී - සිසුන්ට පෙනේ. | අධ්‍යයන සටහන ප්‍රකාශයට පත් කරන ලදී - දැන් සිසුන්ට දර්ශනය වේ. |
| `questionBank.title` | Question Bank Stats | ප්‍රශ්න බැංකුව: විශ්ලේෂණය | ප්‍රශ්න බැංකු සංඛ්‍යාලේඛන |
| `questionBank.subtitle` | Composition of the active competitive-exam question bank across categories, levels, and archetypes. | ප්‍රවර්ග, මට්ටම් සහ වර්ග අනුව සක්‍රීය ප්‍රශ්න බැංකුව. | ප්‍රවර්ග, මට්ටම් සහ ප්‍රරූප හරහා සක්‍රීය තරග විභාග ප්‍රශ්න බැංකුවේ සංයුතිය. |
| `questionBank.untagged` | Missing exam tags | ටැග් නොමැති | විභාග ටැග් නොමැති ප්‍රශ්න |
| `questionBank.bySubcategory` | By subcategory | වර්ගය අනුව | උප ප්‍රවර්ගය අනුව |
| `questionBank.byBloomLevel` | By Bloom's taxonomy level | Bloom's මට්ටම අනුව | Bloom මට්ටම අනුව |
| `mlResearch.subtitle` | Exam readiness model: training composition, evaluation results, explainability, and version history. | විභාග සූදානම් ආකෘතිය: පුහුණු දත්ත, ඇගයීම් ප්‍රතිඵල, විශ්ලේෂණය සහ අනුවාද ඉතිහාසය. | විභාග සූදානම් ආකෘතිය: පුහුණු සංයුතිය, ඇගයීම් ප්‍රතිඵල, පැහැදිලි කළ හැකි බව සහ අනුවාද ඉතිහාසය. |
| `mlResearch.studentsWithPrediction` | Students with a prediction | අනාවැකියක් ඇති සිසුන් | පුරෝකථනයක් සහිත සිසුන් |
| `mlResearch.avgReadiness` | Average readiness | සාමාන්‍ය සූදානම් | සාමාන්‍ය සූදානම |
| `mlResearch.bestModel` | Deployed model | සක්‍රීය ආකෘතිය | යොදවා ඇති ආකෘතිය |
| `mlResearch.noEvaluationYet` | No evaluation report yet - run evaluate.py on the ML service. | තවම ඇගයීම් වාර්තාවක් නැත - ML සේවාවේ evaluate.py ධාවනය කරන්න. | තවම ඇගයීම් වාර්තාවක් නොමැත - ML සේවාව මත evaluate.py ධාවනය කරන්න. |
| `mlResearch.table.score` | Gating score | ලකුණු | පාලන ලකුණු |

## auth.json  (7)

| Key | English | Before | After |
|---|---|---|---|
| `signingIn` | Signing you in... | ඔබව පුරන්නා ... | ඔබව HelaIQ වෙත පිවිසෙමින් පවතී... |
| `fieldRequired` | This field is required. | මෙම කොටස අවශ්‍යයි. | මෙම කොටස අවශ්‍ය වේ. |
| `registerSubtitle` | Start training your mind for free. | ඔබේ මනස නොමිලේ පුහුණු කිරීම අරඹන්න. | ඔබේ මනස නොමිලේ පුහුණු කිරීම ආරම්භ කරන්න. |
| `passwordTooShort` | Password must be at least 8 characters. | මුරපදයේ අවම වශයෙන් අකුරු 8ක් තිබිය යුතුය. | මුරපදය අවම වශයෙන් අකුරු 8කින් සමන්විත විය යුතුය. |
| `registerFailed` | Could not create your account. Please check your details and try again. | ඔබේ ගිණුම සෑදිය නොහැකි විය. විස්තර පරීක්ෂා කර නැවත උත්සාහ කරන්න. | ඔබේ ගිණුම සෑදීමට නොහැකි විය. කරුණාකර ඔබේ විස්තර පරීක්ෂා කර නැවත උත්සාහ කරන්න. |
| `resetLinkSent` | If an account exists for that email, a reset link has been sent. | එම විද්‍යුත් තැපෑලට ගිණුමක් තිබේ නම්, යළි පිහිටුවීමේ සබැඳිය එවා ඇත. | එම විද්‍යුත් තැපෑල සඳහා ගිණුමක් තිබේ නම්, යළි පිහිටුවීමේ සබැඳියක් එවා ඇත. |
| `sending` | Sending... | එවමින්... | යවමින් පවතී... |

## coach.json  (5)

| Key | English | Before | After |
|---|---|---|---|
| `title` | HelaIQ Coach | HelaIQ කෝච් | HelaIQ උපදේශක |
| `openLabel` | Open coach chat | කෝච් චැට් විවෘත කරන්න | උපදේශක කතාබහ විවෘත කරන්න |
| `closeLabel` | Close chat | චැට් වසන්න | කතාබහ වසන්න |
| `greeting` | Hi! Ask me about your progress, what to practice next, which game to play, or your IQ score. | ආයුබෝවන්! ඔබේ දියුණුව, ඊළඟට පුහුණු කළ යුතු දේ, ක්‍රීඩා කළ යුතු ක්‍රීඩාව, හෝ ඔබේ IQ ලකුණු ගැන මගෙන් අහන්න. | ආයුබෝවන්! ඔබේ ප්‍රගතිය, ඊළඟට පුහුණු කළ යුතු අභ්‍යාසය, ක්‍රීඩා කළ යුතු ක්‍රීඩාව හෝ ඔබේ IQ ලකුණු පිළිබඳව මගෙන් විමසන්න. |
| `placeholder` | Ask about your progress, practice, or IQ score... | ඔබේ දියුණුව, පුහුණුව, හෝ IQ ලකුණු ගැන අහන්න... | ඔබේ ප්‍රගතිය, පුහුණුව හෝ IQ ලකුණු පිළිබඳව විමසන්න... |

## common.json  (43)

| Key | English | Before | After |
|---|---|---|---|
| `notFound.title` | We could not find that page | _new_ | අපට එම පිටුව සොයාගත නොහැකි විය |
| `notFound.body` | The link may be old or mistyped. Head back to the start and pick up where you left off. | _new_ | මෙම සබැඳිය පැරණි විය හැක හෝ වැරදි ලෙස ඇතුළත් කර ඇත. ආරම්භක පිටුවට ගොස් ඔබ නැවැත්වූ තැන සිට නැවත ආරම්භ කරන්න. |
| `notFound.home` | Back to home | _new_ | මුල් පිටුවට යන්න |
| `nav.dashboard` | Dashboard | පුවරුව | උපකරණ පුවරුව |
| `nav.mockExam` | Mock Exam | මාදිලි විභාගය | ආදර්ශ විභාගය |
| `nav.badges` | Badges | බැඩ්ජ් | පදක්කම් |
| `nav.leaderboard` | Leaderboard | නායකත්ව පුවරුව | ශ්‍රේණි පුවරුව |
| `nav.logout` | Log out | පිටවීම | පිටවන්න |
| `categories.logical_reasoning` | Logical Reasoning | තාර්කික තර්කනය | තාර්කික චින්තනය |
| `roles.user` | Student | සිසුවා | සිසුවා / සිසුවිය |
| `errors.generic` | Something went wrong. Please try again. | යමක් වැරදුණි. නැවත උත්සාහ කරන්න. | යමක් වැරදී ඇත. කරුණාකර නැවත උත්සාහ කරන්න. |
| `errors.api.invalidCredentials` | Incorrect email/username or password. | _new_ | විද්‍යුත් තැපෑල හෝ පරිශීලක නාමය හෝ මුරපදය වැරදිය. |
| `errors.api.googleAccount` | This account uses Google sign-in. Choose "Continue with Google" instead. | _new_ | පරිශීලක පිවිසුම් දෝෂයකි: මෙම ගිණුම Google හරහා සාදා ඇති බැවින්, කරුණාකර ඒ වෙනුවට Google මඟින් පිවිසෙන්න. 'Google සමඟ ඉදිරියට' බොත්තම භාවිතා කරන්න. |
| `errors.api.validationFailed` | Please check your details and try again. | _new_ | කරුණාකර ඔබේ විස්තර පරීක්ෂා කර නැවත උත්සාහ කරන්න. |
| `errors.api.emailTaken` | An account with this email already exists. | _new_ | මෙම විද්‍යුත් තැපැල් ලිපිනය සහිත ගිණුමක් දැනටමත් පැවතේ. |
| `errors.api.usernameTaken` | This username is already taken. Please choose another. | _new_ | මෙම පරිශීලක නාමය දැනටමත් භාවිතා කර ඇත. කරුණාකර වෙනත් එකක් තෝරන්න. |
| `errors.api.usernameFormat` | Usernames can only contain letters, numbers and underscores. | _new_ | පරිශීලක නාමයේ අකුරු, ඉලක්කම් සහ අඩිරේඛා (_) පමණක් තිබිය හැක. |
| `errors.api.passwordShort` | Your password is too short. Use at least 8 characters. | _new_ | මුරපදය අවම වශයෙන් අකුරු 8කින් සමන්විත විය යුතුය. |
| `errors.api.passwordMismatch` | The two passwords do not match. | _new_ | මුරපද දෙක එකිනෙකට නොගැලපේ. |
| `errors.api.tooYoung` | You must be at least 10 years old to register. | _new_ | ලියාපදිංචි වීම සඳහා ඔබ අවම වශයෙන් වයස අවුරුදු 10ක් විය යුතුය. |
| `errors.api.invalidBirthDate` | Please enter a valid date of birth. | _new_ | කරුණාකර වලංගු උපන් දිනයක් ඇතුළත් කරන්න. |
| `errors.api.invalidEmail` | Please enter a valid email address. | _new_ | කරුණාකර වලංගු විද්‍යුත් තැපැල් ලිපිනයක් ඇතුළත් කරන්න. |
| `errors.api.placementRequired` | Complete the placement test first, then come back here. | _new_ | පළමුව මට්ටම් නිර්ණය පරීක්ෂණය සම්පූර්ණ කර, පසුව මෙහි පැමිණෙන්න. |
| `errors.api.placementDone` | You have already completed the placement test. | _new_ | ඔබ දැනටමත් මට්ටම් නිර්ණය පරීක්ෂණය සම්පූර්ණ කර ඇත. |
| `errors.api.noQuestions` | There are not enough questions for this yet. Please try again later. | _new_ | මෙම අංශය සඳහා ප්‍රමාණවත් ප්‍රශ්න ප්‍රමාණයක් තවමත් සූදානම් කර නැත. කරුණාකර පසුව උත්සාහ කරන්න. |
| `errors.api.sessionExpired` | Your session has expired. Please log in again. | _new_ | ඔබේ සැසිය කල් ඉකුත් වී ඇත. කරුණාකර නැවත පිවිසෙන්න. |
| `errors.api.forbidden` | You do not have permission to do this. | _new_ | මෙය සිදු කිරීමට ඔබට අවසර නැත. |
| `errors.api.tooMany` | Too many requests. Please wait a moment and try again. | _new_ | ඉල්ලීම් අධිකය. කරුණාකර මොහොතක් රැඳී සිට නැවත උත්සාහ කරන්න. |
| `errors.api.server` | Something went wrong on our side. Please try again shortly. | _new_ | අපගේ පද්ධතියේ දෝෂයක් සිදුව ඇත. කරුණාකර කෙටි මොහොතකින් නැවත උත්සාහ කරන්න. |
| `errors.api.offline` | Cannot reach the server. Check your internet connection and try again. | _new_ | සේවාදායකය වෙත ළඟා විය නොහැක. ඔබේ අන්තර්ජාල සබඳතාව පරීක්ෂා කර නැවත උත්සාහ කරන්න. |
| `landing.badge` | Built for Sri Lankan students & exam candidates | ශ්‍රී ලාංකික සිසුන් සහ විභාග අපේක්ෂකයින් සඳහා නිර්මාණය කළා | ශ්‍රී ලාංකික සිසුන් සහ විභාග අපේක්ෂකයින් සඳහා නිර්මාණය කර ඇත |
| `landing.heroTagline` | Train smarter. Think faster. Prepare with purpose. | ඔබේ මනස පුහුණු කරන්න. ඔබේ දියුණුව මනින්න. | වඩාත් බුද්ධිමත්ව පුහුණු කරන්න. වේගයෙන් සිතන්න. අරමුණින් යුතුව සූදානම් වන්න. |
| `landing.howItWorks.steps.assess.title` | Take the placement test | ස්ථානගත කිරීමේ පරීක්ෂණය කරන්න | මට්ටම් නිර්ණය පරීක්ෂණය කරන්න |
| `landing.howItWorks.steps.assess.description` | A short adaptive test finds your starting level across five cognitive areas. | කෙටි අනුවර්තී පරීක්ෂණයක් සංජානන අංශ පහක් හරහා ඔබේ ආරම්භක මට්ටම මනී. | කෙටි අනුවර්තී පරීක්ෂණයක් සංජානන අංශ පහක් හරහා ඔබේ ආරම්භක මට්ටම තීරණය කරයි. |
| `landing.howItWorks.steps.track.description` | See your progress, predicted exam readiness, and what to study next. | ඔබේ දියුණුව, අනාවැකි කළ විභාග සූදානම සහ ඊළඟට අධ්‍යයනය කළ යුතු දේ බලන්න. | ඔබේ ප්‍රගතිය, පුරෝකථනය කළ විභාග සූදානම සහ ඊළඟට අධ්‍යයනය කළ යුතු දේ බලන්න. |
| `landing.skillAreas.description` | Every question is tagged to one of these areas, so your progress is easy to follow. | සෑම ප්‍රශ්නයක්ම මෙම අංශයන්ගෙන් එකකට සම්බන්ධ වේ, එබැවින් ඔබේ දියුණුව නිරීක්ෂණය කිරීම පහසුය. | සෑම ප්‍රශ්නයක්ම මෙම අංශයන්ගෙන් එකකට සම්බන්ධ වේ, එබැවින් ඔබේ ප්‍රගතිය නිරීක්ෂණය කිරීම පහසුය. |
| `landing.features.adaptive.description` | A placement test measures your starting level across memory, logic, numbers, attention and pattern recognition, then adapts as you improve. | ස්ථානගත කිරීමේ පරීක්ෂණයක් මතකය, තර්කනය, සංඛ්‍යා, අවධානය සහ රටා හඳුනාගැනීම හරහා ඔබේ ආරම්භක මට්ටම මනින අතර, ඔබ දියුණු වන විට එය අනුවර්තනය වේ. | මට්ටම් නිර්ණය පරීක්ෂණයක් මතකය, තර්කනය, සංඛ්‍යා, අවධානය සහ රටා හඳුනාගැනීම හරහා ඔබේ ආරම්භක මට්ටම මනින අතර, ඔබ දියුණු වන විට එය අනුවර්තනය වේ. |
| `landing.features.examPrep.description` | Set your exam date, take full-length mock exams, and follow a study plan that adjusts as the date gets closer. | ඔබේ විභාග දිනය සකසන්න, සම්පූර්ණ මාදිලි විභාග කරන්න, සහ දිනය ළං වන විට වෙනස් වන අධ්‍යයන සැලැස්මක් අනුගමනය කරන්න. | ඔබේ විභාග දිනය සකසන්න, සම්පූර්ණ ආදර්ශ විභාග කරන්න, සහ දිනය ළං වන විට වෙනස් වන අධ්‍යයන සැලැස්මක් අනුගමනය කරන්න. |
| `landing.features.progress.title` | Progress you can see | ඔබට දැකිය හැකි දියුණුවක් | ඔබට දැකිය හැකි ප්‍රගතිය |
| `landing.features.games.description` | Short games keep memory, attention and reasoning sharp between full sessions. | කෙටි ක්‍රීඩා මතකය, අවධානය සහ තර්කනය සජීවීව තබයි. | කෙටි ක්‍රීඩා සම්පූර්ණ සැසි අතරතුර මතකය, අවධානය සහ තාර්කික චින්තනය තියුණු ලෙස පවත්වා ගනී. |
| `landing.features.explanations.title` | Explained, not just marked | පැහැදිලි කිරීම් සමඟ | ලකුණු ලබා දීම පමණක් නොව, පැහැදිලි කිරීමද සිදු කරයි |
| `landing.features.explanations.description` | Every wrong answer gets a clear, friendly explanation so mistakes turn into understanding. | සෑම වැරදි පිළිතුරකටම පැහැදිලි, හිතකාමී පැහැදිලි කිරීමක් ලැබෙන අතර එමගින් වැරදි වැටහීම බවට පත් වේ. | සෑම වැරදි පිළිතුරකටම පැහැදිලි හා මිත්‍රශීලී පැහැදිලි කිරීමක් ලැබෙන බැවින්, ඔබට ඔබේ වැරදිවලින් ඉගෙන ගත හැකිය. |
| `landing.features.bilingual.description` | Use HelaIQ in whichever language you study best in. | ඔබට වඩාත් හුරු භාෂාවෙන් HelaIQ භාවිත කරන්න. | ඔබට වඩාත්ම හොඳින් අධ්‍යයනය කළ හැකි භාෂාවෙන් HelaIQ භාවිත කරන්න. |

## dashboard.json  (181)

| Key | English | Before | After |
|---|---|---|---|
| `moreProgress` | More about your progress | ඔබේ දියුණුව පිළිබඳ තවත් | ඔබේ ප්‍රගතිය පිළිබඳ වැඩිදුර තොරතුරු |
| `practiceStreak` | Practice streak | පුහුණු අඛණ්ඩතාව | පුහුණු දින අඛණ්ඩතාව |
| `gamesPlayed` | Games played | ක්‍රීඩා කළ ගණන | ක්‍රීඩා කළ වාර ගණන |
| `readiness.refresh` | Run prediction | අනාවැකිය ක්‍රියාත්මක කරන්න | පුරෝකථනය ක්‍රියාත්මක කරන්න |
| `readiness.noPredictionYet` | No prediction yet - click "Run prediction" to get your first cognitive readiness estimate. | තවම අනාවැකියක් නැත - ඔබේ පළමු සංජානන සූදානම් ඇස්තමේන්තුව ලබා ගැනීමට "අනාවැකිය ක්‍රියාත්මක කරන්න" ක්ලික් කරන්න. | තවම පුරෝකථනයක් නැත - ඔබේ පළමු සංජානන සූදානම් ඇස්තමේන්තුව ලබා ගැනීමට "පුරෝකථනය ක්‍රියාත්මක කරන්න" මත ක්ලික් කරන්න. |
| `readiness.predictionUpdated` | Exam readiness prediction updated. | විභාග සූදානම් අනාවැකිය යාවත්කාලීන කරන ලදී. | විභාග සූදානම් පුරෝකථනය යාවත්කාලීන කර ඇත. |
| `readiness.predictionFailed` | Could not reach the prediction service. Please try again shortly. | අනාවැකි සේවාවට සම්බන්ධ විය නොහැකි විය. කරුණාකර මොහොතකින් නැවත උත්සාහ කරන්න. | පුරෝකථන සේවාව හා සම්බන්ධ විය නොහැකිය. කරුණාකර මොහොතකින් නැවත උත්සාහ කරන්න. |
| `readiness.dropRisk` | Risk of dropping practice | පුහුණුව අඩුවීමේ අවදානම | පුහුණුව අත්හැර දැමීමේ අවදානම |
| `readiness.predictedNextScore` | Predicted next score | ඊළඟ ලකුණු අනාවැකිය | පුරෝකථනය කළ මීළඟ ලකුණු |
| `readiness.timeManagementReadiness` | Time management readiness | කාල කළමනාකරණය සූදානම | කාල කළමනාකරණ සූදානම |
| `readiness.confidenceNote` | This is a research-grade model estimate, not a guaranteed prediction of your actual exam outcome. | මෙය පර්යේෂණ ඇස්තමේන්තුවක් වේ. | මෙය පර්යේෂණ මට්ටමේ ආකෘති ඇස්තමේන්තුවක් වන අතර, ඔබේ සැබෑ විභාග ප්‍රතිඵලය සඳහා ලබා දී ඇති සහතික කළ පුරෝකථනයක් නොවේ. |
| `readiness.labels.almost_ready` | Almost Ready | ආසන්නයෙන් සූදානම් | ආසන්න වශයෙන් සූදානම් |
| `readiness.labels.needs_improvement` | Needs Improvement | දියුණු කළ යුතුයි | දියුණු කළ යුතුය |
| `readiness.checkinButton` | Daily check-in | දෛනික චෙක්-ඉන් | දෛනික සටහන |
| `readiness.checkinTitle` | Today's check-in | අද දින චෙක්-ඉන් | අද දෛනික සටහන |
| `readiness.attendedToday` | I studied/attended today | මම අද අධ්‍යයනය කළෙමි / පැමිණියෙමි | මම අද අධ්‍යයනය කළෙමි |
| `readiness.checkinSaved` | Check-in saved. | චෙක්-ඉන් සුරකින ලදී. | දෛනික සටහන සුරකින ලදී. |
| `readiness.reasonText.placement_iq.pos` | Strong baseline ability at placement | _new_ | මට්ටම් නිර්ණයේදී දැක්වූ ප්‍රබල මූලික හැකියාව |
| `readiness.reasonText.placement_iq.neg` | Lower baseline ability at placement | _new_ | මට්ටම් නිර්ණයේදී දැක්වූ අඩු මූලික හැකියාව |
| `readiness.reasonText.current_iq.pos` | Strong current IQ estimate | _new_ | ප්‍රබල වත්මන් IQ ඇස්තමේන්තුව |
| `readiness.reasonText.current_iq.neg` | Current IQ estimate below target | _new_ | ඉලක්කයට වඩා අඩු වත්මන් IQ ඇස්තමේන්තුව |
| `readiness.reasonText.theta.pos` | High measured cognitive ability (IRT theta) | _new_ | ඉහළ මැනගත් සංජානන හැකියාව (IRT තීටා) |
| `readiness.reasonText.theta.neg` | Cognitive ability estimate needs strengthening | _new_ | සංජානන හැකියාව පිළිබඳ ඇස්තමේන්තුව ශක්තිමත් කළ යුතුය |
| `readiness.reasonText.avg_test_score.pos` | High average test scores | _new_ | ඉහළ සාමාන්‍ය පරීක්ෂණ ලකුණු |
| `readiness.reasonText.avg_test_score.neg` | Average test scores below target | _new_ | ඉලක්කයට වඩා අඩු සාමාන්‍ය පරීක්ෂණ ලකුණු |
| `readiness.reasonText.memory_score.pos` | Excellent memory performance | _new_ | විශිෂ්ට මතක ක්‍රියාකාරිත්වය |
| `readiness.reasonText.memory_score.neg` | Weak memory performance | _new_ | දුර්වල මතක ක්‍රියාකාරිත්වය |
| `readiness.reasonText.logical_score.pos` | Excellent logical reasoning | _new_ | විශිෂ්ට තාර්කික චින්තනය |
| `readiness.reasonText.logical_score.neg` | Weak logical reasoning | _new_ | දුර්වල තාර්කික චින්තනය |
| `readiness.reasonText.numerical_score.pos` | Excellent numerical ability | _new_ | විශිෂ්ට සංඛ්‍යාත්මක හැකියාව |
| `readiness.reasonText.numerical_score.neg` | Weak numerical ability | _new_ | දුර්වල සංඛ්‍යාත්මක හැකියාව |
| `readiness.reasonText.attention_score.pos` | Strong attention/focus performance | _new_ | ප්‍රබල අවධාන/ඒකාග්‍රතා ක්‍රියාකාරිත්වය |
| `readiness.reasonText.attention_score.neg` | Weak attention/focus performance | _new_ | දුර්වල අවධාන/ඒකාග්‍රතා ක්‍රියාකාරිත්වය |
| `readiness.reasonText.spatial_score.pos` | Strong spatial/pattern recognition | _new_ | ප්‍රබල අවකාශීය හා රටා හඳුනාගැනීම |
| `readiness.reasonText.spatial_score.neg` | Weak spatial/pattern recognition | _new_ | දුර්වල අවකාශීය හා රටා හඳුනාගැනීම |
| `readiness.reasonText.avg_game_score.pos` | Strong cognitive game performance | _new_ | ශක්තිමත් සංජානන ක්‍රීඩා කාර්ය සාධනය |
| `readiness.reasonText.avg_game_score.neg` | Low cognitive game performance | _new_ | අඩු සංජානන ක්‍රීඩා කාර්ය සාධනය |
| `readiness.reasonText.daily_practice_count.pos` | Frequent daily practice | _new_ | නිතර සිදු කරන දෛනික අභ්‍යාස |
| `readiness.reasonText.daily_practice_count.neg` | Low daily practice frequency | _new_ | අඩු දෛනික අභ්‍යාස වාර ගණන |
| `readiness.reasonText.weekly_practice_count.pos` | Good weekly practice volume | _new_ | හොඳ සතිපතා අභ්‍යාස ප්‍රමාණය |
| `readiness.reasonText.weekly_practice_count.neg` | Low weekly practice volume | _new_ | අඩු සතිපතා අභ්‍යාස ප්‍රමාණය |
| `readiness.reasonText.practice_streak.pos` | Strong practice streak/consistency | _new_ | ශක්තිමත් අභ්‍යාස දින අඛණ්ඩතාව |
| `readiness.reasonText.practice_streak.neg` | Inconsistent practice - streak is low | _new_ | අක්‍රමවත් පුහුණුව - දින අඛණ්ඩතාව අඩුය |
| `readiness.reasonText.study_hours.pos` | Healthy daily study hours | _new_ | ප්‍රශස්ත දෛනික අධ්‍යයන පැය ගණන |
| `readiness.reasonText.study_hours.neg` | Low daily study hours | _new_ | අඩු දෛනික අධ්‍යයන පැය ගණන |
| `readiness.reasonText.avg_response_time_sec.pos` | Fast, confident response times | _new_ | වේගවත්, විශ්වාසදායක පිළිතුරු ලබා දීමේ කාලය |
| `readiness.reasonText.avg_response_time_sec.neg` | Slow response times suggest hesitation | _new_ | මඳක් ප්‍රමාද වී පිළිතුරු දීම පැකිලීමක් පෙන්නුම් කරයි |
| `readiness.reasonText.wrong_answer_percent.pos` | Low wrong-answer rate | _new_ | අඩු වැරදි පිළිතුරු ප්‍රතිශතය |
| `readiness.reasonText.wrong_answer_percent.neg` | High wrong-answer rate | _new_ | ඉහළ වැරදි පිළිතුරු ප්‍රතිශතය |
| `readiness.reasonText.avg_difficulty_solved.pos` | Solving high-difficulty questions correctly | _new_ | ඉහළ අපහසුතා සහිත ප්‍රශ්න නිවැරදිව විසඳීම |
| `readiness.reasonText.avg_difficulty_solved.neg` | Struggling with higher-difficulty questions | _new_ | වැඩි දුෂ්කරතා මට්ටම් සහිත ප්‍රශ්න විසඳීමේදී අපහසුතා ඇති වේ |
| `readiness.reasonText.improvement_trend.pos` | Clear improvement trend over time | _new_ | කාලයත් සමඟ පැහැදිලි ප්‍රගති ප්‍රවණතාවක් පෙන්නුම් කරයි |
| `readiness.reasonText.improvement_trend.neg` | Little or negative improvement trend | _new_ | කුඩා හෝ ඍණ ප්‍රගති ප්‍රවණතාවක් ඇත |
| `readiness.reasonText.consistency_score.pos` | Consistent performance across sessions | _new_ | සැසි පුරා ස්ථාවර කාර්යසාධනයක් |
| `readiness.reasonText.consistency_score.neg` | Inconsistent performance across sessions | _new_ | සැසි පුරා අස්ථාවර කාර්යසාධනයක් |
| `readiness.reasonText.attendance_percent.pos` | Good attendance/engagement | _new_ | හොඳ සහභාගීත්වයක් හෝ නියැලීමක් |
| `readiness.reasonText.attendance_percent.neg` | Low attendance/engagement | _new_ | අඩු සහභාගීත්වයක් හෝ නියැලීමක් |
| `readiness.reasonText.days_until_exam.pos` | Comfortable amount of time before the exam | _new_ | විභාගයට පෙර ප්‍රමාණවත් කාලයක් ඇත |
| `readiness.reasonText.days_until_exam.neg` | Limited time remaining before the exam | _new_ | විභාගයට පෙර ඉතිරිව ඇත්තේ සීමිත කාලයකි |
| `readiness.reasonText.motivation_score.pos` | High self-reported motivation | _new_ | ඉහළ ස්වයං-වාර්තාගත අභිප්‍රේරණයක් |
| `readiness.reasonText.motivation_score.neg` | Low self-reported motivation | _new_ | අඩු ස්වයං වාර්තාගත අභිප්‍රේරණය |
| `readiness.reasonText.ai_coach_usage_count.pos` | Actively using the AI coach for support | _new_ | AI උපදේශකවරයා සහයෝගය සඳහා සක්‍රීයව භාවිත කරයි |
| `readiness.reasonText.ai_coach_usage_count.neg` | Rarely using the AI coach | _new_ | AI උපදේශකවරයා කලාතුරකින් භාවිත කරයි |
| `readiness.reasonText.question_completion_rate.pos` | High session-completion rate | _new_ | ඉහළ සැසි-සම්පූර්ණ කිරීමේ අනුපාතයක් |
| `readiness.reasonText.question_completion_rate.neg` | Frequently leaving sessions/tests incomplete | _new_ | නිතර සැසි හෝ පරීක්ෂණ අසම්පූර්ණව හැර යාම |
| `readiness.reasonText.rolling_avg_score.pos` | Recent scores trending high | _new_ | මෑත කාලීන ලකුණු ඉහළ යමින් පවතී |
| `readiness.reasonText.rolling_avg_score.neg` | Recent scores trending low | _new_ | මෑත කාලීන ලකුණු පහත වැටෙමින් පවතී |
| `readiness.reasonText.weekly_trend.pos` | Scores improving week over week | _new_ | සතියෙන් සතියට ලකුණු වැඩිදියුණු වෙමින් පවතී |
| `readiness.reasonText.weekly_trend.neg` | Scores declining week over week | _new_ | සතියෙන් සතියට ලකුණු අඩු වෙමින් පවතී |
| `readiness.reasonText.monthly_trend.pos` | Scores improving over the past month | _new_ | ගිය මාසය පුරා ලකුණු වැඩිදියුණු වෙමින් පවතී |
| `readiness.reasonText.monthly_trend.neg` | Scores declining over the past month | _new_ | ගිය මාසය පුරා ලකුණු අඩුවෙමින් පවතී |
| `readiness.reasonText.learning_velocity.pos` | Ability increasing at a healthy pace | _new_ | හැකියාව යෝග්‍ය වේගයකින් වර්ධනය වේ |
| `readiness.reasonText.learning_velocity.neg` | Ability growth has stalled | _new_ | හැකියාවේ වර්ධනය ඇණහිට ඇත |
| `readiness.reasonText.knowledge_gain_rate.pos` | Strong score gains per practice session | _new_ | සෑම අභ්‍යාස සැසියක් තුළම ප්‍රබල ලකුණු වර්ධනයක් පෙන්නුම් කරයි |
| `readiness.reasonText.knowledge_gain_rate.neg` | Little score gain per practice session | _new_ | සෑම අභ්‍යාස සැසියක් තුළම ලකුණු වර්ධනය අවම වේ |
| `readiness.reasonText.consistency_index.pos` | Low variability across sessions | _new_ | සැසි අතර අඩු විචල්‍යතාවක් ඇත |
| `readiness.reasonText.consistency_index.neg` | High variability across sessions | _new_ | සැසි අතර විචල්‍යතාව ඉහළ මට්ටමක පවතී |
| `readiness.reasonText.fatigue_score.pos` | Little within-session fatigue | _new_ | සැසියක් තුළ ඇති වන විඩාව අවම වේ |
| `readiness.reasonText.fatigue_score.neg` | Accuracy drops noticeably within sessions (fatigue) | _new_ | සැසි අතරතුර නිවැරදි භාවය කැපී පෙනෙන ලෙස පහත වැටේ (විඩාව) |
| `readiness.reasonText.retention_score.pos` | Strong retention of previously learned material | _new_ | මීට පෙර ඉගෙනගත් ද්‍රව්‍ය හොඳින් මතකයේ රඳවාගෙන ඇත |
| `readiness.reasonText.retention_score.neg` | Weak retention of previously learned material | _new_ | මීට පෙර ඉගෙනගත් ද්‍රව්‍ය මතකයේ රඳවාගැනීම දුර්වල වේ |
| `readiness.reasonText.engagement_score.pos` | High overall engagement | _new_ | සමස්ත සහභාගීත්වය ඉහළ මට්ටමක පවතී |
| `readiness.reasonText.engagement_score.neg` | Low overall engagement | _new_ | සමස්ත සහභාගීත්වය අඩු මට්ටමක පවතී |
| `readiness.reasonText.practice_intensity.pos` | Practice volume above the recommended target | _new_ | අභ්‍යාස ප්‍රමාණය නිර්දේශිත ඉලක්කයට වඩා වැඩි වේ |
| `readiness.reasonText.practice_intensity.neg` | Practice volume below the recommended target | _new_ | අභ්‍යාස ප්‍රමාණය නිර්දේශිත ඉලක්කයට වඩා අඩු වේ |
| `readiness.reasonText.error_recovery_rate.pos` | Good bounce-back after a wrong answer | _new_ | වැරදි පිළිතුරකින් පසු ඉක්මනින් යථා තත්ත්වයට පත්වීම හොඳ මට්ටමක පවතී |
| `readiness.reasonText.error_recovery_rate.neg` | Wrong answers tend to cascade into more mistakes | _new_ | වැරදි පිළිතුරු හේතුවෙන් තවත් වැරදි සිදු වීමට ඇති ඉඩකඩ වැඩි වේ |
| `readiness.reasonText.category_mastery.pos` | Strong mastery within practiced categories | _new_ | අභ්‍යාස කළ ප්‍රවර්ග තුළ ප්‍රවීණතාවය ප්‍රබල වේ |
| `readiness.reasonText.category_mastery.neg` | Limited mastery within practiced categories | _new_ | පුහුණු කළ ප්‍රවර්ග තුළ සීමිත ප්‍රවීණතාවක් ඇත |
| `readiness.reasonText.confidence_trend.pos` | Growing confidence (faster, more accurate) | _new_ | වැඩෙන ආත්ම විශ්වාසය (වේගවත් සහ වඩාත් නිවැරදි) |
| `readiness.reasonText.confidence_trend.neg` | Confidence not improving | _new_ | ආත්ම විශ්වාසය වර්ධනය වීමක් සිදු නොවේ |
| `readiness.reasonText.reaction_speed_trend.pos` | Getting faster over time | _new_ | කාලයත් සමඟ වේගය වැඩි වෙමින් පවතී |
| `readiness.reasonText.reaction_speed_trend.neg` | Not getting any faster over time | _new_ | කාලයත් සමඟ කිසිදු වේගවත් වීමක් සිදු නොවේ |
| `readiness.reasonText.adaptive_learning_gain.pos` | Daily sessions are driving real ability gains | _new_ | දෛනික සැසි සැබෑ හැකියාවන් වර්ධනය කරයි |
| `readiness.reasonText.adaptive_learning_gain.neg` | Daily sessions show little ability gain | _new_ | දෛනික සැසි මඟින් හැකියාවන්හි අවම වර්ධනයක් පෙන්වයි |
| `readiness.reasonText.difficulty_progression.pos` | Successfully tackling harder items over time | _new_ | කාලයත් සමඟ අපහසු අයිතම සාර්ථකව ජය ගනිමින් සිටී |
| `readiness.reasonText.difficulty_progression.neg` | Not progressing to harder items | _new_ | වඩාත් අපහසු අයිතම වෙත ප්‍රගතියක් ලබමින් නොමැත |
| `readiness.reasonText.question_diversity_score.pos` | Practicing a wide range of subtopics | _new_ | පුළුල් පරාසයක පැතිරුණු උප මාතෘකා පුහුණු වෙමින් පවතී |
| `readiness.reasonText.question_diversity_score.neg` | Practice is narrowly focused on few subtopics | _new_ | පුහුණුව සීමිත උප මාතෘකා කිහිපයක් වෙත පමණක් යොමු වී ඇත |
| `readiness.reasonText.time_management_score.pos` | Good session time management | _new_ | සැසි කාලය හොඳින් කළමනාකරණය කර ඇත |
| `readiness.reasonText.time_management_score.neg` | Sessions often over/under target duration | _new_ | සැසි කාලය බොහෝ විට ඉලක්කගත කාලයට වඩා වැඩි හෝ අඩු වේ |
| `readiness.reasonText.revision_frequency.pos` | Regularly revisiting earlier material | _new_ | පෙර අන්තර්ගතයන් නිතිපතා පුනරීක්ෂණය කරනු ලැබේ |
| `readiness.reasonText.revision_frequency.neg` | Rarely revisiting earlier material | _new_ | පෙර අන්තර්ගතයන් පුනරීක්ෂණය කරනු ලබන්නේ කලාතුරකිනි |
| `readiness.featureLabel.placement_iq` | placement iq | _new_ | මට්ටම් නිර්ණය IQ |
| `readiness.featureLabel.current_iq` | current iq | _new_ | වත්මන් IQ |
| `readiness.featureLabel.theta` | theta | _new_ | තීටා (theta) |
| `readiness.featureLabel.avg_test_score` | average test score | _new_ | සාමාන්‍ය පරීක්ෂණ ලකුණු |
| `readiness.featureLabel.memory_score` | memory score | _new_ | මතක ලකුණු |
| `readiness.featureLabel.logical_score` | logical reasoning score | _new_ | තාර්කික චින්තන ලකුණු |
| `readiness.featureLabel.numerical_score` | numerical reasoning score | _new_ | සංඛ්‍යාත්මක හැකියාවන් ලකුණු |
| `readiness.featureLabel.attention_score` | attention score | _new_ | අවධාන ලකුණු |
| `readiness.featureLabel.spatial_score` | spatial reasoning score | _new_ | අවකාශීය හා රටා හඳුනාගැනීමේ ලකුණු |
| `readiness.featureLabel.avg_game_score` | avg game score | _new_ | සාමාන්‍ය ක්‍රීඩා ලකුණු |
| `readiness.featureLabel.daily_practice_count` | daily practice count | _new_ | දෛනික අභ්‍යාස ප්‍රමාණය |
| `readiness.featureLabel.weekly_practice_count` | weekly practice volume | _new_ | සතිපතා අභ්‍යාස පරිමාව |
| `readiness.featureLabel.practice_streak` | practice streak | _new_ | අභ්‍යාස අඛණ්ඩතාව |
| `readiness.featureLabel.study_hours` | daily study hours | _new_ | දෛනික අධ්‍යයන පැය ගණන |
| `readiness.featureLabel.avg_response_time_sec` | avg response time sec | _new_ | සාමාන්‍ය ප්‍රතිචාර කාලය තත්පර වලින් |
| `readiness.featureLabel.wrong_answer_percent` | wrong answer percent | _new_ | වැරදි පිළිතුරු ප්‍රතිශතය |
| `readiness.featureLabel.avg_difficulty_solved` | avg difficulty solved | _new_ | විසඳන ලද සාමාන්‍ය අපහසුතා මට්ටම |
| `readiness.featureLabel.improvement_trend` | improvement trend | _new_ | දියුණුවේ ප්‍රවණතාව |
| `readiness.featureLabel.consistency_score` | study consistency | _new_ | අධ්‍යයන අඛණ්ඩතාව |
| `readiness.featureLabel.attendance_percent` | attendance percent | _new_ | පැමිණීමේ ප්‍රතිශතය |
| `readiness.featureLabel.days_until_exam` | days until exam | _new_ | විභාගයට ඉතිරි දින ගණන |
| `readiness.featureLabel.motivation_score` | motivation score | _new_ | අභිප්‍රේරණ ලකුණු |
| `readiness.featureLabel.ai_coach_usage_count` | ai coach usage count | _new_ | AI උපදේශක භාවිත වාර ගණන |
| `readiness.featureLabel.question_completion_rate` | question completion rate | _new_ | ප්‍රශ්න අවසන් කිරීමේ අනුපාතය |
| `readiness.featureLabel.rolling_avg_score` | rolling avg score | _new_ | මෑත කාලීන සාමාන්‍ය ලකුණු |
| `readiness.featureLabel.weekly_trend` | weekly trend | _new_ | සතිපතා ප්‍රවණතාව |
| `readiness.featureLabel.monthly_trend` | monthly trend | _new_ | මාසික ප්‍රවණතාව |
| `readiness.featureLabel.learning_velocity` | learning speed | _new_ | ඉගෙනුම් වේගය |
| `readiness.featureLabel.knowledge_gain_rate` | knowledge gain rate | _new_ | දැනුම වර්ධනය වීමේ වේගය |
| `readiness.featureLabel.consistency_index` | study consistency | _new_ | අධ්‍යයන අඛණ්ඩතා දර්ශකය |
| `readiness.featureLabel.fatigue_score` | session fatigue | _new_ | සැසි විඩාව |
| `readiness.featureLabel.retention_score` | knowledge retention | _new_ | මතක තබා ගැනීමේ ලකුණු |
| `readiness.featureLabel.engagement_score` | overall engagement | _new_ | සමස්ත සහභාගීත්ව ලකුණු |
| `readiness.featureLabel.practice_intensity` | practice intensity | _new_ | අභ්‍යාස තීව්‍රතාව |
| `readiness.featureLabel.error_recovery_rate` | error recovery rate | _new_ | දෝෂ නිවැරදි කර ගැනීමේ අනුපාතය |
| `readiness.featureLabel.category_mastery` | category mastery | _new_ | ප්‍රවර්ග ප්‍රවීණතාව |
| `readiness.featureLabel.confidence_trend` | confidence trend | _new_ | ආත්ම විශ්වාස ප්‍රවණතාව |
| `readiness.featureLabel.reaction_speed_trend` | reaction speed trend | _new_ | ප්‍රතිචාර වේග ප්‍රවණතාව |
| `readiness.featureLabel.adaptive_learning_gain` | adaptive learning gain | _new_ | අනුවර්තී ඉගෙනුම් ලාභය |
| `readiness.featureLabel.difficulty_progression` | difficulty progression | _new_ | දුෂ්කරතා ප්‍රගතිය |
| `readiness.featureLabel.question_diversity_score` | question diversity score | _new_ | ප්‍රශ්න විවිධත්ව ලකුණු |
| `readiness.featureLabel.time_management_score` | time management score | _new_ | කාල කළමනාකරණ ලකුණු |
| `readiness.featureLabel.revision_frequency` | revision frequency | _new_ | පුනරාවර්තන වාර ගණන |
| `readiness.explain.dropped` | your {{label}} dropped by {{pct}}% | _new_ | ඔබේ {{label}} ප්‍රතිශතය {{pct}}%කින් පහත වැටීම |
| `readiness.explain.increased` | your {{label}} increased by {{pct}}% | _new_ | ඔබේ {{label}} ප්‍රමාණය {{pct}}% කින් වැඩි වීම |
| `readiness.explain.strength` | your {{label}} was a strength | _new_ | ඔබේ {{label}} ශක්තියක් වීම |
| `readiness.explain.weak` | your {{label}} was a weak point | _new_ | ඔබේ {{label}} දුර්වල අංශයක් වීම |
| `readiness.explain.one` | Your readiness estimate reflects that {{clause}}. | _new_ | ඔබේ විභාග සූදානම් වීමේ ඇස්තමේන්තුව මඟින් පෙන්නුම් කරන්නේ {{clause}} බවයි. |
| `readiness.explain.many` | Your readiness estimate changed because {{clauses}}, and {{last}}. | _new_ | {{clauses}} සහ {{last}} හේතුවෙන් ඔබේ විභාග සූදානම් වීමේ ඇස්තමේන්තුව වෙනස් වී ඇත. |
| `readiness.explain.none` | Not enough data yet to explain this prediction's biggest drivers. | _new_ | මෙම පුරෝකථනයේ ප්‍රධාන සාධක පැහැදිලි කිරීමට තවමත් ප්‍රමාණවත් දත්ත නොමැත. |
| `examProfile.noProfileYet` | Set up your exam profile to get a countdown and a personalized study plan. | ගණන් කිරීමක් සහ පුද්ගලික අධ්‍යයන සැලැස්මක් ලබා ගැනීමට ඔබේ විභාග පැතිකඩ සකසන්න. | කාල ගණනය කිරීමක් සහ පුද්ගලීකරණය කළ අධ්‍යයන සැලැස්මක් ලබා ගැනීමට ඔබේ විභාග පැතිකඩ සකසන්න. |
| `examProfile.outcome.subtitle` | This exam's date has passed. Let us know what happened - it helps us understand your real results. | මෙම විභාගයේ දිනය පසුවී ඇත. සිදුවූයේ කුමක්දැයි අපට දන්වන්න - එය ඔබේ සැබෑ ප්‍රතිඵල තේරුම් ගැනීමට උපකාරී වේ. | මෙම විභාගයේ දිනය පසු වී ඇත. සිදු වූයේ කුමක්දැයි අපට දන්වන්න. එය ඔබේ සැබෑ ප්‍රතිඵල තේරුම් ගැනීමට අපට උපකාරී වේ. |
| `examProfile.outcome.attendedQuestion` | Did you attend the exam? | ඔබ විභාගයට පැමිණියේද? | ඔබ විභාගයට පෙනී සිටියේද? |
| `examProfile.outcome.passedQuestion` | Did you pass? | ඔබ සමත් වුණාද? | ඔබ සමත් වූයේද? |
| `examProfile.outcome.scoreLabel` | Your score (optional) | ඔබේ ලකුණු (විකල්පයි) | ඔබේ ලකුණු (විකල්ප) |
| `examProfile.outcome.submit` | Save and continue | සුරකින්න | සුරකින්න සහ ඉදිරියට යන්න |
| `examProfile.outcome.didNotAttend` | Did not attend | පැමිණියේ නැත | පෙනී සිටියේ නැත |
| `examProfile.outcome.attended` | Attended | පැමිණියා | පෙනී සිටියේය |
| `examProfile.edit` | Edit exam profile | විභාග පැතිකඩ සංස්කරණය | විභාග පැතිකඩ සංස්කරණය කරන්න |
| `examProfile.backToDashboard` | Back to dashboard | උපකරණ පුවරුවට ආපසු | උපකරණ පුවරුව වෙත ආපසු යන්න |
| `examProfile.saved` | Exam profile saved. | විභාග පැතිකඩ සුරකින ලදී. | විභාග පැතිකඩ සුරැක ඇත. |
| `examProfile.examName` | What exam are you preparing for? | විභාග නම | ඔබ සූදානම් වන්නේ කුමන විභාගය සඳහාද? |
| `examProfile.targetScore` | Marks you need out of 100 | 100න් අවශ්‍ය ලකුණු | ඔබට 100න් ලබා ගැනීමට අවශ්‍ය ලකුණු |
| `examProfile.dailyHours` | How long can you use the system each day? | දිනකට පැය කීයක්ද? | ඔබට දිනකට පද්ධතිය භාවිත කළ හැක්කේ පැය කීයක්ද? |
| `examProfile.dailyHoursHint` | Asked once here - we won't ask again during check-ins or predictions. | මෙය එක් වරක් පමණි. | මෙය මෙහිදී එක් වරක් පමණක් අසනු ලැබේ - දෛනික සටහන් හෝ පුරෝකථන වලදී අපි එය නැවත අසන්නේ නැත. |
| `examProfile.realExamDetailsTitle` | Real exam details (optional) | විභාගය (විකල්ප) | සැබෑ විභාග විස්තර (විකල්ප) |
| `examProfile.realExamDetailsHint` | Skip this if you're not preparing for a specific exam. Used to show your pace against the real exam and to build mock exams. | මෙය අවශ්‍ය නොවේ. | ඔබ නිශ්චිත විභාගයක් සඳහා සූදානම් නොවන්නේ නම් මෙය මඟ හරින්න. සැබෑ විභාගයට සාපේක්ෂව ඔබේ වේගය පෙන්වීමට සහ ආදර්ශ විභාග සකස් කිරීමට මෙය භාවිත කෙරේ. |
| `examProfile.passMark` | Pass mark (out of 100) | සමත් ලකුණු | සමත් වන ලකුණු සංඛ්‍යාව (100කට) |
| `examProfile.negativeMarking` | Negative marking | වැරදි පිළිතුරු සඳහා ලකුණු | ඍණ ලකුණු |
| `examProfile.daysLeft` | days left | දින ඉතිරිව ඇත | ඉතිරි දින |
| `examProfile.countdown` | {{days}}d {{hours}}h {{minutes}}m remaining | දින {{days}} පැය {{hours}} මිනිත්තු {{minutes}} ඉතිරිව ඇත | දින {{days}}ක්, පැය {{hours}}ක් සහ මිනිත්තු {{minutes}}ක් ඉතිරිව ඇත |
| `examProfile.todaysGoal` | Today's goal: {{count}} practice questions | අද දින ඉලක්කය: ප්‍රශ්න {{count}}ක් | අද දින ඉලක්කය: පුහුණු විය යුතු ප්‍රශ්න {{count}}ක් |
| `examProfile.activity.timed_mock_practice` | Timed mock practice | කාල නියමිත මාදිලි අභ්‍යාසය | කාල නියමිත ආදර්ශ විභාග අභ්‍යාසය |
| `studyNotes.eyebrow` | Self-learning library | න්‍යාය සටහන් | ස්වයං අධ්‍යයන පුස්තකාලය |
| `studyNotes.allCategories` | All categories | සියල්ල | සියලුම ප්‍රවර්ග |
| `studyNotes.recommendation.title` | You've been struggling here ({{accuracy}}% accuracy). Learn it now. | ඔබ මෙහි දුර්වලයි (නිවැරදිතාවය {{accuracy}}%) - දැන් ඉගෙන ගන්න | ඔබ මෙහි අරගල කරමින් සිටී (නිවැරදිතාවය {{accuracy}}%). දැන් එය ඉගෙන ගන්න. |
| `studyNotes.testYourself` | Test yourself | ස්වයං පරීක්ෂණය | ඔබම පරීක්ෂා කරන්න |
| `studyNotes.review.again` | Forgot | අමතකයි | අමතක විය |

## games.json  (11)

| Key | English | Before | After |
|---|---|---|---|
| `hub.subtitle` | Quick brain-training games that feed your dashboard streak. | ඔබේ පුවරුවේ අඛණ්ඩතාවට එකතු වන කෙටි මොළ පුහුණු ක්‍රීඩා. | ඔබේ උපකරණ පුවරුවේ අඛණ්ඩතාව පෝෂණය කරන කෙටි මොළ පුහුණු ක්‍රීඩා. |
| `result.best` | Best: {{score}} pts | වාර්තාව: ලකුණු {{score}} | හොඳම ලකුණු: {{score}} |
| `result.backToGames` | Back to games | ක්‍රීඩා වෙතට ආපසු | ක්‍රීඩා වෙත ආපසු යන්න |
| `common.backToGames` | Back to games | ක්‍රීඩා වෙතට ආපසු | ක්‍රීඩා වෙත ආපසු යන්න |
| `memoryMatch.matched` | Matched: {{matched}} / {{total}} | ගැලපුණු: {{matched}} / {{total}} | ගැලපුණු ප්‍රමාණය: {{matched}} / {{total}} |
| `sequencePuzzle.correct` | Correct: {{count}} | නිවැරදි: {{count}} | නිවැරදි පිළිතුරු: {{count}} |
| `selectiveAttention.title` | Selective Attention Challenge | තෝරාගත් අවධාන අභියෝගය | වරණීය අවධාන අභියෝගය |
| `workingMemorySpan.task.forward` | Forward digit span | ඉදිරි ඉලක්කම් මතකය | ඉදිරි ඉලක්කම් මතක පරාසය |
| `workingMemorySpan.task.backward` | Backward digit span | ප්‍රතිලෝම ඉලක්කම් මතකය | ප්‍රතිලෝම ඉලක්කම් මතක පරාසය |
| `visualSpatialMemory.instructions` | Memorize positions and icons, then answer what you saw. | ස්ථාන සහ සංකේත මතක තබාගන්න. | ස්ථාන සහ සංකේත මතක තබාගන්න, ඉන්පසු ඔබ දුටු දේ පිළිතුරු දෙන්න. |
| `cognitiveCommandCenter.title` | Cognitive Command Center | බුද්ධිමය විධාන මධ්‍යස්ථානය | සංජානන විධාන මධ්‍යස්ථානය |

## gamification.json  (19)

| Key | English | Before | After |
|---|---|---|---|
| `reward.earned` | +{{xp}} XP, +{{coins}} coins earned! | XP {{xp}}+, කාසි {{coins}}+ උපයා ගන්නා ලදී! | +{{xp}} XP, +{{coins}} කාසි උපයා ඇත! |
| `reward.badgeUnlocked` | Badge unlocked: {{name}} | බැඩ්ජ් අගුළු ඇරිණි: {{name}} | පදක්කම අගුළු හරින ලදී: {{name}} |
| `widget.badgesEarned` | {{earned}}/{{total}} badges | බැඩ්ජ් {{earned}}/{{total}} | පදක්කම් {{earned}}/{{total}} |
| `widget.levelTitle.1` | Novice | _new_ | ආධුනික |
| `widget.levelTitle.2` | Learner | _new_ | අධ්‍යාපනලාභී |
| `widget.levelTitle.3` | Achiever | _new_ | සාර්ථකත්වයට පත්වන්නා |
| `widget.levelTitle.4` | Scholar | _new_ | විද්වත් |
| `widget.levelTitle.5` | Expert | _new_ | විශේෂඥ |
| `widget.levelTitle.6` | Specialist | _new_ | නිපුණ |
| `widget.levelTitle.7` | Master | _new_ | ප්‍රවීණ |
| `widget.levelTitle.8` | Grandmaster | _new_ | මහා ප්‍රවීණ |
| `widget.levelTitle.9` | Champion | _new_ | ශූරයා |
| `widget.levelTitle.10` | Legend | _new_ | අමරණීය |
| `badges.title` | Badges | බැඩ්ජ් | පදක්කම් |
| `badges.subtitle` | Earn badges as you train, play games, and prepare for your exam. | ඔබ පුහුණු වන විට, ක්‍රීඩා කරන විට සහ විභාගය සඳහා සූදානම් වන විට බැඩ්ජ් උපයා ගන්න. | ඔබ පුහුණුවීම් කරන විට, ක්‍රීඩා කරන විට සහ විභාගය සඳහා සූදානම් වන විට පදක්කම් උපයා ගන්න. |
| `missions.codes.weekly_average` | Average 80%+ this week | මෙම සතියේ සාමාන්‍යය 80%+ තබා ගන්න | මෙම සතියේ සාමාන්‍යය 80%+ ලෙස පවත්වා ගන්න |
| `leaderboard.title` | Leaderboard | නායකත්ව පුවරුව | ශ්‍රේණි පුවරුව |
| `leaderboard.rank` | Rank | ශ්‍රේණිය | ස්ථානය |
| `leaderboard.yourRank` | Your rank: #{{rank}} | ඔබේ ශ්‍රේණිය: #{{rank}} | ඔබේ ස්ථානය: #{{rank}} |

## profile.json  (7)

| Key | English | Before | After |
|---|---|---|---|
| `feedback.questionQuality` | Question quality | ප්‍රශ්න ගුණාත්මකභාවය | ප්‍රශ්නවල ගුණාත්මකභාවය |
| `feedback.sinhalaQuality` | Sinhala quality | සිංහල ගුණාත්මකභාවය | සිංහල භාෂාවේ ගුණාත්මකභාවය |
| `feedback.comment` | Comments (optional) | අදහස් (විකල්පයි) | අදහස් (විකල්ප) |
| `feedback.suggestion` | Feature suggestion (optional) | විශේෂාංග යෝජනාවක් (විකල්පයි) | විශේෂාංග යෝජනා (විකල්ප) |
| `feedback.submit` | Submit feedback | ප්‍රතිපෝෂණය එවන්න | ප්‍රතිපෝෂණය ඉදිරිපත් කරන්න |
| `feedback.submitting` | Submitting... | එවමින්... | ඉදිරිපත් කරමින් පවතී... |
| `feedback.submitFailed` | Could not submit feedback. Please try again. | ප්‍රතිපෝෂණය එවිය නොහැකි විය. නැවත උත්සාහ කරන්න. | ප්‍රතිපෝෂණය ඉදිරිපත් කළ නොහැකි විය. කරුණාකර නැවත උත්සාහ කරන්න. |

## sessions.json  (27)

| Key | English | Before | After |
|---|---|---|---|
| `types.placement` | Placement Test | ස්ථානගත කිරීමේ පරීක්ෂණය | මට්ටම් නිර්ණය පරීක්ෂණය |
| `types.mock` | Mock Exam | මාදිලි විභාගය | ආදර්ශ විභාගය |
| `keyboardHint` | Press 1-6 to answer, Enter to continue | පිළිතුරු දීමට 1-6, ඉදිරියට යාමට Enter ඔබන්න | පිළිතුරු දීමට 1-6 ඔබන්න, ඉදිරියට යාමට Enter ඔබන්න |
| `mockExam.title` | Mock Exam | මාදිලි විභාගය | ආදර්ශ විභාගය |
| `mockExam.subtitle` | Build a personalized, timed practice exam that focuses more on your weak areas. | පුද්ගලික, කාල නියමිත විභාගයක් සකසන්න. | ඔබේ දුර්වල අංශ කෙරෙහි වැඩි අවධානයක් යොමු කරමින්, පුද්ගලීකරණය කළ, කාල නියමිත ආදර්ශ විභාගයක් සකසන්න. |
| `mockExam.setupTitle` | Exam settings | විභාගය | විභාග සැකසුම් |
| `mockExam.scopeFull` | Full syllabus | සම්පූර්ණ ආවරණය | සම්පූර්ණ විෂය නිර්දේශය |
| `mockExam.difficultyMode` | Difficulty | මට්ටම | දුෂ්කරතා මට්ටම |
| `mockExam.difficultyStandard` | Standard (your current level) | වත්මන් මට්ටම | සම්මත (ඔබගේ වත්මන් මට්ටම) |
| `mockExam.difficultyAdaptive` | Adaptive (harder in your strong areas) | අනුවර්තී මට්ටම | අනුවර්තී (ඔබේ ශක්තිමත් අංශවල ප්‍රශ්න ක්‍රමයෙන් අපහසු වේ) |
| `mockExam.start` | Start mock exam | ආරම්භ කරන්න | ආදර්ශ විභාගය ආරම්භ කරන්න |
| `mockExam.startError` | Could not start a mock exam with these settings - try a smaller question count. | මාදිලි විභාගය ආරම්භ කළ නොහැකි විය. | මෙම සැකසුම් සමඟ ආදර්ශ විභාගයක් ආරම්භ කළ නොහැකි විය - කුඩා ප්‍රශ්න සංඛ්‍යාවක් උත්සාහ කරන්න. |
| `placement.title` | Let's measure your starting level | අපි ඔබේ ආරම්භක මට්ටම මනිමු | ඔබේ ආරම්භක මට්ටම මනිමු |
| `placement.start` | Start placement test | ස්ථානගත කිරීමේ පරීක්ෂණය ආරම්භ කරන්න | මට්ටම් නිර්ණය පරීක්ෂණය ආරම්භ කරන්න |
| `placement.startError` | Could not start the placement test. Please try again shortly. | ස්ථානගත කිරීමේ පරීක්ෂණය ආරම්භ කළ නොහැකි විය. කරුණාකර ටික වේලාවකින් නැවත උත්සාහ කරන්න. | මට්ටම් නිර්ණය පරීක්ෂණය ආරම්භ කළ නොහැකි විය. කරුණාකර කෙටි වේලාවකින් නැවත උත්සාහ කරන්න. |
| `placement.backToDashboard` | Back to dashboard | පුවරුවට ආපසු | උපකරණ පුවරුව වෙත ආපසු |
| `placement.progress` | Question {{current}} (adaptive, up to {{max}}) | ප්‍රශ්නය {{current}} (අනුවර්තී, උපරිම {{max}}) | ප්‍රශ්නය {{current}} (අනුවර්තී, උපරිම {{max}} දක්වා) |
| `daily.backToDashboard` | Back to dashboard | පුවරුවට ආපසු | උපකරණ පුවරුව වෙත ආපසු |
| `practice.subtitle` | Pick a category to drill on its own, at your current level. | ඔබේ වත්මන් මට්ටමේදී තනි ප්‍රවර්ගයක් තෝරාගෙන පුහුණු වන්න. | ඔබේ වත්මන් මට්ටමින්, තනි ප්‍රවර්ගයක් තෝරාගෙන අභ්‍යාසයේ යෙදෙන්න. |
| `practice.dailyHint` | Your adaptive daily set, weighted toward your weak areas. | ඔබේ දුර්වල ප්‍රදේශ කෙරෙහි නැඹුරු වූ අනුවර්තී දෛනික ප්‍රශ්න කට්ටලය. | ඔබේ දුර්වල අංශ කෙරෙහි නැඹුරු වූ අනුවර්තී දෛනික ප්‍රශ්න කට්ටලය. |
| `practice.weakArea` | Weak-area practice | දුර්වල ප්‍රදේශ පුහුණුව | දුර්වල අංශ පුහුණුව |
| `practice.weakAreaUnavailable` | Available once you have a practice history. | පුහුණු ඉතිහාසයක් ඇති පසු ලබා ගත හැක. | පුහුණු ඉතිහාසයක් ඇති විට ලබා ගත හැක. |
| `report.title` | Session complete | සැසිය සම්පූර්ණයි | සැසිය සම්පූර්ණ කර ඇත |
| `report.levelUpdated` | Level updated | මට්ටම යාවත්කාලීන විය | මට්ටම යාවත්කාලීන කර ඇත |
| `report.backToDashboard` | Back to dashboard | පුවරුවට ආපසු | උපකරණ පුවරුව වෙත ආපසු |
| `report.correctAnswer` | Correct: {{value}} | නිවැරදි: {{value}} | නිවැරදි පිළිතුර: {{value}} |
| `report.explainMore` | Get a deeper explanation | වැඩිදුර පැහැදිලි කිරීමක් ලබා ගන්න | වඩා ගැඹුරු පැහැදිලි කිරීමක් ලබා ගන්න |

## studyPlan.json  (16)

| Key | English | Before | After |
|---|---|---|---|
| `recommendedWeeklyMockTests` | Weekly mock tests | සතිපතා මාදිලි විභාග | සතිපතා ආදර්ශ විභාග |
| `weeksRemaining` | Weeks remaining | ඉතිරි සති ගණන | ඉතිරි සති |
| `prepDayOfTotal` | Day {{day}} of your {{total}}-day plan | සැලැස්මේ දිනය {{day}} / {{total}} | ඔබේ දින {{total}}ක සැලැස්මේ {{day}} වන දිනය |
| `streakChip` | {{count}}-day streak | {{count}} දින පුහුණුව | දින {{count}}ක අඛණ්ඩතාව |
| `phaseNames.practice` | Practice | අභ්‍යාසය අවධිය | අභ්‍යාස අවධිය |
| `motivation.foundation` | Practice builds your foundation. | සූදානම පුහුණුව මගින් ආරම්භ වේ. | අභ්‍යාස මඟින් ඔබේ පදනම නිර්මාණය කරයි. |
| `motivation.practice` | Steady practice, day by day. | පුහුණුව ස්ථාවර වේ. | ක්‍රමානුකූලව, දිනෙන් දින අභ්‍යාස කරන්න. |
| `motivation.intensive` | Focus on your weakest areas now. | දැඩි පුහුණුව අවශ්‍ය වේ. | දැන් ඔබගේ දුර්වලම අංශ කෙරෙහි අවධානය යොමු කරන්න. |
| `motivation.final_revision` | Review what you've learned. | අවසාන සමාලෝචනය කරයි. | ඔබ ඉගෙනගත් දේ සමාලෝචනය කරන්න. |
| `motivation.exam_day` | You're ready. Trust your preparation. | විභාග දිනය සූදානම වේ. | ඔබ සූදානම්ය. ඔබේ සූදානම විශ්වාස කරන්න. |
| `focus.mock` | Mock test | මාදිලි විභාගය | ආදර්ශ විභාගය |
| `noExamProfile.mockExamTitle` | Try a full mock exam | සම්පූර්ණ මාදිලි විභාගයක් උත්සාහ කරන්න | සම්පූර්ණ ආදර්ශ විභාගයක් උත්සාහ කරන්න |
| `noExamProfile.setupHint` | Want a full exam countdown and day-by-day plan instead? | ඒ වෙනුවට සම්පූර්ණ විභාග සැලැස්මක් අවශ්‍යද? | ඒ වෙනුවට සම්පූර්ණ විභාග දින ගණන් කිරීමක් සහ දිනපතා සැලැස්මක් අවශ්‍යද? |
| `readinessGap.secondsPerQuestion` | {{count}}s/question | තත්පර {{count}} | තත්පර {{count}}/ප්‍රශ්නයක් |
| `readinessGap.noPredictionYet` | Run a prediction to see your readiness gap. | අනාවැකියක් නැත. | ඔබේ සූදානම් වීමේ පරතරය බැලීමට පුරෝකථනයක් ක්‍රියාත්මක කරන්න. |
| `readinessGap.noExamPaceYet` | Add your exam's question count and duration to see a pace target. | විභාග ප්‍රශ්න ගණන සහ කාලය එක් කරන්න. | වේග ඉලක්කයක් බැලීමට ඔබේ විභාගයේ ප්‍රශ්න සංඛ්‍යාව සහ කාලය එක් කරන්න. |
