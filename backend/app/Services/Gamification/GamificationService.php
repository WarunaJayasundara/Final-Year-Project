<?php

namespace App\Services\Gamification;

use App\Models\GameScore;
use App\Models\TestSession;
use App\Models\User;
use App\Models\XpLedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Core XP/coin economy: a transparent, documented rule set (not a hidden or
 * randomized reward schedule) so students can understand exactly why they
 * earned what they earned - important for a platform whose main claim is
 * measuring cognitive ability honestly, not manipulating engagement.
 *
 * Level curve: level n requires a cumulative xpForLevel(n) = 50*(n-1)*n XP
 * (a standard "triangular" game-leveling curve - level 2 needs 100 XP,
 * level 3 needs 300, level 4 needs 600, etc., so each level takes
 * proportionally more effort than the last).
 */
class GamificationService
{
    private const XP_BASE = 50;

    private const LEVEL_TITLES = [
        1 => 'Novice',
        2 => 'Learner',
        3 => 'Achiever',
        4 => 'Scholar',
        5 => 'Expert',
        6 => 'Specialist',
        7 => 'Master',
        8 => 'Grandmaster',
        9 => 'Champion',
        10 => 'Legend',
    ];

    /**
     * Rough per-game score ceilings used to normalize a raw game score to a
     * 0-100 scale for XP/coin sizing - kept in sync with (but independent
     * of) FeatureExtractionService::GAME_SCORE_SCALE, which uses the same
     * values for an unrelated purpose (ML feature normalization).
     */
    private const GAME_SCORE_SCALE = [
        'memory_match' => 1000,
        'sequence_puzzle' => 3000,
        'math_rush' => 600,
        'mental_rotation' => 1000,
        'selective_attention' => 1000,
    ];

    public const HIGH_SCORE_THRESHOLD_PERCENT = 85.0;

    public function award(User $user, int $xp, int $coins, string $reason): void
    {
        if ($xp === 0 && $coins === 0) {
            return;
        }

        DB::transaction(function () use ($user, $xp, $coins, $reason) {
            $user->increment('xp', $xp);
            $user->increment('coins', $coins);

            XpLedgerEntry::create([
                'user_id' => $user->id,
                'xp_amount' => $xp,
                'coin_amount' => $coins,
                'reason' => $reason,
            ]);
        });
    }

    public function xpForLevel(int $level): int
    {
        return self::XP_BASE * ($level - 1) * $level;
    }

    public function levelForXp(int $xp): int
    {
        $level = 1;
        while ($xp >= $this->xpForLevel($level + 1)) {
            $level++;
        }

        return $level;
    }

    public function levelTitle(int $level): string
    {
        return self::LEVEL_TITLES[$level] ?? "Level {$level}";
    }

    public function summary(User $user): array
    {
        $level = $this->levelForXp($user->xp);
        $currentLevelXp = $this->xpForLevel($level);
        $nextLevelXp = $this->xpForLevel($level + 1);
        $span = max(1, $nextLevelXp - $currentLevelXp);

        return [
            'xp' => $user->xp,
            'coins' => $user->coins,
            'level' => $level,
            'level_title' => $this->levelTitle($level),
            'xp_into_level' => $user->xp - $currentLevelXp,
            'xp_for_next_level' => $span,
            'progress_percent' => round((($user->xp - $currentLevelXp) / $span) * 100, 1),
        ];
    }

    /**
     * Session completion reward: a flat participation bonus plus a
     * performance-scaled component, with a larger one-time bonus for the
     * placement test (the platform's biggest early milestone).
     */
    public function sessionRewards(TestSession $session): array
    {
        $scorePercent = (float) $session->score_percent;
        $xp = 10 + (int) round($scorePercent * 0.5);
        $coins = (int) round($scorePercent / 10);

        if ($session->session_type === 'placement') {
            $xp += 100;
            $coins += 50;
        }

        return [$xp, $coins];
    }

    public function normalizedGameScorePercent(GameScore $gameScore): float
    {
        $code = $gameScore->game->code ?? '';
        $scale = self::GAME_SCORE_SCALE[$code] ?? 1000;

        return min(100, ($gameScore->score / $scale) * 100);
    }

    public function gameRewards(GameScore $gameScore): array
    {
        $normalized = $this->normalizedGameScorePercent($gameScore);
        $xp = (int) round($normalized * 0.3);
        $coins = (int) round($normalized * 0.1);

        return [$xp, $coins];
    }
}
