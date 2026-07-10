<?php

namespace App\Services\Analytics;

use App\Models\GameScore;
use App\Models\TestSession;
use Illuminate\Support\Carbon;

/**
 * Consecutive-day activity streak, derived from distinct completed-session and
 * game-play dates (no stored streak column - see the plan's "Streaks are
 * computed, not stored" decision). Shared by the dashboard summary and the AI
 * coach's student-context builder.
 */
class StreakService
{
    public function calculate(int $userId): int
    {
        $sessionDates = TestSession::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->pluck('completed_at')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());

        $gameDates = GameScore::where('user_id', $userId)
            ->pluck('played_at')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());

        $activeDates = $sessionDates->merge($gameDates)->unique()->sort()->values();

        if ($activeDates->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $cursor = Carbon::today();

        // Walk backwards from today; the streak breaks the first day with no activity
        // (today itself is allowed to be "not yet done" without breaking a streak).
        while (true) {
            $dateString = $cursor->toDateString();
            if ($activeDates->contains($dateString)) {
                $streak++;
                $cursor = $cursor->subDay();
                continue;
            }

            if ($dateString === Carbon::today()->toDateString()) {
                $cursor = $cursor->subDay();
                continue;
            }

            break;
        }

        return $streak;
    }
}
