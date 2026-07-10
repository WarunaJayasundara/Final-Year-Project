"""
FastAPI inference microservice for the exam-readiness model. Laravel's
ReadinessPredictionService calls this over HTTP (same swappable-service
pattern already used for the Gemini AI feedback integration) rather than
Laravel reimplementing gradient boosting, so training and inference always
use the exact same scikit-learn/XGBoost/SHAP code path.

Run:
    uvicorn app:app --host 127.0.0.1 --port 8100
"""
import json
from pathlib import Path
from typing import Dict, List

import joblib
import numpy as np
import shap
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

MODELS_DIR = Path(__file__).parent / "models"

FEATURE_ORDER = [
    "placement_iq", "current_iq", "theta", "avg_test_score",
    "memory_score", "logical_score", "numerical_score", "attention_score", "spatial_score",
    "avg_game_score", "daily_practice_count", "weekly_practice_count", "practice_streak",
    "study_hours", "avg_response_time_sec", "wrong_answer_percent", "avg_difficulty_solved",
    "improvement_trend", "consistency_score", "attendance_percent", "days_until_exam",
    "motivation_score", "ai_coach_usage_count", "question_completion_rate",
]

LABEL_ORDER = ["high_risk", "needs_improvement", "almost_ready", "ready"]
LABEL_MIDPOINT = {"high_risk": 20, "needs_improvement": 45, "almost_ready": 70, "ready": 90}

FEATURE_MESSAGES: Dict[str, Dict[str, str]] = {
    "placement_iq": {"pos": "Strong baseline ability at placement", "neg": "Lower baseline ability at placement"},
    "current_iq": {"pos": "Strong current IQ estimate", "neg": "Current IQ estimate below target"},
    "theta": {"pos": "High measured cognitive ability (IRT theta)", "neg": "Cognitive ability estimate needs strengthening"},
    "avg_test_score": {"pos": "High average test scores", "neg": "Average test scores below target"},
    "memory_score": {"pos": "Excellent memory performance", "neg": "Weak memory performance"},
    "logical_score": {"pos": "Excellent logical reasoning", "neg": "Weak logical reasoning"},
    "numerical_score": {"pos": "Excellent numerical ability", "neg": "Weak numerical ability"},
    "attention_score": {"pos": "Strong attention/focus performance", "neg": "Weak attention/focus performance"},
    "spatial_score": {"pos": "Strong spatial/pattern recognition", "neg": "Weak spatial/pattern recognition"},
    "avg_game_score": {"pos": "Strong cognitive game performance", "neg": "Low cognitive game performance"},
    "daily_practice_count": {"pos": "Frequent daily practice", "neg": "Low daily practice frequency"},
    "weekly_practice_count": {"pos": "Good weekly practice volume", "neg": "Low weekly practice volume"},
    "practice_streak": {"pos": "Strong practice streak/consistency", "neg": "Inconsistent practice - streak is low"},
    "study_hours": {"pos": "Healthy daily study hours", "neg": "Low daily study hours"},
    "avg_response_time_sec": {"pos": "Fast, confident response times", "neg": "Slow response times suggest hesitation"},
    "wrong_answer_percent": {"pos": "Low wrong-answer rate", "neg": "High wrong-answer rate"},
    "avg_difficulty_solved": {"pos": "Solving high-difficulty questions correctly", "neg": "Struggling with higher-difficulty questions"},
    "improvement_trend": {"pos": "Clear improvement trend over time", "neg": "Little or negative improvement trend"},
    "consistency_score": {"pos": "Consistent performance across sessions", "neg": "Inconsistent performance across sessions"},
    "attendance_percent": {"pos": "Good attendance/engagement", "neg": "Low attendance/engagement"},
    "days_until_exam": {"pos": "Comfortable amount of time before the exam", "neg": "Limited time remaining before the exam"},
    "motivation_score": {"pos": "High self-reported motivation", "neg": "Low self-reported motivation"},
    "ai_coach_usage_count": {"pos": "Actively using the AI coach for support", "neg": "Rarely using the AI coach"},
    "question_completion_rate": {"pos": "High session-completion rate", "neg": "Frequently leaving sessions/tests incomplete"},
}


class PredictionRequest(BaseModel):
    placement_iq: float = 100
    current_iq: float = 100
    theta: float = 0.0
    avg_test_score: float = 50.0
    memory_score: float = 50.0
    logical_score: float = 50.0
    numerical_score: float = 50.0
    attention_score: float = 50.0
    spatial_score: float = 50.0
    avg_game_score: float = 0.0
    daily_practice_count: float = 0
    weekly_practice_count: float = 0
    practice_streak: float = 0
    study_hours: float = 0.0
    avg_response_time_sec: float = 15.0
    wrong_answer_percent: float = 50.0
    avg_difficulty_solved: float = 0.0
    improvement_trend: float = 0.0
    consistency_score: float = 50.0
    attendance_percent: float = 100.0
    days_until_exam: float = 90
    motivation_score: float = 5
    ai_coach_usage_count: float = 0
    question_completion_rate: float = 100.0


class Reason(BaseModel):
    feature: str
    message: str
    direction: str = Field(description='"positive" or "negative" contribution to readiness')
    impact: float


class PredictionResponse(BaseModel):
    readiness_percent: float
    readiness_label: str
    reasons: List[Reason]
    model_version: str


app = FastAPI(title="MindRise Exam Readiness Service")

_state = {}


@app.on_event("startup")
def load_artifacts():
    model_path = MODELS_DIR / "model.joblib"
    scaler_path = MODELS_DIR / "scaler.joblib"
    metadata_path = MODELS_DIR / "metadata.json"

    if not (model_path.exists() and scaler_path.exists() and metadata_path.exists()):
        # The service can still start (health check works) but /predict will
        # 503 until train_model.py has been run at least once.
        _state["ready"] = False
        return

    _state["model"] = joblib.load(model_path)
    _state["scaler"] = joblib.load(scaler_path)
    _state["metadata"] = json.loads(metadata_path.read_text())
    _state["explainer"] = shap.TreeExplainer(_state["model"])
    _state["ready"] = True


@app.get("/health")
def health():
    return {"status": "ok", "model_loaded": _state.get("ready", False)}


@app.get("/metadata")
def metadata():
    if not _state.get("ready"):
        raise HTTPException(503, "Model not trained yet - run train_model.py first.")
    return _state["metadata"]


def _ready_class_shap(x_scaled: np.ndarray) -> np.ndarray:
    ready_index = LABEL_ORDER.index("ready")
    shap_values = _state["explainer"].shap_values(x_scaled)

    if isinstance(shap_values, list):
        return shap_values[ready_index][0]
    if shap_values.ndim == 3:
        return shap_values[0, :, ready_index]
    return shap_values[0]


@app.post("/predict", response_model=PredictionResponse)
def predict(payload: PredictionRequest):
    if not _state.get("ready"):
        raise HTTPException(503, "Model not trained yet - run train_model.py first.")

    row = np.array([[getattr(payload, f) for f in FEATURE_ORDER]], dtype=float)
    x_scaled = _state["scaler"].transform(row)

    model = _state["model"]
    proba = model.predict_proba(x_scaled)[0]
    percent = float(sum(p * LABEL_MIDPOINT[LABEL_ORDER[i]] for i, p in enumerate(proba)))
    percent = round(max(1.0, min(99.0, percent)), 1)
    label = LABEL_ORDER[int(np.argmax(proba))]

    contributions = _ready_class_shap(x_scaled)
    ranked = sorted(zip(FEATURE_ORDER, contributions), key=lambda t: abs(t[1]), reverse=True)[:5]

    reasons = []
    for feature, impact in ranked:
        direction = "positive" if impact >= 0 else "negative"
        message = FEATURE_MESSAGES[feature]["pos" if impact >= 0 else "neg"]
        reasons.append(Reason(feature=feature, message=message, direction=direction, impact=round(float(impact), 4)))

    return PredictionResponse(
        readiness_percent=percent,
        readiness_label=label,
        reasons=reasons,
        model_version=_state["metadata"]["version"],
    )
