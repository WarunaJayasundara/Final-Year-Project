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
 * Levels are cut at +/-0.5 and +/-1.5 logits, i.e. each level spans
 * approximately one standard deviation of the ability distribution - the same
 * banding convention used for stanine-style normal-curve grouping.
 */
class LevelAdjustmentService
{
    private const CUTPOINT_1_2 = -1.5;

    private const CUTPOINT_2_3 = -0.5;

    private const CUTPOINT_3_4 = 0.5;

    private const CUTPOINT_4_5 = 1.5;

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
