"""
Single source of truth for the latent-trait structural model that generates
MindRise's platform-only features (IRT theta, per-category cognitive scores,
game performance, AI-coach usage, response time, difficulty solved,
question-completion rate) from three latent traits: theta (ability),
motivation, and consistency.

Why this exists as its own module: both the pure-synthetic generator
(generate_dataset.py, unchanged in its public behaviour) and the hybrid
pipeline's real-data augmentation (feature_mapping.py) need to produce
these platform-only fields from a *single, consistent* generative process -
real students get a theta/motivation/consistency triple *derived from their
real outcome data* (see feature_mapping.py) rather than drawn from thin air,
then the exact same equations used here turn that triple into cognitive
scores/game performance/etc., so a "real" row and a "synthetic" row with the
same underlying theta are structurally comparable. Keeping the equations in
one module (instead of copy-pasted into two files) guarantees that
consistency mechanically rather than by convention.

All functions are pure and vectorized (numpy arrays in, numpy arrays out).
"""
import numpy as np


def clip(arr, lo, hi):
    return np.clip(arr, lo, hi)


def iq_from_theta(theta: np.ndarray, rng: np.random.Generator, noise_sd: float = 3.0) -> np.ndarray:
    return clip(100 + 15 * theta + rng.normal(0, noise_sd, len(theta)), 40, 160)


def category_score(theta: np.ndarray, rng: np.random.Generator, bias_scale: float = 15) -> np.ndarray:
    n = len(theta)
    bias = rng.normal(0, 1, n)
    return clip(50 + 15 * theta + bias_scale * bias * 0.4 + rng.normal(0, 6, n), 0, 100)


def game_score(theta: np.ndarray, consistency_latent: np.ndarray, rng: np.random.Generator) -> np.ndarray:
    return clip(40 + 10 * theta + 8 * consistency_latent + rng.normal(0, 12, len(theta)), 0, 100)


def response_time_sec(theta: np.ndarray, rng: np.random.Generator) -> np.ndarray:
    return clip(18 - 3 * theta + rng.normal(0, 4, len(theta)), 4, 60)


def difficulty_solved(theta: np.ndarray, rng: np.random.Generator) -> np.ndarray:
    return clip(theta * 0.8 + rng.normal(0, 0.5, len(theta)), -3, 3)


def ai_coach_usage(motivation_latent: np.ndarray, rng: np.random.Generator) -> np.ndarray:
    return rng.poisson(clip(1.5 + 0.5 * motivation_latent, 0.05, None))


def question_completion_rate(motivation_latent: np.ndarray, rng: np.random.Generator) -> np.ndarray:
    return clip(85 + 8 * motivation_latent + rng.normal(0, 6, len(motivation_latent)), 30, 100)


def motivation_score_1to10(motivation_latent: np.ndarray, rng: np.random.Generator) -> np.ndarray:
    return np.rint(clip(5.5 + 1.8 * motivation_latent + rng.normal(0, 0.8, len(motivation_latent)), 1, 10))


def practice_counts(motivation_latent: np.ndarray, consistency_latent: np.ndarray, rng: np.random.Generator):
    """Returns (daily_practice_count, weekly_practice_count, practice_streak)."""
    intensity = clip(0.6 * motivation_latent + 0.4 * consistency_latent, -3, 3)
    daily = rng.poisson(clip(2 + intensity, 0.1, None))
    weekly = daily + rng.poisson(clip(4 + intensity * 1.5, 0.1, None))
    streak = rng.poisson(clip(3 + intensity * 2, 0.1, None))
    return daily, weekly, streak
