<?php

namespace App\Services\Leveling;

use App\Models\IqLevel;
use App\Models\TestSession;
use App\Services\Irt\AbilityEstimationService;

/**
 * Maps a student's Rasch-model ability estimate (theta, a latent trait on a
 * roughly standard-normal logit scale after PROX calibration) onto the
 * platform's 5 authored levels, and updates the student's running theta after
 * every placement/daily session. Practice sessions never change level or
 * theta (they're user-chosen category drills, not adaptively targeted
 * evidence about ability).
 *
 * Cutpoints are chosen to line up exactly with IqScoreService::classify()'s
 * deviation-IQ bands (IQ = 100 + 15*theta), so a student's platform level
 * and their IQ classification always agree instead of being two
 * independently-banded views of the same theta:
 *   Level 1 (theta < -2.0)        <-> IQ < 70   "Extremely Low"
 *   Level 2 (-2.0 <= theta < -1.0) <-> IQ 70-84  "Below Average"
 *   Level 3 (-1.0 <= theta < 1.0)  <-> IQ 85-114 "Average"
 *   Level 4 (1.0 <= theta < 2.0)   <-> IQ 115-129 "Above Average"
 *   Level 5 (theta >= 2.0)         <-> IQ >= 130 "Gifted"
 * Previously used a different, narrower banding (+/-0.5 and +/-1.5 logits)
 * that didn't correspond to the IQ bands at all - e.g. a student could be
 * "Level 5 - Expert" while showing as "Above Average" (not "Gifted") on
 * their dashboard, which read as two systems disagreeing about the same
 * ability estimate.
 */
class LevelAdjustmentService
{
    private const CUTPOINT_1_2 = -2.0;

    private const CUTPOINT_2_3 = -1.0;

    private const CUTPOINT_3_4 = 1.0;

    private const CUTPOINT_4_5 = 2.0;

    public function __construct(private AbilityEstimationService $abilityEstimation)
    {
    }

    public function levelNumberForTheta(float $theta): int
    {
        return match (true) {
            $theta < self::CUTPOINT_1_2 => 1,
            $theta < self::CUTPOINT_2_3 => 2,
            $theta < self::CUTPOINT_3_4 => 3,
            $theta < self::CUTPOINT_4_5 => 4,
            default => 5,
        };
    }

    /**
     * Apply leveling rules after a session is completed. For placement/daily
     * sessions this re-estimates the student's overall theta from their full
     * response history (not just this session) via MLE, then derives
     * current_level_id from it. Practice sessions are a no-op for level/theta.
     */
    public function adjustLevelAfterSession(TestSession $session): void
    {
        $user = $session->user;
        $levelBefore = $user->current_level_id;

        if ($session->session_type === 'practice') {
            $session->update([
                'level_before_id' => $levelBefore,
                'level_after_id' => $levelBefore,
            ]);

            return;
        }

        $estimate = $this->abilityEstimation->estimateFromHistory($user);
        $levelNumber = $this->levelNumberForTheta($estimate['theta']);
        $newLevel = IqLevel::where('level_number', $levelNumber)->firstOrFail();

        $update = [
            'current_level_id' => $newLevel->id,
            'theta_estimate' => $estimate['theta'],
            'theta_se' => $estimate['se'],
        ];

        if ($session->session_type === 'placement') {
            $update['placement_completed_at'] = now();
        }

        $user->update($update);

        $session->update([
            'level_before_id' => $levelBefore,
            'level_after_id' => $newLevel->id,
            'theta' => $estimate['theta'],
            'theta_se' => $estimate['se'],
        ]);
    }
}
