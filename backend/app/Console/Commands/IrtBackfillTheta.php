<?php

namespace App\Console\Commands;

use App\Models\IqLevel;
use App\Models\User;
use App\Services\Irt\AbilityEstimationService;
use App\Services\Leveling\LevelAdjustmentService;
use Illuminate\Console\Command;

/**
 * One-time migration utility: users who completed placement before the
 * Rasch/IRT engine existed have current_level_id set (via the old
 * percentage-band system) but no theta_estimate, so their IQ estimate and
 * future level changes would otherwise stay frozen until their next session
 * completes. This recomputes theta from each such user's existing response
 * history (via the same AbilityEstimationService the live app uses) so their
 * account is immediately consistent with the new system.
 */
class IrtBackfillTheta extends Command
{
    protected $signature = 'irt:backfill-theta';

    protected $description = 'Backfill theta_estimate/current_level_id for users who placed before the IRT engine existed';

    public function handle(AbilityEstimationService $abilityEstimation, LevelAdjustmentService $leveling)
    {
        $users = User::whereNotNull('placement_completed_at')->whereNull('theta_estimate')->get();

        if ($users->isEmpty()) {
            $this->info('No users need backfilling.');

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($users as $user) {
            $estimate = $abilityEstimation->estimateFromHistory($user);
            $levelNumber = $leveling->levelNumberForTheta($estimate['theta']);
            $newLevel = IqLevel::where('level_number', $levelNumber)->firstOrFail();

            $user->update([
                'theta_estimate' => $estimate['theta'],
                'theta_se' => $estimate['se'],
                'current_level_id' => $newLevel->id,
            ]);

            $rows[] = [$user->id, $user->email, $estimate['theta'], $estimate['se'], $levelNumber];
        }

        $this->table(['User ID', 'Email', 'Theta', 'SE', 'Level'], $rows);

        return Command::SUCCESS;
    }
}
