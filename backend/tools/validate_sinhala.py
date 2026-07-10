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
CORPUS_SOURCES = [
    *(BACKEND / "database" / "seeders").glob("*.php"),
    *(BACKEND / "database" / "seeders" / "Questions").glob("*.php"),
    *(PROJECT / "frontend" / "src" / "locales" / "si").glob("*.json"),
]

# Files being validated must not contribute to the corpus they are checked
# against; new seeders are listed here while under review.
UNDER_REVIEW_MARKER = "Bank2"

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
APPROVED_NOVEL_WORDS: set[str] = {"වම්", "දකුණු", "පසින්", "සක්‍රීය", "රූප", "ටැග්"}

SINHALA_RE = re.compile(r"[඀-෿‍]+")
# Any letter from the neighbouring Indic blocks is an instant corruption signal.
FORBIDDEN_RE = re.compile(r"[ఀ-౿ಀ-೿ഀ-ൿ�]")


def extract_words(text: str) -> set[str]:
    return {w.strip("‍") for w in SINHALA_RE.findall(text) if w.strip("‍")}


def build_corpus() -> set[str]:
    words: set[str] = set()
    for path in CORPUS_SOURCES:
        if UNDER_REVIEW_MARKER in path.name:
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
        targets = sorted((BACKEND / "database" / "seeders" / "Questions").glob("*.php"))

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
