<?php

namespace App\Services\Gamification;

use App\Models\GameScore;
use App\Models\MissionClaim;
use App\Models\TestSession;
use App\Models\User;
use App\Services\Analytics\StreakService;
use Illuminate\Support\Carbon;

/**
 * Daily/weekly missions are defined in code (not stored) and evaluated live
 * from existing session/game data each time they're listed - only the
 * *claim* is persisted (mission_claims), both to prevent double-claiming
 * within a period and to record which periods a student has already
 * collected. This mirrors StudyPlanService's "transparent rules engine, not
 * a database of state to keep in sync" philosophy.
 */
class MissionService
{
    private const DAILY_QUESTIONS_TARGET = 15;

    private const WEEKLY_SESSIONS_TARGET = 5;

    private const WEEKLY_AVERAGE_TARGET = 80.0;

    private const WEEKLY_STREAK_TARGET = 7;

    public function __construct(private StreakService $streak)
    {
    }

    public function dailyPeriodKey(): string
    {
        return Carbon::now()->toDateString();
    }

    public function weeklyPeriodKey(): string
    {
        return Carbon::now()->format('o-\WW');
    }

    /** @return array<int, array<string, mixed>> */
    public function list(User $user): array
    {
        return array_merge($this->dailyMissions($user), $this->weeklyMissions($user));
    }

    public function claim(User $user, string $code): array
    {
        $mission = collect($this->list($user))->firstWhere('code', $code);

        if (! $mission) {
            throw new \InvalidArgumentException("Unknown mission: {$code}");
        }

        if (! $mission['completed']) {
            throw new \RuntimeException('Mission is not completed yet.');
        }

        if ($mission['claimed']) {
            throw new \RuntimeException('Mission already claimed for this period.');
        }

        MissionClaim::create([
            'user_id' => $user->id,
            'mission_code' => $mission['code'],
            'period_key' => $mission['period_key'],
            'xp_awarded' => $mission['xp_reward'],
            'coin_awarded' => $mission['coin_reward'],
            'claimed_at' => now(),
        ]);

        app(GamificationService::class)->award($user, $mission['xp_reward'], $mission['coin_reward'], "mission:{$code}");

        return $mission;
    }

    private function dailyMissions(User $user): array
    {
        $period = $this->dailyPeriodKey();
        $todayStart = Carbon::today();

        $sessionsToday = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $todayStart)
            ->count();

        $questionsToday = TestSession::where('user_id', $user->id)
            ->join('session_answers', 'session_answers.test_session_id', '=', 'test_sessions.id')
            ->where('session_answers.answered_at', '>=', $todayStart)
            ->count();

        $gamesToday = GameScore::where('user_id', $user->id)
            ->where('played_at', '>=', $todayStart)
            ->count();

        return [
            $this->build($user, 'daily_practice', 'daily', $period, min($sessionsToday, 1), 1, 15, 10),
            $this->build($user, 'daily_questions', 'daily', $period, min($questionsToday, self::DAILY_QUESTIONS_TARGET), self::DAILY_QUESTIONS_TARGET, 20, 10),
            $this->build($user, 'daily_game', 'daily', $period, min($gamesToday, 1), 1, 10, 5),
        ];
    }

    private function weeklyMissions(User $user): array
    {
        $period = $this->weeklyPeriodKey();
        $weekStart = Carbon::now()->startOfWeek();

        $sessionsThisWeek = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $weekStart)
            ->count();

        $avgScoreThisWeek = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $weekStart)
            ->avg('score_percent');

        $streakDays = $this->streak->calculate($user->id);

        return [
            $this->build($user, 'weekly_sessions', 'weekly', $period, min($sessionsThisWeek, self::WEEKLY_SESSIONS_TARGET), self::WEEKLY_SESSIONS_TARGET, 60, 30),
            $this->build(
                $user,
                'weekly_average',
                'weekly',
                $period,
                $sessionsThisWeek > 0 ? min((float) $avgScoreThisWeek, self::WEEKLY_AVERAGE_TARGET) : 0,
                self::WEEKLY_AVERAGE_TARGET,
                50,
                25
            ),
            $this->build($user, 'weekly_streak', 'weekly', $period, min($streakDays, self::WEEKLY_STREAK_TARGET), self::WEEKLY_STREAK_TARGET, 80, 40),
        ];
    }

    private function build(User $user, string $code, string $type, string $period, float $progress, float $target, int $xpReward, int $coinReward): array
    {
        $claimed = MissionClaim::where('user_id', $user->id)
            ->where('mission_code', $code)
            ->where('period_key', $period)
            ->exists();

        return [
            'code' => $code,
            'type' => $type,
            'period_key' => $period,
            'progress' => $progress,
            'target' => $target,
            'completed' => $progress >= $target,
            'claimed' => $claimed,
            'xp_reward' => $xpReward,
            'coin_reward' => $coinReward,
        ];
    }
}
