"""
Builds a TEMPORALLY-SPLIT version of OULAD, distinct from process_oulad.py's
cross-sectional summary: for each (student, module presentation), the
first half of the module's real activity/assessment records become the
FEATURES, and something that only happens in the second half becomes the
TARGET - a genuine forward-looking prediction setup with no target leakage,
needed for the 3 multi-output targets that require real ground truth
(see train_multioutput.py):

  risk_of_dropping_practice (binary): 1 if the student has zero VLE activity
    in the second half of the module (a real, measurable disengagement
    event), given only first-half behaviour.

  next_assessment_score (regression): the score of the LAST assessment
    submitted in the second half, given first-half assessment history.

  score_change (regression): (second-half average score) - (first-half
    average score) - a genuine "did this student's performance improve"
    ground truth, the closest real analogue to a learning-velocity target.

UCI is excluded from this pipeline: with only 3 static grades per student
and no dated activity log, there is no defensible way to carve out a
"first half" of behaviour distinct from the target - attempting it would
mean deriving both features and target from the same 1-2 remaining grade
points, which is target leakage in disguise, not a genuine forward-looking
split. Multi-output targets are therefore trained on real OULAD data only
(no synthetic augmentation - see train_multioutput.py for why the volume
is still adequate).

Run: python -m data_pipeline.process_oulad_temporal
Output: data/processed/oulad_temporal.csv
"""
from pathlib import Path

import numpy as np
import pandas as pd

RAW = Path(__file__).resolve().parent.parent / "data" / "raw" / "oulad"
OUT = Path(__file__).resolve().parent.parent / "data" / "processed"
VLE_CHUNKSIZE = 500_000


def _half_split_vle() -> pd.DataFrame:
    """Per (module, presentation, student): first/second-half VLE engagement, split at the
    module's temporal midpoint (module_presentation_length / 2)."""
    courses = pd.read_csv(RAW / "courses.csv")
    midpoint = dict(zip(
        zip(courses["code_module"], courses["code_presentation"]),
        (courses["module_presentation_length"] / 2).tolist(),
    ))

    totals: dict[tuple, dict] = {}
    reader = pd.read_csv(
        RAW / "studentVle.csv",
        usecols=["code_module", "code_presentation", "id_student", "date", "sum_click"],
        chunksize=VLE_CHUNKSIZE,
    )
    for chunk in reader:
        for key, sub in chunk.groupby(["code_module", "code_presentation", "id_student"]):
            module, presentation, _ = key
            mid = midpoint.get((module, presentation), 90)
            acc = totals.setdefault(key, {"first_clicks": 0, "first_days": set(), "second_days": set()})
            first_mask = sub["date"] < mid
            acc["first_clicks"] += int(sub.loc[first_mask, "sum_click"].sum())
            acc["first_days"].update(sub.loc[first_mask, "date"].tolist())
            acc["second_days"].update(sub.loc[~first_mask, "date"].tolist())

    rows = []
    for (module, presentation, student_id), acc in totals.items():
        rows.append({
            "code_module": module, "code_presentation": presentation, "id_student": student_id,
            "first_half_clicks": acc["first_clicks"],
            "first_half_active_days": len(acc["first_days"]),
            "second_half_active_days": len(acc["second_days"]),
            "risk_of_dropping_practice": int(len(acc["second_days"]) == 0),
        })
    return pd.DataFrame(rows)


def _half_split_assessments() -> pd.DataFrame:
    courses = pd.read_csv(RAW / "courses.csv")
    midpoint = dict(zip(
        zip(courses["code_module"], courses["code_presentation"]),
        (courses["module_presentation_length"] / 2).tolist(),
    ))
    assessments = pd.read_csv(RAW / "assessments.csv")
    student_assessment = pd.read_csv(RAW / "studentAssessment.csv")

    merged = student_assessment.merge(
        assessments[["code_module", "code_presentation", "id_assessment", "date"]].rename(columns={"date": "date_due"}),
        on="id_assessment", how="left",
    )
    merged["score"] = pd.to_numeric(merged["score"], errors="coerce")
    merged["date_due"] = pd.to_numeric(merged["date_due"], errors="coerce")
    merged = merged.dropna(subset=["score", "date_due"])
    merged["mid"] = merged.apply(lambda r: midpoint.get((r["code_module"], r["code_presentation"]), 90), axis=1)
    merged["is_first_half"] = merged["date_due"] < merged["mid"]

    def per_group(g: pd.DataFrame) -> pd.Series:
        first = g[g["is_first_half"]].sort_values("date_due")
        second = g[~g["is_first_half"]].sort_values("date_due")

        first_avg = first["score"].mean() if len(first) else np.nan
        second_avg = second["score"].mean() if len(second) else np.nan
        next_score = second["score"].iloc[-1] if len(second) else np.nan

        return pd.Series({
            "first_half_n_assessments": len(first),
            "first_half_avg_score": first_avg,
            "next_assessment_score": next_score,
            "score_change": (second_avg - first_avg) if (len(first) and len(second)) else np.nan,
        })

    return merged.groupby(["code_module", "code_presentation", "id_student"]).apply(
        per_group, include_groups=False
    ).reset_index()


def build() -> pd.DataFrame:
    info = pd.read_csv(RAW / "studentInfo.csv")
    courses = pd.read_csv(RAW / "courses.csv")
    assessments = pd.read_csv(RAW / "assessments.csv")
    vle_half = _half_split_vle()
    assess_half = _half_split_assessments()

    df = info.merge(vle_half, on=["code_module", "code_presentation", "id_student"], how="inner")
    df = df.merge(assess_half, on=["code_module", "code_presentation", "id_student"], how="inner")
    df = df.merge(courses, on=["code_module", "code_presentation"], how="left")

    # A first-half assessment is required for the regression targets (a
    # student with zero first-half assessments has nothing to compute
    # first_half_avg_score from) - dropped rather than imputed, since
    # imputing a fake "first half score" for students with no real one
    # would fabricate the very ground truth these targets exist to provide.
    df = df.dropna(subset=["first_half_avg_score"])

    # Canonically-named feature columns (matching FULL_FEATURE_ORDER's
    # naming/derivation, computed on first-half-only data) - so the SAME
    # live feature vector Laravel already sends for the main readiness
    # classifier can feed these multi-output models too, rather than
    # requiring a second, incompatible feature contract at inference time.
    df["module_weeks_half"] = (df["module_presentation_length"] / 2 / 7).clip(lower=1)
    df["avg_test_score"] = df["first_half_avg_score"].round(2)
    df["weekly_practice_count"] = (df["first_half_active_days"] / df["module_weeks_half"]).round(2)

    total_per_module = assessments.groupby(["code_module", "code_presentation"]).size().rename("total_assessments")
    df = df.merge(total_per_module, on=["code_module", "code_presentation"], how="left")
    # Only ~half the module's assessments are due by the midpoint - divide
    # the real denominator in half to keep this a genuine 0-100 completion
    # rate for "assessments due so far," not an artificially deflated one.
    df["question_completion_rate"] = (
        (df["first_half_n_assessments"] / (df["total_assessments"] / 2).clip(lower=1)).clip(upper=1.0) * 100
    ).round(2)

    def z(s):
        return (s - s.mean()) / (s.std() + 1e-9)

    engagement_z = (z(df["first_half_active_days"]) + z(df["first_half_clicks"])) / 2
    df["engagement_score"] = (100 / (1 + np.exp(-engagement_z))).round(2)

    median_rate = df["weekly_practice_count"].replace(0, np.nan).median()
    df["practice_intensity"] = ((df["weekly_practice_count"] / (median_rate + 1e-9)) * 100).clip(upper=300).round(2)

    OUT.mkdir(parents=True, exist_ok=True)
    df.to_csv(OUT / "oulad_temporal.csv", index=False)
    print(f"Wrote {len(df):,} rows to {OUT / 'oulad_temporal.csv'}")
    print(f"  risk_of_dropping_practice positive rate: {df['risk_of_dropping_practice'].mean():.3f}")
    print(f"  next_assessment_score available: {df['next_assessment_score'].notna().sum():,} / {len(df):,}")
    print(f"  score_change available: {df['score_change'].notna().sum():,} / {len(df):,}")

    return df


if __name__ == "__main__":
    build()
