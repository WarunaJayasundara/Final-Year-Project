<?php

namespace App\Services\Gamification;

use App\Models\Badge;
use App\Models\ExamReadinessPrediction;
use App\Models\Game;
use App\Models\GameScore;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\Analytics\StreakService;

/**
 * Evaluates the fixed badge catalog (seeded by BadgeSeeder) against a
 * user's current stats and awards any newly-earned ones. Called after any
 * action that could plausibly unlock a badge (session completion, game
 * score submission, exam-profile setup, readiness prediction) - cheap to
 * over-call since already-earned badges are skipped via the unique
 * (user_id, badge_id) constraint check up front.
 */
class BadgeService
{
    public function __construct(private GamificationService $gamification, private StreakService $streak)
    {
    }

    /** @return Badge[] newly-awarded badges (empty if none) */
    public function evaluate(User $user): array
    {
        $earnedCodes = Badge::whereHas('userBadges', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('code')
            ->all();

        $checks = $this->checks($user);
        $newlyEarned = [];

        foreach ($checks as $code => $isMet) {
            if (in_array($code, $earnedCodes, true)) {
                continue;
            }

            if (! $isMet()) {
                continue;
            }

            $badge = Badge::where('code', $code)->first();
            if (! $badge) {
                continue;
            }

            UserBadge::create(['user_id' => $user->id, 'badge_id' => $badge->id, 'earned_at' => now()]);
            $this->gamification->award($user, $badge->xp_reward, $badge->coin_reward, "badge:{$code}");
            $newlyEarned[] = $badge;
        }

        return $newlyEarned;
    }

    /** @return array<string, callable(): bool> */
    private function checks(User $user): array
    {
        return [
            'first_placement' => fn () => $user->placement_completed_at !== null,
            'streak_3' => fn () => $this->streak->calculate($user->id) >= 3,
            'streak_7' => fn () => $this->streak->calculate($user->id) >= 7,
            'streak_14' => fn () => $this->streak->calculate($user->id) >= 14,
            'streak_30' => fn () => $this->streak->calculate($user->id) >= 30,
            'perfect_score' => fn () => TestSession::where('user_id', $user->id)
                ->where('score_percent', 100)
                ->exists(),
            'questions_100' => fn () => $this->totalAnswered($user) >= 100,
            'questions_500' => fn () => $this->totalAnswered($user) >= 500,
            'level_3_reached' => fn () => (int) optional($user->currentLevel)->level_number >= 3,
            'level_5_reached' => fn () => (int) optional($user->currentLevel)->level_number >= 5,
            'game_explorer' => fn () => $this->distinctGamesPlayed($user) >= max(1, Game::count()),
            'high_scorer' => fn () => $this->hasHighScore($user),
            'exam_ready' => fn () => ExamReadinessPrediction::where('user_id', $user->id)
                ->where('readiness_label', 'ready')
                ->exists(),
            'study_planner' => fn () => $user->examProfile !== null,
        ];
    }

    private function totalAnswered(User $user): int
    {
        return SessionAnswer::whereHas('session', fn ($q) => $q->where('user_id', $user->id))
            ->whereNotNull('answered_at')
            ->count();
    }

    private function distinctGamesPlayed(User $user): int
    {
        return GameScore::where('user_id', $user->id)->distinct('game_id')->count('game_id');
    }

    private function hasHighScore(User $user): bool
    {
        return GameScore::where('user_id', $user->id)
            ->with('game')
            ->get()
            ->contains(fn (GameScore $score) => $this->gamification->normalizedGameScorePercent($score) >= GamificationService::HIGH_SCORE_THRESHOLD_PERCENT);
    }
}
