<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GameScore;
use App\Models\TestSession;
use App\Models\UserProgressSnapshot;
use App\Services\Analytics\IqScoreService;
use App\Services\Analytics\StreakService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private IqScoreService $iqScore, private StreakService $streak)
    {
    }

    public function summary(Request $request)
    {
        $user = $request->user();

        $categories = Category::orderBy('name_en')->get();
        $categoryStrengths = $categories->map(function (Category $category) use ($user) {
            $latest = UserProgressSnapshot::where('user_id', $user->id)
                ->where('category_id', $category->id)
                ->orderByDesc('snapshot_date')
                ->first();

            return [
                'category_id' => $category->id,
                'code' => $category->code,
                'name_en' => $category->name_en,
                'name_si' => $category->name_si,
                'accuracy_percent' => $latest ? (float) $latest->accuracy_percent : null,
            ];
        });

        $recentSessions = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->with('category')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get()
            ->map(fn (TestSession $session) => [
                'id' => $session->id,
                'session_type' => $session->session_type,
                'category_name' => optional($session->category)->name_en,
                'score_percent' => (float) $session->score_percent,
                'completed_at' => $session->completed_at,
            ]);

        $gameScores = GameScore::where('user_id', $user->id)
            ->with('game')
            ->get()
            ->groupBy('game_id')
            ->map(fn ($scores) => [
                'game_code' => optional($scores->first()->game)->code,
                'game_name' => optional($scores->first()->game)->name_en,
                'best_score' => $scores->max('score'),
                'plays' => $scores->count(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'current_level' => $user->currentLevel,
                'placement_completed_at' => $user->placement_completed_at,
                'streak_days' => $this->streak->calculate($user->id),
                'category_strengths' => $categoryStrengths,
                'recent_sessions' => $recentSessions,
                'game_scores' => $gameScores,
                'iq_estimate' => $this->iqScore->estimateFor($user),
            ],
        ]);
    }

    public function progressHistory(Request $request)
    {
        $user = $request->user();

        $levelHistory = TestSession::where('user_id', $user->id)
            ->whereIn('session_type', ['placement', 'daily'])
            ->whereNotNull('completed_at')
            ->whereNotNull('level_after_id')
            ->with('levelAfter')
            ->orderBy('completed_at')
            ->get()
            ->map(fn (TestSession $session) => [
                'date' => $session->completed_at->toDateString(),
                'level_number' => optional($session->levelAfter)->level_number,
            ]);

        $accuracyHistory = UserProgressSnapshot::where('user_id', $user->id)
            ->whereNull('category_id')
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn (UserProgressSnapshot $snapshot) => [
                'date' => $snapshot->snapshot_date->toDateString(),
                'accuracy_percent' => (float) $snapshot->accuracy_percent,
            ]);

        return response()->json([
            'data' => [
                'level_history' => $levelHistory,
                'accuracy_history' => $accuracyHistory,
            ],
        ]);
    }
}
