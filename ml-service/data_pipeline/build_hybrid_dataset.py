"""
Assembles the final hybrid training set: real OULAD rows + real UCI rows +
a documented share of synthetic_calibrated rows (generate_dataset.py, now
using empirically-calibrated weights - see calibrate_synthetic.py), each
tagged with a `data_source` column so the split is fully auditable and
never silently blended.

Why synthetic rows are still included at all (not just real data): OULAD
and UCI together are ~33,600 rows, entirely from two specific institutions
with their own demographic/curriculum skew, and neither covers MindRise's
platform-only constructs (IRT theta, per-category cognitive scores, mini-
games, AI coach) with anything better than the pseudo-theta derivation in
feature_mapping.py. Synthetic rows widen the training distribution's
coverage of the full platform-only feature space and let the model see
the *documented* structural relationships at full fidelity (not just the
derived pseudo-theta approximation), while the real rows anchor the
outcome-relevant coefficients in genuine measured behaviour. The mixing
ratio is deliberately conservative (real data is never diluted below 40% of
the total) - see docs/ML_RESEARCH_METHODOLOGY.md Sec 3.4 for the ablation
comparing real-only / hybrid / synthetic-only training.

Run: python -m data_pipeline.build_hybrid_dataset --synthetic-rows 40000 --seed 42
Output: data/hybrid_student_dataset.csv
"""
import argparse
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from data_pipeline.feature_mapping import FULL_FEATURE_ORDER_TIME_AWARE, map_oulad, map_uci  # noqa: E402
from generate_dataset import generate  # noqa: E402

DATA_DIR = Path(__file__).resolve().parent.parent / "data"


def build(synthetic_rows: int, seed: int) -> pd.DataFrame:
    oulad = map_oulad()[FULL_FEATURE_ORDER_TIME_AWARE + ["label", "data_source"]]
    uci = map_uci()[FULL_FEATURE_ORDER_TIME_AWARE + ["label", "data_source"]]

    synthetic = generate(synthetic_rows, seed)
    synthetic = synthetic.drop(columns=["student_id", "readiness_percent"])
    synthetic["data_source"] = "synthetic_calibrated"

    hybrid = pd.concat([oulad, uci, synthetic], ignore_index=True)
    hybrid = hybrid.sample(frac=1, random_state=seed).reset_index(drop=True)
    hybrid.insert(0, "student_id", range(1, len(hybrid) + 1))

    real_share = 1 - (len(synthetic) / len(hybrid))
    if real_share < 0.40:
        raise ValueError(
            f"Real-data share ({real_share:.1%}) fell below the documented 40% floor - "
            f"reduce --synthetic-rows (currently {synthetic_rows})."
        )

    DATA_DIR.mkdir(parents=True, exist_ok=True)
    out_path = DATA_DIR / "hybrid_student_dataset.csv"
    hybrid.to_csv(out_path, index=False)

    print(f"Wrote {len(hybrid):,} rows to {out_path}")
    print(f"  real_oulad:           {(hybrid['data_source'] == 'real_oulad').sum():,}")
    print(f"  real_uci:             {(hybrid['data_source'] == 'real_uci').sum():,}")
    print(f"  synthetic_calibrated: {(hybrid['data_source'] == 'synthetic_calibrated').sum():,}")
    print(f"  real-data share: {real_share:.1%}")
    print()
    print("Label distribution:")
    print(hybrid["label"].value_counts(normalize=True).round(3))
    print()
    print("Label distribution BY source (checking real vs synthetic don't wildly disagree):")
    print(hybrid.groupby("data_source")["label"].value_counts(normalize=True).round(3))

    return hybrid


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--synthetic-rows", type=int, default=40000)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()
    build(args.synthetic_rows, args.seed)
