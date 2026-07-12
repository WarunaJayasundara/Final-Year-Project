"""
Champion-vs-challenger retraining orchestrator: runs the full training
pipeline fresh, registers the result as a new version, and only promotes it
to live if it beats the currently-deployed model's gating score by more
than PROMOTION_MARGIN (never automatically replaces a working model with a
worse or barely-different one, and never on the first run - the first
trained model is always promoted since there's no champion yet to compare
against).

This implements the "retraining strategy" and "compare new models with
deployed models before replacing them" requirements: it does NOT implement
a fully automatic scheduled production MLOps pipeline (drift-detection
dashboards, automatic real-usage-data collection triggers) - that is
disproportionate infrastructure for a single-VM student project and is
documented as a deployment configuration note, not built here. What this
script does mechanically implement and that you can actually run: fresh
training + evaluation + explainability + versioning + a real promotion gate.

Run: python retrain.py [--data data/hybrid_student_dataset.csv]
"""
import argparse
import subprocess
import sys
from pathlib import Path

import model_registry

PROMOTION_MARGIN = 0.005  # a challenger must beat the champion's f1_macro by at least this much
GATING_METRIC = "f1_macro"


def _run(cmd: list) -> None:
    print(f"$ {' '.join(cmd)}")
    result = subprocess.run(cmd, cwd=Path(__file__).parent)
    if result.returncode != 0:
        raise RuntimeError(f"Command failed: {' '.join(cmd)}")


def main(data_path: str):
    python = sys.executable
    data = Path(data_path)

    champion_score = model_registry.current_live_score(GATING_METRIC)
    print(f"Current live model's {GATING_METRIC}: {champion_score}")

    print("\n=== Step 1/3: model_comparison.py (fresh training + HPO) ===")
    _run([python, "model_comparison.py", "--data", str(data)])

    print("\n=== Step 2/3: evaluate.py (comprehensive evaluation) ===")
    _run([python, "evaluate.py"])

    print("\n=== Step 3/3: explain.py (explainability report) ===")
    _run([python, "explain.py"])

    entry = model_registry.register_version(data, gating_metric=GATING_METRIC)
    challenger_score = entry["gating_score"]

    if champion_score is None:
        print(f"\nNo champion exists yet - promoting v{entry['version_id']} as the first live model.")
        model_registry.promote(entry["version_id"])
        return

    improvement = challenger_score - champion_score
    print(f"\nChampion {GATING_METRIC}: {champion_score:.4f}")
    print(f"Challenger {GATING_METRIC}: {challenger_score:.4f} (delta={improvement:+.4f})")

    if improvement >= PROMOTION_MARGIN:
        print(f"Challenger beats champion by >= {PROMOTION_MARGIN} - promoting v{entry['version_id']}.")
        model_registry.promote(entry["version_id"])
    else:
        print(f"Challenger does not beat champion by the required margin ({PROMOTION_MARGIN}) - "
              f"keeping the current live model. Challenger remains archived as v{entry['version_id']} for reference.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--data", type=str, default="data/hybrid_student_dataset.csv")
    args = parser.parse_args()
    main(args.data)
