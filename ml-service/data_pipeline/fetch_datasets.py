"""
Downloads the two public datasets used to ground MindRise's hybrid training
set in real student behaviour, into data/raw/ (gitignored - regenerate by
re-running this script rather than committing ~500MB of third-party data).

Datasets (selection rationale in docs/ML_RESEARCH_METHODOLOGY.md Sec 2):

1. OULAD - Open University Learning Analytics Dataset
   Kuzilek, J., Hlosta, M. & Zdrahal, Z. Open University Learning Analytics
   dataset. Sci Data 4, 170171 (2017). https://doi.org/10.1038/sdata.2017.171
   License: CC BY 4.0. 32,593 real students, 7 courses, VLE clickstream,
   assessment scores, demographics, final outcome (Pass/Fail/Withdrawn/
   Distinction). Chosen because it is the largest freely-downloadable,
   citable, real learning-analytics dataset with genuine behavioural
   (engagement) signal, not just static demographics.

2. UCI Student Performance Dataset
   Cortez, P. & Silva, A. Using Data Mining to Predict Secondary School
   Student Performance. Proceedings of 5th FUBUTEC (2008).
   License: CC BY 4.0. 649 (Portuguese) + 395 (Math) secondary-school
   students with study time, absences, past failures, and three sequential
   grades (G1/G2/G3) - the closest public analogue to MindRise's own
   improvement_trend / avg_test_score features.

Explicitly NOT fetched (see the methodology doc for the full rationale):
EdNet (>100GB, infeasible to process here), ASSISTments/KDD Cup Educational
(require a per-dataset access request this pipeline cannot complete
non-interactively), xAPI-Edu-Data (small, largely redundant with OULAD's
richer engagement signal).
"""
import sys
import zipfile
from pathlib import Path
from urllib.request import urlretrieve

RAW_DIR = Path(__file__).resolve().parent.parent / "data" / "raw"

SOURCES = {
    "oulad": {
        "url": "https://archive.ics.uci.edu/static/public/349/open+university+learning+analytics+dataset.zip",
        "zip_name": "oulad.zip",
        "extract_to": "oulad",
    },
    "uci_student": {
        "url": "https://archive.ics.uci.edu/ml/machine-learning-databases/00320/student.zip",
        "zip_name": "student.zip",
        "extract_to": "uci_student",
    },
}


def _download(name: str, spec: dict) -> None:
    RAW_DIR.mkdir(parents=True, exist_ok=True)
    zip_path = RAW_DIR / spec["zip_name"]
    extract_dir = RAW_DIR / spec["extract_to"]

    if extract_dir.exists() and any(extract_dir.iterdir()):
        print(f"[{name}] already present at {extract_dir}, skipping download.")
        return

    print(f"[{name}] downloading {spec['url']} ...")
    urlretrieve(spec["url"], zip_path)
    print(f"[{name}] extracting to {extract_dir} ...")
    with zipfile.ZipFile(zip_path) as zf:
        zf.extractall(extract_dir)
    zip_path.unlink()
    print(f"[{name}] done.")


def main() -> None:
    only = sys.argv[1] if len(sys.argv) > 1 else None
    for name, spec in SOURCES.items():
        if only and only != name:
            continue
        _download(name, spec)


if __name__ == "__main__":
    main()
