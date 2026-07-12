"""
Aggregates the raw OULAD tables (data/raw/oulad/*.csv) into one row per
(student, module presentation) with features mapped onto MindRise's schema
wherever a genuine analogue exists. studentVle.csv (10.6M rows, ~450MB) is
read in chunks and reduced to per-student aggregates rather than loaded
whole, since only the aggregate (total engagement, active days, activity
diversity) is meaningful for this mapping - the row-level clickstream has no
MindRise analogue to preserve.

Run: python -m data_pipeline.process_oulad
Output: data/processed/oulad_features.csv
"""
from pathlib import Path

import numpy as np
import pandas as pd

RAW = Path(__file__).resolve().parent.parent / "data" / "raw" / "oulad"
OUT = Path(__file__).resolve().parent.parent / "data" / "processed"

VLE_CHUNKSIZE = 500_000

# final_result -> a readiness label. Distinction/Pass students completed the
# module successfully (near/at "ready"); Fail students engaged but did not
# meet the bar ("needs_improvement"); Withdrawn students disengaged before
# completion, the closest OULAD analogue to MindRise's "high_risk" (low
# engagement + poor predicted outcome) rather than "poor performance".
FINAL_RESULT_TO_LABEL = {
    "Distinction": "ready",
    "Pass": "almost_ready",
    "Fail": "needs_improvement",
    "Withdrawn": "high_risk",
}

# imd_band (index of multiple deprivation, England) midpoint used only for
# the bias/fairness analysis in the methodology doc, not as a model feature -
# deprivation should not be an input to an "exam readiness" prediction.
IMD_MIDPOINT = {
    "0-10%": 5, "10-20": 15, "10-20%": 15, "20-30%": 25, "30-40%": 35,
    "40-50%": 45, "50-60%": 55, "60-70%": 65, "70-80%": 75, "80-90%": 85,
    "90-100%": 95,
}


def _aggregate_vle() -> pd.DataFrame:
    """
    Per (student, module, presentation): total clicks, active days, distinct
    activity sites touched. Accumulated across chunks using true global
    date/site sets (not a per-chunk nunique(), which would under-count
    anything split across a chunk boundary) - the running dict stays small
    (~32K groups) even though the source file has 10.6M rows.
    """
    totals: dict[tuple, dict] = {}

    reader = pd.read_csv(
        RAW / "studentVle.csv",
        usecols=["code_module", "code_presentation", "id_student", "id_site", "date", "sum_click"],
        chunksize=VLE_CHUNKSIZE,
    )
    for chunk in reader:
        for key, sub in chunk.groupby(["code_module", "code_presentation", "id_student"]):
            acc = totals.setdefault(key, {"total_clicks": 0, "active_days": set(), "distinct_sites": set()})
            acc["total_clicks"] += int(sub["sum_click"].sum())
            acc["active_days"].update(sub["date"].tolist())
            acc["distinct_sites"].update(sub["id_site"].tolist())

    rows = []
    for (module, presentation, student_id), acc in totals.items():
        active_days = len(acc["active_days"])
        distinct = len(acc["distinct_sites"])
        # Fewer distinct resources touched than days active => the student is
        # re-visiting already-seen material rather than exploring new
        # content each day - a real, if approximate, revision signal.
        revision_frequency = max(0.0, min(100.0, 100 * (1 - distinct / active_days))) if active_days > 0 else 0.0

        rows.append({
            "code_module": module,
            "code_presentation": presentation,
            "id_student": student_id,
            "vle_total_clicks": acc["total_clicks"],
            "vle_active_days": active_days,
            "vle_distinct_activities": distinct,
            "vle_longest_streak": _longest_consecutive_run(acc["active_days"]),
            "revision_frequency": round(revision_frequency, 2),
        })

    return pd.DataFrame(rows)


def _longest_consecutive_run(day_offsets: set) -> int:
    """Longest run of consecutive integer day-offsets in a real activity log - a true measured
    engagement streak (not a proxy), used for MindRise's practice_streak feature."""
    if not day_offsets:
        return 0
    days = sorted(day_offsets)
    longest = current = 1
    for prev, curr in zip(days, days[1:]):
        if curr == prev + 1:
            current += 1
            longest = max(longest, current)
        elif curr != prev:
            current = 1
    return longest


def _ols_slope(x: np.ndarray, y: np.ndarray) -> float:
    """beta_1 from y = beta_0 + beta_1*x + eps via closed-form OLS - used for weekly_trend,
    monthly_trend, difficulty_progression (proxy). Returns 0.0 for <2 valid points (no slope defined)."""
    mask = ~(np.isnan(x) | np.isnan(y))
    x, y = x[mask], y[mask]
    if len(x) < 2 or np.std(x) == 0:
        return 0.0
    return float(np.polyfit(x, y, 1)[0])


def _aggregate_assessments() -> pd.DataFrame:
    assessments = pd.read_csv(RAW / "assessments.csv")
    student_assessment = pd.read_csv(RAW / "studentAssessment.csv")

    # Total assessments scheduled per module presentation - the denominator
    # for a real (not proxied) question_completion_rate: how many of the
    # assessments actually set for this course did the student submit.
    total_per_module = assessments.groupby(["code_module", "code_presentation"]).size().rename("total_assessments")

    merged = student_assessment.merge(
        assessments[["code_module", "code_presentation", "id_assessment", "date"]].rename(columns={"date": "date_due"}),
        on="id_assessment", how="left",
    )
    merged["score"] = pd.to_numeric(merged["score"], errors="coerce")
    merged = merged.dropna(subset=["score"])
    # date_due is NaN for a handful of exam-type assessments (open-ended
    # window) - lateness for those rows is left undefined (NaN), excluded
    # from the per-student mean rather than treated as zero lateness.
    merged["lateness_days"] = pd.to_numeric(merged["date_submitted"], errors="coerce") - pd.to_numeric(merged["date_due"], errors="coerce")

    def per_group(g: pd.DataFrame) -> pd.Series:
        g = g.sort_values("date_due")
        n = len(g)
        scores = g["score"].to_numpy(dtype=float)
        dates = pd.to_numeric(g["date_due"], errors="coerce").to_numpy(dtype=float)
        avg_score = scores.mean()
        std_score = scores.std(ddof=0) if n > 1 else 0.0
        if n >= 2:
            half = n // 2
            trend = scores[half:].mean() - scores[:half].mean()
        else:
            trend = 0.0

        weeks = dates / 7.0
        months = dates / 30.0
        # Clipped to the same bounds structural_model/advanced_features.py
        # documents for the synthetic-calibrated rows: a handful of students
        # with only 2-3 widely-spaced assessments produce a numerically
        # unstable OLS slope (a near-vertical or near-horizontal line
        # through very few points) that isn't a genuine trend signal.
        weekly_trend = np.clip(_ols_slope(weeks, scores), -6, 6)
        monthly_trend = np.clip(_ols_slope(months, scores), -10, 10)
        rolling_avg_score = scores[-5:].mean()
        knowledge_gain_rate = (scores[-1] - scores[0]) / n if n >= 2 else 0.0
        cv = (std_score / avg_score) if avg_score > 0 else 0.0
        consistency_index = max(0.0, min(100.0, 100 * (1 - cv)))
        avg_lateness = g["lateness_days"].dropna().mean()
        time_management_score = max(0.0, min(100.0, 100 - abs(avg_lateness) * 5)) if pd.notna(avg_lateness) else 50.0

        return pd.Series({
            "n_assessments": n,
            "avg_assessment_score": avg_score,
            "assessment_score_std": std_score,
            "assessment_score_trend": trend,
            "weekly_trend": weekly_trend,
            "monthly_trend": monthly_trend,
            "rolling_avg_score": rolling_avg_score,
            "knowledge_gain_rate": knowledge_gain_rate,
            "consistency_index": consistency_index,
            "time_management_score": time_management_score,
        })

    result = merged.groupby(["code_module", "code_presentation", "id_student"]).apply(
        per_group, include_groups=False
    ).reset_index()

    result = result.merge(total_per_module, on=["code_module", "code_presentation"], how="left")
    result["question_completion_rate"] = (
        (result["n_assessments"] / result["total_assessments"]).clip(upper=1.0) * 100
    ).round(2)

    return result.drop(columns=["total_assessments"])


def build() -> pd.DataFrame:
    info = pd.read_csv(RAW / "studentInfo.csv")
    registration = pd.read_csv(RAW / "studentRegistration.csv")
    courses = pd.read_csv(RAW / "courses.csv")
    vle_catalog = pd.read_csv(RAW / "vle.csv")

    info = info.merge(registration, on=["code_module", "code_presentation", "id_student"], how="left")
    info = info.merge(courses, on=["code_module", "code_presentation"], how="left")
    info = info.merge(_aggregate_assessments(), on=["code_module", "code_presentation", "id_student"], how="left")
    info = info.merge(_aggregate_vle(), on=["code_module", "code_presentation", "id_student"], how="left")

    # Withdrew before the module ended, or never engaged at all -> the
    # engagement/assessment aggregates are structurally missing (0), not
    # "average" - fill with 0 rather than a mean so the model can learn the
    # (very real) pattern that no-engagement predicts poor outcome.
    for col in ["n_assessments", "avg_assessment_score", "assessment_score_std", "assessment_score_trend",
                "weekly_trend", "monthly_trend", "rolling_avg_score", "knowledge_gain_rate",
                "consistency_index", "time_management_score", "question_completion_rate",
                "vle_total_clicks", "vle_active_days", "vle_distinct_activities", "vle_longest_streak",
                "revision_frequency"]:
        info[col] = info[col].fillna(0)

    info["module_weeks"] = (info["module_presentation_length"] / 7).clip(lower=1)
    info["vle_weekly_active_rate"] = (info["vle_active_days"] / info["module_weeks"]).round(2)

    # question_diversity_score: breadth of VLE resource types touched out of
    # everything available in that module presentation (real denominator
    # from vle.csv's own site catalog, not an assumed constant).
    sites_per_module = vle_catalog.groupby(["code_module", "code_presentation"]).size().rename("total_sites")
    info = info.merge(sites_per_module, on=["code_module", "code_presentation"], how="left")
    info["question_diversity_score"] = (
        (info["vle_distinct_activities"] / info["total_sites"]).clip(upper=1.0) * 100
    ).round(2)

    # engagement_score and practice_intensity are cohort-relative by
    # construction (see advanced_features.py's math spec) - computed here,
    # after aggregation, against this cohort's own distribution rather than
    # an arbitrary fixed constant.
    def z(s):
        return (s - s.mean()) / (s.std() + 1e-9)

    engagement_z = (z(info["vle_active_days"]) + z(info["vle_total_clicks"]) + z(info["vle_distinct_activities"])) / 3
    info["engagement_score"] = (100 / (1 + np.exp(-engagement_z))).round(2)  # logistic squash to [0,100]

    median_weekly_rate = info["vle_weekly_active_rate"].replace(0, np.nan).median()
    info["practice_intensity"] = (
        (info["vle_weekly_active_rate"] / (median_weekly_rate + 1e-9)) * 100
    ).clip(upper=300).round(2)

    # difficulty_progression has no OULAD analogue (no item-difficulty
    # concept) - reuse the already-computed real assessment_score_trend as
    # the closest available real proxy ("is this student's measured
    # performance moving up over time"), documented explicitly rather than
    # silently reusing the column under a different name.
    info["difficulty_progression"] = (info["assessment_score_trend"] / 10).clip(-1, 1).round(3)

    # learning_velocity: OULAD provides only one pseudo-theta per row (no
    # within-module ability *history*), so real theta-change-per-week can't
    # be measured here - approximated from the real score trend expressed
    # per week of module length, on the same theta-equivalent scale
    # (score-points/15, mirroring the pseudo-theta derivation in
    # feature_mapping.py) rather than left as a pure synthetic guess.
    info["learning_velocity"] = ((info["assessment_score_trend"] / 15) / info["module_weeks"]).round(4)

    info["imd_midpoint"] = info["imd_band"].map(IMD_MIDPOINT)
    info["label"] = info["final_result"].map(FINAL_RESULT_TO_LABEL)
    info = info.dropna(subset=["label"])

    OUT.mkdir(parents=True, exist_ok=True)
    info.to_csv(OUT / "oulad_features.csv", index=False)
    print(f"Wrote {len(info):,} rows to {OUT / 'oulad_features.csv'}")
    print(info["label"].value_counts(normalize=True).round(3))

    return info


if __name__ == "__main__":
    build()
