"""
Loads the UCI Student Performance dataset (semicolon-delimited, two subject
files) and normalizes it into the same processed-row shape produced by
process_oulad.py, so build_hybrid_dataset.py can treat both real sources
uniformly. See Cortez & Silva (2008) - full citation in fetch_datasets.py.

Run: python -m data_pipeline.process_uci
Output: data/processed/uci_features.csv
"""
from pathlib import Path

import pandas as pd

RAW = Path(__file__).resolve().parent.parent / "data" / "raw" / "uci_student"
OUT = Path(__file__).resolve().parent.parent / "data" / "processed"

FILES = {"mat": "student-mat.csv", "por": "student-por.csv"}


def _label_from_g3(g3: float) -> str:
    """
    G3 (final grade) is on a 0-20 Portuguese scale. Bucketed onto MindRise's
    4-class readiness scale using the same proportional cut points as the
    percent->label bins in generate_dataset.py (40/60/80 out of 100), scaled
    to 0-20: 8/12/16.
    """
    if g3 < 8:
        return "high_risk"
    if g3 < 12:
        return "needs_improvement"
    if g3 < 16:
        return "almost_ready"
    return "ready"


def build() -> pd.DataFrame:
    frames = []
    for subject, filename in FILES.items():
        df = pd.read_csv(RAW / filename, sep=";")
        df["subject"] = subject
        frames.append(df)

    combined = pd.concat(frames, ignore_index=True)

    combined["label"] = combined["G3"].apply(_label_from_g3)
    # First-half vs second-half analogue: only two prior grades exist (G1,
    # G2) before the final G3, so the "trend" is simply G2-G1 - the same
    # early-vs-late split idea as OULAD's assessment_score_trend, just with
    # fewer data points per student.
    combined["grade_trend"] = combined["G2"] - combined["G1"]
    combined["avg_grade"] = combined[["G1", "G2", "G3"]].mean(axis=1)

    OUT.mkdir(parents=True, exist_ok=True)
    combined.to_csv(OUT / "uci_features.csv", index=False)
    print(f"Wrote {len(combined):,} rows to {OUT / 'uci_features.csv'}")
    print(combined["label"].value_counts(normalize=True).round(3))

    return combined


if __name__ == "__main__":
    build()
