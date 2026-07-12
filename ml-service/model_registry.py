"""
Versions every trained model and keeps a complete experiment history
(models/registry.json), implementing a champion-vs-challenger promotion
gate: a newly trained model only replaces the live one if it's genuinely
better on the same held-out test set, never automatically overwriting a
working model with a worse one.

The live model always lives at the fixed models/model.joblib +
models/scaler.joblib + models/metadata.json paths app.py already expects
(this file never changes that contract - only WHICH artifacts sit there).
Every version, live or not, is archived under models/versions/v{id}/ so
past experiments are never lost.
"""
import hashlib
import json
import shutil
from datetime import datetime, timezone
from pathlib import Path

MODELS_DIR = Path(__file__).parent / "models"
VERSIONS_DIR = MODELS_DIR / "versions"
REGISTRY_PATH = MODELS_DIR / "registry.json"

LIVE_ARTIFACT_NAMES = ["model.joblib", "scaler.joblib", "metadata.json", "model_comparison_report.json", "evaluation_report.json", "explainability_report.json"]


def _load_registry() -> dict:
    if REGISTRY_PATH.exists():
        return json.loads(REGISTRY_PATH.read_text())
    return {"versions": [], "live_version": None}


def _save_registry(registry: dict) -> None:
    REGISTRY_PATH.write_text(json.dumps(registry, indent=2))


def _data_snapshot_hash(data_path: Path) -> str:
    """SHA-256 of the training data file - lets the registry answer 'was this model trained
    on the exact same data as that one' without storing the whole dataset per version."""
    h = hashlib.sha256()
    with open(data_path, "rb") as f:
        for chunk in iter(lambda: f.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()[:16]


def register_version(data_path: Path, gating_metric: str = "f1_macro") -> dict:
    """
    Archives whatever is currently sitting at the live artifact paths (just
    produced by model_comparison.py + evaluate.py + explain.py) as a new
    version in the registry, WITHOUT changing which version is live -
    call promote() separately once the champion-vs-challenger comparison
    (see retrain.py) decides this version should go live.
    """
    metadata_path = MODELS_DIR / "metadata.json" if (MODELS_DIR / "metadata.json").exists() else MODELS_DIR / "model_comparison_report.json"
    if not metadata_path.exists():
        raise FileNotFoundError("No metadata.json/model_comparison_report.json found - run model_comparison.py first.")
    metadata = json.loads(metadata_path.read_text())

    eval_report = MODELS_DIR / "evaluation_report.json"
    gating_score = None
    if eval_report.exists():
        gating_score = json.loads(eval_report.read_text())["core_metrics"].get(gating_metric)
    elif "default_vs_optimized_test_f1" in metadata:
        best = metadata["best_model"]
        gating_score = metadata["default_vs_optimized_test_f1"][best]["optimized_test_f1_macro"]

    version_id = metadata.get("version", datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S"))
    version_dir = VERSIONS_DIR / f"v{version_id}"
    version_dir.mkdir(parents=True, exist_ok=True)

    for name in LIVE_ARTIFACT_NAMES:
        src = MODELS_DIR / name
        if src.exists():
            shutil.copy2(src, version_dir / name)

    entry = {
        "version_id": version_id,
        "registered_at": datetime.now(timezone.utc).isoformat(),
        "best_model": metadata.get("best_model"),
        "training_rows": metadata.get("training_rows"),
        "data_snapshot_hash": _data_snapshot_hash(data_path) if data_path.exists() else None,
        "gating_metric": gating_metric,
        "gating_score": gating_score,
    }

    registry = _load_registry()
    registry["versions"].append(entry)
    _save_registry(registry)

    print(f"Registered version v{version_id} ({gating_metric}={gating_score})")
    return entry


def current_live_score(gating_metric: str = "f1_macro") -> float | None:
    registry = _load_registry()
    if registry["live_version"] is None:
        return None
    for v in registry["versions"]:
        if v["version_id"] == registry["live_version"]:
            return v["gating_score"]
    return None


def promote(version_id: str) -> None:
    """Copies a specific archived version's artifacts back to the live paths and marks it as
    the live version in the registry - the only function that changes what app.py actually
    serves."""
    version_dir = VERSIONS_DIR / f"v{version_id}"
    if not version_dir.exists():
        raise FileNotFoundError(f"No archived version v{version_id} found.")

    for name in LIVE_ARTIFACT_NAMES:
        src = version_dir / name
        if src.exists():
            shutil.copy2(src, MODELS_DIR / name)

    registry = _load_registry()
    registry["live_version"] = version_id
    _save_registry(registry)
    print(f"Promoted v{version_id} to live.")


def list_versions() -> list:
    return _load_registry()["versions"]


if __name__ == "__main__":
    import sys

    if len(sys.argv) > 1 and sys.argv[1] == "list":
        for v in list_versions():
            live_marker = " (LIVE)" if v["version_id"] == _load_registry()["live_version"] else ""
            print(f"v{v['version_id']}{live_marker}: {v['best_model']}, {v['gating_metric']}={v['gating_score']}, "
                  f"trained on {v['training_rows']} rows")
    else:
        print("Usage: python model_registry.py list")
