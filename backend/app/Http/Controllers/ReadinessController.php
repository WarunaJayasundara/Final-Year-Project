<?php

namespace App\Http\Controllers;

use App\Models\ExamReadinessPrediction;
use App\Models\User;
use App\Services\Gamification\BadgeService;
use App\Services\Ml\ReadinessPredictionService;
use Illuminate\Http\Request;

class ReadinessController extends Controller
{
    public function __construct(private ReadinessPredictionService $predictions, private BadgeService $badges)
    {
    }

    public function predict(Request $request)
    {
        $user = $request->user();

        try {
            $prediction = $this->predictions->predictFor($user);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'The exam readiness service is temporarily unavailable. Please try again shortly.',
            ], 503);
        }

        $newBadges = $this->badges->evaluate($user->fresh());

        return response()->json([
            'data' => $this->present($prediction, $user),
            'new_badges' => array_map(fn ($b) => $b->toRewardArray(), $newBadges),
        ]);
    }

    public function latest(Request $request)
    {
        $prediction = ExamReadinessPrediction::where('user_id', $request->user()->id)
            ->orderByDesc('predicted_at')
            ->first();

        return response()->json(['data' => $prediction ? $this->present($prediction, $request->user()) : null]);
    }

    public function history(Request $request)
    {
        $predictions = ExamReadinessPrediction::where('user_id', $request->user()->id)
            ->orderBy('predicted_at')
            ->limit(30)
            ->get()
            ->map(fn (ExamReadinessPrediction $p) => [
                'predicted_at' => $p->predicted_at,
                'readiness_percent' => (float) $p->readiness_percent,
                'readiness_label' => $p->readiness_label,
            ]);

        return response()->json(['data' => $predictions]);
    }

    /**
     * "readiness_type" is a presentation-layer distinction only (derived
     * from the CURRENT exam profile, not stored per-prediction): without an
     * active exam to be "ready" for, the same model output is shown as a
     * general cognitive-training indicator instead of implying a pass
     * probability for a named exam.
     */
    private function present(ExamReadinessPrediction $prediction, ?User $user = null): array
    {
        $examProfile = $user?->examProfile()->first();
        $hasActiveExam = $examProfile && $examProfile->exam_date && ! $examProfile->isPastDue();

        return [
            'readiness_percent' => (float) $prediction->readiness_percent,
            'readiness_label' => $prediction->readiness_label,
            'readiness_type' => $hasActiveExam ? 'exam_specific' : 'general',
            'exam_name' => $hasActiveExam ? $examProfile->exam_name : null,
            'reasons' => $prediction->reasons,
            'model_version' => $prediction->model_version,
            'predicted_at' => $prediction->predicted_at,
            'plain_english_explanation' => $prediction->plain_english_explanation,
            'risk_of_dropping_practice' => $prediction->risk_of_dropping_practice_probability !== null ? [
                'probability' => (float) $prediction->risk_of_dropping_practice_probability,
                'at_risk' => (bool) $prediction->at_risk_of_dropping_practice,
            ] : null,
            'predicted_next_assessment_score' => $prediction->predicted_next_assessment_score !== null
                ? (float) $prediction->predicted_next_assessment_score : null,
            'predicted_score_change' => $prediction->predicted_score_change !== null
                ? (float) $prediction->predicted_score_change : null,
            'time_management_readiness_percent' => $prediction->time_management_readiness_percent !== null
                ? (float) $prediction->time_management_readiness_percent : null,
            'predicted_score_range' => $prediction->predicted_score_range,
        ];
    }
}
