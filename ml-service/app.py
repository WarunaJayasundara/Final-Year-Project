"""
FastAPI inference microservice for the exam-readiness model. Laravel's
ReadinessPredictionService calls this over HTTP (same swappable-service
pattern already used for the Gemini AI feedback integration) rather than
Laravel reimplementing gradient boosting, so training and inference always
use the exact same scikit-learn/XGBoost/LightGBM/CatBoost/SHAP code path.

Research-grade upgrade (see docs/ML_RESEARCH_METHODOLOGY.md): the feature
vector grew from 24 to 42 (18 new advanced behavioural features, all with
documented defaults so a caller that only knows the original 24 - e.g.
Laravel before its own upgrade lands - still gets a valid response). The
/predict response's ORIGINAL fields (readiness_percent, readiness_label,
reasons, model_version) are unchanged in shape; new fields are additive.

Run:
    uvicorn app:app --host 127.0.0.1 --port 8100
"""
import json
from pathlib import Path
from typing import Dict, List, Optional

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

ADVANCED_FEATURE_ORDER = [
    "rolling_avg_score", "weekly_trend", "monthly_trend", "learning_velocity",
    "knowledge_gain_rate", "consistency_index", "fatigue_score", "retention_score",
    "engagement_score", "practice_intensity", "error_recovery_rate", "category_mastery",
    "confidence_trend", "reaction_speed_trend", "adaptive_learning_gain",
    "difficulty_progression", "question_diversity_score", "time_management_score",
    "revision_frequency",
]

FULL_FEATURE_ORDER = FEATURE_ORDER + ADVANCED_FEATURE_ORDER

MULTIOUTPUT_FEATURES = ["avg_test_score", "weekly_practice_count", "question_completion_rate", "engagement_score", "practice_intensity"]

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
    "rolling_avg_score": {"pos": "Recent scores trending high", "neg": "Recent scores trending low"},
    "weekly_trend": {"pos": "Scores improving week over week", "neg": "Scores declining week over week"},
    "monthly_trend": {"pos": "Scores improving over the past month", "neg": "Scores declining over the past month"},
    "learning_velocity": {"pos": "Ability increasing at a healthy pace", "neg": "Ability growth has stalled"},
    "knowledge_gain_rate": {"pos": "Strong score gains per practice session", "neg": "Little score gain per practice session"},
    "consistency_index": {"pos": "Low variability across sessions", "neg": "High variability across sessions"},
    "fatigue_score": {"pos": "Little within-session fatigue", "neg": "Accuracy drops noticeably within sessions (fatigue)"},
    "retention_score": {"pos": "Strong retention of previously learned material", "neg": "Weak retention of previously learned material"},
    "engagement_score": {"pos": "High overall engagement", "neg": "Low overall engagement"},
    "practice_intensity": {"pos": "Practice volume above the recommended target", "neg": "Practice volume below the recommended target"},
    "error_recovery_rate": {"pos": "Good bounce-back after a wrong answer", "neg": "Wrong answers tend to cascade into more mistakes"},
    "category_mastery": {"pos": "Strong mastery within practiced categories", "neg": "Limited mastery within practiced categories"},
    "confidence_trend": {"pos": "Growing confidence (faster, more accurate)", "neg": "Confidence not improving"},
    "reaction_speed_trend": {"pos": "Getting faster over time", "neg": "Not getting any faster over time"},
    "adaptive_learning_gain": {"pos": "Daily sessions are driving real ability gains", "neg": "Daily sessions show little ability gain"},
    "difficulty_progression": {"pos": "Successfully tackling harder items over time", "neg": "Not progressing to harder items"},
    "question_diversity_score": {"pos": "Practicing a wide range of subtopics", "neg": "Practice is narrowly focused on few subtopics"},
    "time_management_score": {"pos": "Good session time management", "neg": "Sessions often over/under target duration"},
    "revision_frequency": {"pos": "Regularly revisiting earlier material", "neg": "Rarely revisiting earlier material"},
}

FEATURE_LABELS: Dict[str, str] = {
    "weekly_practice_count": "weekly practice volume", "avg_test_score": "average test score",
    "numerical_score": "numerical reasoning score", "logical_score": "logical reasoning score",
    "memory_score": "memory score", "attention_score": "attention score", "spatial_score": "spatial reasoning score",
    "consistency_score": "study consistency", "consistency_index": "study consistency",
    "study_hours": "daily study hours", "practice_streak": "practice streak",
    "engagement_score": "overall engagement", "fatigue_score": "session fatigue",
    "retention_score": "knowledge retention", "learning_velocity": "learning speed",
    "revision_frequency": "revision frequency",
}


class PredictionRequest(BaseModel):
    # --- original 24 (unchanged defaults, unchanged names) ---
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
    # --- 18 new advanced features (all default to a neutral value so an
    # old caller sending only the original 24 still gets a valid response) ---
    rolling_avg_score: float = 50.0
    weekly_trend: float = 0.0
    monthly_trend: float = 0.0
    learning_velocity: float = 0.0
    knowledge_gain_rate: float = 0.0
    consistency_index: float = 50.0
    fatigue_score: float = 0.0
    retention_score: float = 50.0
    engagement_score: float = 50.0
    practice_intensity: float = 100.0
    error_recovery_rate: float = 50.0
    category_mastery: float = 50.0
    confidence_trend: float = 0.0
    reaction_speed_trend: float = 0.0
    adaptive_learning_gain: float = 0.0
    difficulty_progression: float = 0.0
    question_diversity_score: float = 50.0
    time_management_score: float = 50.0
    revision_frequency: float = 0.0
    # Optional snapshot of the PREVIOUS prediction's feature vector (Laravel
    # already stores this in exam_readiness_predictions.features) - enables
    # the trend-aware plain-English explanation ("your X dropped by Y%")
    # instead of a static one when this student has a prediction history.
    previous_features: Optional[Dict[str, float]] = None
    # Optional time-aware signals (not yet part of FULL_FEATURE_ORDER / the
    # live model's input contract - see FeatureExtractionService::
    # TIME_AWARE_FEATURE_ORDER's docblock for why the cutover is deliberately
    # deferred to a promoted model). When present, only used to derive the
    # rule-based time_management_readiness_percent below - never fed into
    # the classifier itself, so this is safe to send even against the
    # currently-deployed model.
    exam_pace_gap: Optional[float] = None
    time_efficiency_score: Optional[float] = None


class Reason(BaseModel):
    feature: str
    message: str
    direction: str = Field(description='"positive" or "negative" contribution to readiness')
    impact: float
    pct_change_since_last: Optional[float] = None


class RiskPrediction(BaseModel):
    probability: float
    at_risk: bool


class ScoreRange(BaseModel):
    low: float
    high: float


class PredictionResponse(BaseModel):
    readiness_percent: float
    readiness_label: str
    reasons: List[Reason]
    model_version: str
    plain_english_explanation: str
    # This is a MODEL ESTIMATE from a partly-synthetic-trained classifier
    # (see ML_RESEARCH_METHODOLOGY.md's threats-to-validity section), not a
    # verified real-world pass probability for any specific examination -
    # the brief this was built for explicitly requires this distinction be
    # stated, not just implied by documentation nobody reads at inference time.
    prediction_confidence_note: str = (
        "This is a research-grade model estimate based on your practice history, "
        "not a guaranteed or verified prediction of your actual exam outcome."
    )
    # Additive multi-output fields - null if the multi-output models haven't
    # been trained yet (train_multioutput.py), so this response shape is
    # still valid before that step ever runs.
    risk_of_dropping_practice: Optional[RiskPrediction] = None
    predicted_next_assessment_score: Optional[float] = None
    predicted_score_change: Optional[float] = None
    # +/- the next-assessment-score model's held-out RMSE (models/
    # multioutput_metadata.json) - a rough calibrated band around the point
    # estimate, not a formal statistical prediction interval.
    predicted_score_range: Optional[ScoreRange] = None
    # Rule-based (not a trained sub-model - see FeatureExtractionService::
    # TIME_AWARE_FEATURE_ORDER's docblock on why this avoids overclaiming a
    # new supervised output): null unless the caller sends exam_pace_gap AND
    # time_efficiency_score.
    time_management_readiness_percent: Optional[float] = None


app = FastAPI(title="MindRise Exam Readiness Service")

_state = {}


def _try_load(path: Path):
    return joblib.load(path) if path.exists() else None


@app.on_event("startup")
def load_artifacts():
    model_path = MODELS_DIR / "model.joblib"
    scaler_path = MODELS_DIR / "scaler.joblib"
    metadata_path = MODELS_DIR / "metadata.json"
    if not metadata_path.exists():
        metadata_path = MODELS_DIR / "model_comparison_report.json"

    if not (model_path.exists() and scaler_path.exists() and metadata_path.exists()):
        _state["ready"] = False
        return

    _state["model"] = joblib.load(model_path)
    _state["scaler"] = joblib.load(scaler_path)
    _state["metadata"] = json.loads(metadata_path.read_text())
    try:
        _state["explainer"] = shap.TreeExplainer(_state["model"])
        _state["shap_supported"] = True
    except Exception:  # noqa: BLE001 - SVM/MLP aren't tree models; SHAP falls back to a no-explanation mode
        _state["shap_supported"] = False
    _state["ready"] = True

    _state["risk_model"] = _try_load(MODELS_DIR / "risk_model.joblib")
    _state["next_score_model"] = _try_load(MODELS_DIR / "next_score_model.joblib")
    _state["score_change_model"] = _try_load(MODELS_DIR / "score_change_model.joblib")
    _state["multioutput_scaler"] = _try_load(MODELS_DIR / "multioutput_scaler.joblib")

    multioutput_metadata_path = MODELS_DIR / "multioutput_metadata.json"
    _state["next_score_rmse"] = None
    if multioutput_metadata_path.exists():
        mo_meta = json.loads(multioutput_metadata_path.read_text())
        _state["next_score_rmse"] = mo_meta.get("targets", {}).get("next_assessment_score", {}).get("rmse")


@app.get("/health")
def health():
    return {"status": "ok", "model_loaded": _state.get("ready", False)}


@app.get("/metadata")
def metadata():
    if not _state.get("ready"):
        raise HTTPException(503, "Model not trained yet - run model_comparison.py first.")
    return _state["metadata"]


@app.get("/models")
def models():
    """Lists every registered model version (see model_registry.py) for the admin dashboard."""
    registry_path = MODELS_DIR / "registry.json"
    if not registry_path.exists():
        return {"versions": [], "live_version": None}
    return json.loads(registry_path.read_text())


@app.get("/evaluation-report")
def evaluation_report():
    path = MODELS_DIR / "evaluation_report.json"
    if not path.exists():
        raise HTTPException(404, "No evaluation report yet - run evaluate.py.")
    return json.loads(path.read_text())


@app.get("/explainability-report")
def explainability_report():
    path = MODELS_DIR / "explainability_report.json"
    if not path.exists():
        raise HTTPException(404, "No explainability report yet - run explain.py.")
    return json.loads(path.read_text())


class DuplicateCheckRequest(BaseModel):
    new_text: str
    candidate_texts: List[str] = []
    threshold: float = 0.75


class DuplicateCheckResponse(BaseModel):
    is_duplicate: bool
    max_similarity: float
    most_similar_index: Optional[int] = None


@app.post("/duplicate-check", response_model=DuplicateCheckResponse)
def duplicate_check(payload: DuplicateCheckRequest):
    """
    Semantic/embedding-style duplicate detection for the question-bank
    generation pipeline (QuestionDraftService), complementing - not
    replacing - Laravel's existing Jaccard word-overlap check. Uses TF-IDF
    + cosine similarity (scikit-learn, already a training dependency here)
    rather than a heavy sentence-transformer model: this is a real semantic-
    similarity signal (weights rare/distinctive words more than common ones,
    unlike raw Jaccard overlap), not a claim of deep-learning-grade
    embeddings - documented as such in ML_RESEARCH_METHODOLOGY.md.
    """
    if not payload.candidate_texts:
        return DuplicateCheckResponse(is_duplicate=False, max_similarity=0.0, most_similar_index=None)

    from sklearn.feature_extraction.text import TfidfVectorizer
    from sklearn.metrics.pairwise import cosine_similarity

    corpus = [payload.new_text] + payload.candidate_texts
    try:
        vectorizer = TfidfVectorizer().fit(corpus)
        vectors = vectorizer.transform(corpus)
    except ValueError:
        # Empty vocabulary (e.g. all-stopword or all-numeric text) - TF-IDF
        # can't score it, so fall back to "not a duplicate" and let the
        # Jaccard check (which handles this case fine) be the deciding signal.
        return DuplicateCheckResponse(is_duplicate=False, max_similarity=0.0, most_similar_index=None)

    similarities = cosine_similarity(vectors[0:1], vectors[1:])[0]
    max_index = int(np.argmax(similarities))
    max_similarity = float(similarities[max_index])

    return DuplicateCheckResponse(
        is_duplicate=max_similarity >= payload.threshold,
        max_similarity=max_similarity,
        most_similar_index=max_index,
    )


def _ready_class_shap(x_scaled: np.ndarray) -> np.ndarray:
    ready_index = LABEL_ORDER.index("ready")
    shap_values = _state["explainer"].shap_values(x_scaled)

    if isinstance(shap_values, list):
        return shap_values[ready_index][0]
    if shap_values.ndim == 3:
        return shap_values[0, :, ready_index]
    return shap_values[0]


def _plain_english(ranked: list, payload: PredictionRequest) -> str:
    clauses = []
    for feature, impact in ranked[:3]:
        label = FEATURE_LABELS.get(feature, feature.replace("_", " "))
        current = getattr(payload, feature)
        previous = payload.previous_features.get(feature) if payload.previous_features else None

        if previous is not None and previous != 0:
            pct_change = (current - previous) / abs(previous) * 100
            if pct_change <= -10:
                clauses.append(f"your {label} dropped by {abs(pct_change):.0f}%")
                continue
            if pct_change >= 10:
                clauses.append(f"your {label} increased by {pct_change:.0f}%")
                continue

        clauses.append(f"your {label} was {'a strength' if impact >= 0 else 'a weak point'}")

    if not clauses:
        return "Not enough data yet to explain this prediction's biggest drivers."
    if len(clauses) == 1:
        return f"Your readiness estimate reflects that {clauses[0]}."
    return f"Your readiness estimate changed because {', '.join(clauses[:-1])}, and {clauses[-1]}."


def _time_management_readiness(payload: PredictionRequest) -> Optional[float]:
    """
    Rule-based, not a trained output (see PredictionResponse's docstring
    note on this field): starts from time_efficiency_score (already a 0-100
    "share of answers within expected time" measure) and, if the student has
    a real exam's pace target, subtracts a documented penalty for every
    second they're currently running behind that target pace. A positive
    exam_pace_gap (ahead of pace) applies no bonus - full credit already
    comes from time_efficiency_score itself.
    """
    if payload.time_efficiency_score is None:
        return None
    if payload.exam_pace_gap is None:
        return round(max(0.0, min(100.0, payload.time_efficiency_score)), 1)

    behind_pace_seconds = max(0.0, -payload.exam_pace_gap)
    penalty = behind_pace_seconds * 0.5
    return round(max(0.0, min(100.0, payload.time_efficiency_score - penalty)), 1)


@app.post("/predict", response_model=PredictionResponse)
def predict(payload: PredictionRequest):
    if not _state.get("ready"):
        raise HTTPException(503, "Model not trained yet - run model_comparison.py first.")

    row = np.array([[getattr(payload, f) for f in FULL_FEATURE_ORDER]], dtype=float)
    x_scaled = _state["scaler"].transform(row)

    model = _state["model"]
    proba = model.predict_proba(x_scaled)[0]
    percent = float(sum(p * LABEL_MIDPOINT[LABEL_ORDER[i]] for i, p in enumerate(proba)))
    percent = round(max(1.0, min(99.0, percent)), 1)
    label = LABEL_ORDER[int(np.argmax(proba))]

    reasons = []
    ranked = []
    if _state.get("shap_supported"):
        contributions = _ready_class_shap(x_scaled)
        ranked = sorted(zip(FULL_FEATURE_ORDER, contributions), key=lambda t: abs(t[1]), reverse=True)[:5]
        for feature, impact in ranked:
            direction = "positive" if impact >= 0 else "negative"
            message = FEATURE_MESSAGES.get(feature, {}).get("pos" if impact >= 0 else "neg", feature)
            pct_change = None
            if payload.previous_features and feature in payload.previous_features:
                prev_val = payload.previous_features[feature]
                current_val = getattr(payload, feature)
                if prev_val:
                    pct_change = round((current_val - prev_val) / abs(prev_val) * 100, 1)
            reasons.append(Reason(feature=feature, message=message, direction=direction, impact=round(float(impact), 4), pct_change_since_last=pct_change))

    plain_english = _plain_english(ranked, payload) if ranked else "Keep practicing to build up enough history for a detailed explanation."

    risk = None
    next_score = None
    score_change = None
    score_range = None
    if _state.get("risk_model") is not None and _state.get("multioutput_scaler") is not None:
        mo_row = np.array([[getattr(payload, f) for f in MULTIOUTPUT_FEATURES]], dtype=float)
        mo_scaled = _state["multioutput_scaler"].transform(mo_row)
        risk_proba = float(_state["risk_model"].predict_proba(mo_scaled)[0][1])
        risk = RiskPrediction(probability=round(risk_proba, 3), at_risk=risk_proba >= 0.5)
        if _state.get("next_score_model") is not None:
            next_score = round(float(_state["next_score_model"].predict(mo_scaled)[0]), 1)
            if _state.get("next_score_rmse") is not None:
                rmse = _state["next_score_rmse"]
                score_range = ScoreRange(
                    low=round(max(0.0, next_score - rmse), 1),
                    high=round(min(100.0, next_score + rmse), 1),
                )
        if _state.get("score_change_model") is not None:
            score_change = round(float(_state["score_change_model"].predict(mo_scaled)[0]), 1)

    return PredictionResponse(
        readiness_percent=percent,
        readiness_label=label,
        reasons=reasons,
        model_version=_state["metadata"].get("version", "unknown"),
        plain_english_explanation=plain_english,
        risk_of_dropping_practice=risk,
        predicted_next_assessment_score=next_score,
        predicted_score_change=score_change,
        predicted_score_range=score_range,
        time_management_readiness_percent=_time_management_readiness(payload),
    )
