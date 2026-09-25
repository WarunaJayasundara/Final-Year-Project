<?php

namespace App\Services\Leveling;

use App\Models\IqLevel;
use App\Models\TestSession;
use App\Services\Irt\AbilityEstimationService;

/** Maps a student's Rasch-model ability estimate (theta. */
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

    /** Apply leveling rules after a session is completed. */
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
