<?php

namespace App\Services\Analytics;

use App\Models\Category;
use App\Models\TestSession;
use App\Models\User;
use App\Models\UserProgressSnapshot;

/**
 * Builds a compact snapshot of a student's current standing (level, streak,
 * category accuracy, IQ estimate) for the AI coach to ground its answers in -
 * this is what makes the coach feel personalized without any model training:
 * every chat call reads the student's latest real data, not a cached profile.
 */
class StudentContextService
{
    public function __construct(private IqScoreService $iqScore, private StreakService $streak)
    {
    }

    public function build(User $user): array
    {
        $categories = Category::orderBy('name_en')->get();

        $categoryStrengths = $categories->map(function (Category $category) use ($user) {
            $latest = UserProgressSnapshot::where('user_id', $user->id)
                ->where('category_id', $category->id)
                ->orderByDesc('snapshot_date')
                ->first();

            return [
                'code' => $category->code,
                'name_en' => $category->name_en,
                'name_si' => $category->name_si,
                'accuracy_percent' => $latest ? (float) $latest->accuracy_percent : null,
            ];
        });

        $weakest = $categoryStrengths
            ->filter(fn ($c) => $c['accuracy_percent'] !== null)
            ->sortBy('accuracy_percent')
            ->first();

        $recentSessions = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get();

        return [
            'name' => $user->name,
            'has_placement' => (bool) $user->placement_completed_at,
            'level_number' => optional($user->currentLevel)->level_number,
            'level_name_en' => optional($user->currentLevel)->name_en,
            'level_name_si' => optional($user->currentLevel)->name_si,
            'streak_days' => $this->streak->calculate($user->id),
            'iq_estimate' => $this->iqScore->estimateFor($user),
            'category_strengths' => $categoryStrengths->values()->all(),
            'weakest_category' => $weakest,
            'recent_avg_score_percent' => $recentSessions->count()
                ? round((float) $recentSessions->avg('score_percent'), 1)
                : null,
            'sessions_completed' => TestSession::where('user_id', $user->id)->whereNotNull('completed_at')->count(),
        ];
    }
}
