<?php

namespace App\Http\Controllers;

use App\Models\ExamReadinessPrediction;
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
            'data' => $this->present($prediction),
            'new_badges' => array_map(fn ($b) => $b->toRewardArray(), $newBadges),
        ]);
    }

    public function latest(Request $request)
    {
        $prediction = ExamReadinessPrediction::where('user_id', $request->user()->id)
            ->orderByDesc('predicted_at')
            ->first();

        return response()->json(['data' => $prediction ? $this->present($prediction) : null]);
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

    private function present(ExamReadinessPrediction $prediction): array
    {
        return [
            'readiness_percent' => (float) $prediction->readiness_percent,
            'readiness_label' => $prediction->readiness_label,
            'reasons' => $prediction->reasons,
            'model_version' => $prediction->model_version,
            'predicted_at' => $prediction->predicted_at,
        ];
    }
}
