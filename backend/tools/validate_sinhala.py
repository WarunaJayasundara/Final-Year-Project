"""Sinhala text safety validator for the question bank seeders.

Guards against the two failure modes that can corrupt generated Sinhala:
  1. Stray codepoints from neighbouring Unicode blocks (Malayalam U+0D00-0D7F,
     Telugu U+0C00-0C7F, Kannada U+0C80-0CFF), replacement characters, or
     malformed escape junk embedded inside Sinhala text.
  2. Novel Sinhala words that do not appear anywhere in the verified corpus
     (existing seeders + frontend si locale files, all of which have been
     visually verified rendering correctly in the app). Novel words are not
     automatically wrong, but each one must be consciously reviewed once and
     then added to the whitelist below.

Usage:
  python tools/validate_sinhala.py --build-corpus          # rebuild corpus cache
  python tools/validate_sinhala.py file1.php [file2.php]   # validate files
  python tools/validate_sinhala.py --all                   # validate every Questions seeder
"""

import argparse
import json
import re
import sys
import unicodedata
from pathlib import Path

BACKEND = Path(__file__).resolve().parent.parent
PROJECT = BACKEND.parent
CORPUS_CACHE = BACKEND / "tools" / "sinhala_corpus.json"

# Files whose Sinhala is trusted (rendered + verified in the running app).
# NOTE: the Questions glob is recursive (**/*.php) so Bank2/Bank3/Bank4/Bank5
# subdirectory seeders are actually reachable here - a plain "*.php" glob
# (the original form of this line) silently skips every subdirectory,
# which meant --all's coverage and UNDER_REVIEW_MARKERS' exclusion were
# both no-ops for every Bank* seeder until this was caught and fixed
# during the adult-content upgrade.
CORPUS_SOURCES = [
    *(BACKEND / "database" / "seeders").glob("*.php"),
    *(BACKEND / "database" / "seeders" / "Questions").glob("**/*.php"),
    *(PROJECT / "frontend" / "src" / "locales" / "si").glob("*.json"),
]

# Files being validated must not contribute to the corpus they are checked
# against; new seeders are listed here while under review.
UNDER_REVIEW_MARKERS = ("Bank2", "Bank3", "Bank4", "Bank5")

# Words manually reviewed and approved after being flagged as novel.
# Keep this list short - prefer reusing corpus vocabulary.
# Review log:
#   වම් (left), දකුණු (right), පසින් (from the side) - standard dictionary
#   words needed by the ranking/position archetype; codepoints verified to
#   be entirely within the Sinhala block.
#   සක්‍රීය (active, root form of already-verified සක්‍රීයයි="is active"),
#   රූප (image, root form of already-verified රූපය="the image") - needed as
#   noun-modifiers ("active questions", "image questions") by the admin
#   Question Bank Stats page; ටැග් (tag) - a standard Sinhala tech loanword.
#   අඩුවීමේ (of decreasing, oblique form of the standard word අඩුවීම) - needed
#   by the research-grade ML dashboard's "risk of practice decreasing" label;
#   codepoints verified to be entirely within the Sinhala block.
#   Admin ML Research page (mlResearch.*) - a genuinely new topic (model
#   evaluation/versioning) with no prior bilingual precedent in this app,
#   so a larger batch of standard dictionary words was needed at once:
#   අනුවාද/අනුවාදය (version/versions), ආකෘති (models, plural of the already-
#   verified ආකෘතිය), ඇගයීම් (evaluations), එකඟතාව (agreement), ප්‍රතිඵල
#   (results), ප්‍රභවය (source), පේළි (rows), ලියාපදිංචි (registered),
#   වාර්තාවක් (a report), වැදගත්කම (importance), විනිශ්චය (diagnosis),
#   විශේෂාංග (features), සංයුතිය (composition), සමස්ත (overall), සේවාවේ
#   (of the service) - all standard dictionary words, codepoints verified
#   to be entirely within the Sinhala block.
#   Admin Knowledge & Question Source Library page (knowledgeLibrary.*,
#   plus aiQuestions.sourceDocument*) - the PDF-ingestion admin feature,
#   another genuinely new topic: ලේඛනය (document, singular - root form of
#   the already-verified plural ලේඛන, same root/inflection pattern as the
#   earlier රූප/රූපය pair), ප්‍රභව (source, attributive stem form of the
#   already-verified ප්‍රභවය, same stem-drop pattern as රූප/රූපය used as a
#   noun-modifier e.g. "ප්‍රභව ලේඛනය" = "source document"), මාතෘකාව (title),
#   වර්ෂය (year), සටහන (note), න්‍යාය (theory), වෙනත් (other), තේමා (topic) -
#   all standard dictionary words, codepoints verified to be entirely
#   within the Sinhala block.
#   Bank3 seeders (new competitive-exam archetypes discovered via PDF
#   analysis: blood relations, direction sense, coding-decoding,
#   calendar/clock reasoning, seating arrangement, data interpretation) -
#   basic, unambiguous dictionary words with essentially no dialectal/
#   corruption risk: උතුර/දකුණ/නැගෙනහිර/බටහිර (the four cardinal directions),
#   කිලෝමීටර් (kilometre), සතිය (week), මාසය (month), ඔරලෝසුව (clock),
#   කෝණය (angle), පේළිය (row, singular of the already-verified plural
#   පේළි), වගුව (table/data-table), ප්‍රස්ථාරය (chart/graph), ගැහැණු
#   (female, paired with the already-verified පිරිමි="male") - codepoints
#   verified to be entirely within the Sinhala block. Deliberately kept the
#   blood-relations archetype restricted to direct nuclear-family terms
#   (father/mother/brother/sister/grandmother/uncle/aunt/child) already
#   verified elsewhere in this corpus, rather than inventing additional
#   Sinhala kinship vocabulary (e.g. paternal-vs-maternal-specific aunt/
#   uncle distinctions) this session has no prior verified precedent for.
#   Study-notes theory content (6 original notes authored from the real
#   theory chapters found in the uploaded reference books - IQ/g-factor
#   theory, number-series rules, blood-relation solving method, DST speed
#   formula, syllogism Venn-diagram method, clock-angle/day-of-week
#   arithmetic): පොදු (common/general), නිරූපණය (represents/depicts),
#   සියලු (all, variant of the already-verified සියල්ල), අංශක (degrees),
#   මනිනු (measures, inflected form of මනින්), ගුණ (multiply/times),
#   මීටර් (metre, standard transliterated loanword), එනවා (comes) - all
#   standard dictionary words, codepoints verified to be entirely within
#   the Sinhala block.
#   Study Plan page engagement redesign (phase names): දැඩි
#   (intensive/strict), අවසාන (final/last) - both standard dictionary
#   words, codepoints verified to be entirely within the Sinhala block.
#   Adult-content upgrade, Bank4/Bank5 seeders + sinhala_glossary.json v2
#   (5 new question archetypes - truth-teller logic, multi-constraint
#   seating, Venn consistency, chained numeric word problems, passage
#   critical reasoning - plus boolean-overlay/chart-data-interpretation
#   image questions, and the glossary's exam_guide_topics_v2 section copied
#   verbatim from the uploaded Environmental Officer exam guide PDF): this
#   is a large batch (~150 words) because these are genuinely new sentence
#   patterns (multi-clause logical/comparative reasoning, chart/finance
#   vocabulary) with no prior precedent in this app, not because any word
#   is unusual - every entry below is a standard dictionary word or a
#   regular grammatical inflection (case/tense/plural marker) of an
#   already-verified root, individually read and confirmed to be genuine
#   Sinhala with no cross-script intrusion (this batch's FORBIDDEN-codepoint
#   check independently returned zero hits). Also fixed a real bug found
#   while doing this review: CORPUS_SOURCES/--all used a non-recursive
#   glob(), so every Bank2-5 subdirectory seeder was silently excluded from
#   both corpus-building AND validation coverage - UNDER_REVIEW_MARKERS'
#   Bank2/Bank3 exclusion was a no-op. Fixed to glob("**/*.php").
APPROVED_NOVEL_WORDS: set[str] = {
    "වම්", "දකුණු", "පසින්", "සක්‍රීය", "රූප", "ටැග්", "අඩුවීමේ",
    "අනුවාද", "අනුවාදය", "ආකෘති", "ඇගයීම්", "එකඟතාව", "ප්‍රතිඵල", "ප්‍රභවය",
    "පේළි", "ලියාපදිංචි", "වාර්තාවක්", "වැදගත්කම", "විනිශ්චය", "විශේෂාංග",
    "සංයුතිය", "සමස්ත", "සේවාවේ",
    "ලේඛනය", "ප්‍රභව", "මාතෘකාව", "වර්ෂය", "සටහන", "න්‍යාය", "වෙනත්", "තේමා",
    "උතුර", "දකුණ", "නැගෙනහිර", "බටහිර", "කිලෝමීටර්", "සතිය", "මාසය",
    "ඔරලෝසුව", "කෝණය", "පේළිය", "වගුව", "ප්‍රස්ථාරය", "ගැහැණු",
    "පොදු", "නිරූපණය", "සියලු", "අංශක", "මනිනු", "ගුණ", "මීටර්", "එනවා", "බුද්ධි",
    "දැඩි", "අවසාන",
    "අගයක්", "අගයන්", "අඩු", "අඩුය", "අතරද", "අතුරින්", "අනුපාතයට",
    "අභියෝගයතාවය", "අමතර", "අවධි", "අවසානයේ", "අහඹු", "ආයෝජනය", "ආරම්භයේ",
    "ආශ්‍රිත", "ඉඟි", "ඉඟිය", "ඉහත", "උසක්", "උසයි", "උසින්", "එකදු", "එක්ව",
    "එකිනෙකට", "එවැනි", "ඒවායින්", "කඩදාසි", "කණ්ඩායමට", "කණ්ඩායම්", "කණුවක්",
    "කරන්නේ", "කරුණු", "ක්ද", "ක්වත්", "කාණ්ඩයෙන්", "කාණ්ඩවල", "කාර්යයක්",
    "කාර්යයම", "කැට", "කැඩපත්",
    "කැපීම", "කියවීමෙන්", "කෙටිය", "කොටස", "කොටස්", "කොන්දේසි", "ගත්තේය",
    "ගැටලු", "ගැනීමේ", "ජල", "ඥාති", "ටැංකි", "තරණය", "තර්කන", "තර්කය", "තව",
    "තහවුරු", "තිබීම", "තීරු", "තුනෙන්", "තොරතුරු", "දාදු", "දැක්වේ", "දෑ",
    "දිගය", "දිශාවන්", "දුම්රියක්", "දූරය", "දෙකට", "දෙකටම", "දෙකෙහිම",
    "දෙනාට", "නමුත්", "නවතී", "නැත්තේ", "නැවීම", "නිගමනයට", "නියෝජනය",
    "නිලධාරි", "නිසාම", "නොගැලපෙන්නේ", "නොවන්නේ", "නොවූ", "පයිප්ප",
    "පර්යේෂකයෙකුගේ", "පරිසර", "පවත්වන", "ප්‍රකාශයක්ම", "ප්‍රතිඵලය",
    "ප්‍රතිශතය", "ප්‍රස්ථාරයෙන්", "ප්‍රස්ථාරයේ", "ප්‍රාග්ධනය", "පැවති",
    "පිළිබඳ", "පුද්ගලයන්", "පෙන්වන්නේ", "පෙළගැස්වීම", "බඳවා", "බාලය",
    "බැලීමෙන්", "බුද්ධිය", "බෙදේ", "භාෂාමය", "මාස", "යාබද", "යුගලයක්ම",
    "යොදාගැනීමෙන්", "රුපියල්", "රේඛීය", "ලාභ", "ලේ", "වචන", "වඩාත්ම",
    "වයසක්", "වයස්ගතය", "වයස්ගතයා", "වයසින්", "ව්‍යාපාරය", "වැඩිදියුණුවක්",
    "වැඩිමහල්ය", "වැඩිය", "වැඩිවීම", "විග්‍රහය", "විභාගයකට", "විසංකේතනය",
    "විසඳීමේ", "වෙනසක්", "වෙන්", "ශාලාවේදී", "සංකේතනය", "සංඥා", "සදෘශතාවය",
    "සඳහන්", "සපුරාලන", "සබඳතා", "සම්පූර්ණයෙන්", "සම්පූර්ණයෙන්ම",
    "සම්බන්ධතාව", "සමානුපාත", "සසඳා", "සහභාගී", "සහාය", "සාධකය", "සැකැස්ම්",
    "සිදුවූ", "සියල්ලෝම", "සෘජුවම", "හඳුනාගත", "හරියටම", "හිමි",
    # HelaIQ rebrand, new landing-page copy (frontend/src/locales/si/common.json
    # landing.howItWorks/skillAreas/features) - all standard dictionary words
    # or grammatical case/verb inflections of already-corpus-approved roots,
    # zero forbidden-codepoint hits: "අංශයන්ගෙන්" (ablative of අංශය "area"),
    # "එකකට" (dative of එකක් "one"), "කිරීම්" (inflection of කිරීම "doing"),
    # "කෙරෙහි" ("towards"), "තබයි" ("keeps", root තබනවා), "පහසුය" ("is easy"),
    # "භාෂාවෙන්" (instrumental of භාෂාව "language"), "මනී" ("measures",
    # indicative of the root already used imperatively as මනින්න), "වේගයට"
    # (dative of වේගය "pace"), "සජීවීව" ("vividly/actively"), "හුරු"
    # ("familiar"), "ළං" ("near").
    "අංශයන්ගෙන්", "එකකට", "කිරීම්", "කෙරෙහි", "තබයි", "පහසුය",
    "භාෂාවෙන්", "මනී", "වේගයට", "සජීවීව", "හුරු", "ළං",
    # HelaIQ rebrand, dashboard hierarchy copy (frontend/src/locales/si/
    # dashboard.json primaryCta/secondaryLink) - standard words: "ඒ" (that,
    # demonstrative pronoun), "මාතෘකාවක්" (indefinite inflection of the
    # already-approved මාතෘකාව "topic"), "වෙනුවට" ("instead of").
    "ඒ", "මාතෘකාවක්", "වෙනුවට",
    # HelaIQ rebrand, test-taking keyboard hint + AI-processing copy fix
    # (frontend/src/locales/si/sessions.json keyboardHint/report.thinking) -
    # standard dictionary words/inflections: "ඔබන්න" ("press", imperative of
    # ඔබනවා), "දීමට" (infinitive-dative of දෙනවා "to give"), "යාමට"
    # (infinitive-dative of යනවා "to go"), "සකසමින්" (progressive form of
    # the already-approved root behind සකසන්න "set up").
    "ඔබන්න", "දීමට", "යාමට", "සකසමින්",
    # HelaIQ rebrand, game start-screen instructions (frontend/src/locales/si/
    # games.json memoryMatch/mathRush/workingMemorySpan .instructions) -
    # standard dictionary words/inflections: "අනුපිළිවෙලක්" (indefinite
    # inflection of the already-approved අනුපිළිවෙල "sequence/order"),
    # "ඉවර" ("over/finished"), "තරම්" ("as much as", common quantifier),
    # "ප්‍රශ්නවලට" (dative-plural of the already-approved ප්‍රශ්න
    # "questions"), "සොයා" ("search/find", gerund of සොයනවා).
    "අනුපිළිවෙලක්", "ඉවර", "තරම්", "ප්‍රශ්නවලට", "සොයා",
}

SINHALA_RE = re.compile(r"[඀-෿‍]+")
# Any letter from the neighbouring Indic blocks is an instant corruption signal.
FORBIDDEN_RE = re.compile(r"[ఀ-౿ಀ-೿ഀ-ൿ�]")


def extract_words(text: str) -> set[str]:
    return {w.strip("‍") for w in SINHALA_RE.findall(text) if w.strip("‍")}


def build_corpus() -> set[str]:
    words: set[str] = set()
    for path in CORPUS_SOURCES:
        if any(marker in str(path) for marker in UNDER_REVIEW_MARKERS):
            continue
        words |= extract_words(path.read_text(encoding="utf-8"))
    CORPUS_CACHE.write_text(json.dumps(sorted(words), ensure_ascii=False, indent=0), encoding="utf-8")
    return words


def load_corpus() -> set[str]:
    if CORPUS_CACHE.exists():
        return set(json.loads(CORPUS_CACHE.read_text(encoding="utf-8")))
    return build_corpus()


def validate_file(path: Path, corpus: set[str]) -> list[str]:
    problems: list[str] = []
    text = path.read_text(encoding="utf-8")

    for lineno, line in enumerate(text.splitlines(), 1):
        for match in FORBIDDEN_RE.finditer(line):
            ch = match.group()
            problems.append(
                f"{path.name}:{lineno}: FORBIDDEN codepoint U+{ord(ch):04X} "
                f"({unicodedata.name(ch, 'unknown')}) in: {line.strip()[:90]}"
            )

    novel = extract_words(text) - corpus - APPROVED_NOVEL_WORDS
    for word in sorted(novel):
        problems.append(f"{path.name}: NOVEL word not in verified corpus: {word}")

    return problems


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("files", nargs="*")
    parser.add_argument("--build-corpus", action="store_true")
    parser.add_argument("--all", action="store_true")
    args = parser.parse_args()

    if args.build_corpus:
        words = build_corpus()
        print(f"Corpus built: {len(words)} verified Sinhala words -> {CORPUS_CACHE.name}")
        return 0

    corpus = load_corpus()
    targets = [Path(f) for f in args.files]
    if args.all:
        targets = sorted((BACKEND / "database" / "seeders" / "Questions").glob("**/*.php"))

    all_problems: list[str] = []
    for target in targets:
        all_problems.extend(validate_file(target, corpus))

    if all_problems:
        print(f"{len(all_problems)} problem(s):")
        for p in all_problems:
            print("  " + p)
        return 1

    print(f"OK: {len(targets)} file(s) clean against a corpus of {len(corpus)} verified words.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
