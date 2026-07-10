<?php

namespace App\Services\Irt;

use App\Models\SessionAnswer;
use App\Models\User;

/**
 * DB-backed wrapper around RaschMath::estimateAbility(). Two entry points:
 *  - estimateFromSession(): the running/final estimate for one (typically
 *    adaptively-delivered) session, using only that session's answers so far.
 *  - estimateFromHistory(): the user's overall ability, re-estimated via MLE
 *    over their *entire* placement+daily response history every time a
 *    session completes - this is what current_level_id and the IQ estimate
 *    are ultimately derived from.
 */
class AbilityEstimationService
{
    public function estimateFromSession(int $sessionId, float $startingTheta = 0.0): array
    {
        $answers = SessionAnswer::where('test_session_id', $sessionId)
            ->whereNotNull('answered_at')
            ->with('question')
            ->get();

        return $this->estimate($answers, $startingTheta);
    }

    public function estimateFromHistory(User $user): array
    {
        $answers = SessionAnswer::whereHas('session', function ($query) use ($user) {
            $query->where('user_id', $user->id)->whereIn('session_type', ['placement', 'daily']);
        })
            ->whereNotNull('answered_at')
            ->with('question')
            ->get();

        return $this->estimate($answers, $user->theta_estimate ?? 0.0);
    }

    /** @param \Illuminate\Support\Collection<int,SessionAnswer> $answers */
    private function estimate($answers, float $startingTheta): array
    {
        $itemDifficulties = [];
        $responses = [];

        foreach ($answers as $answer) {
            if (! $answer->question) {
                continue;
            }

            $itemDifficulties[$answer->question_id] = RaschCalibrationService::difficultyFor($answer->question);
            $responses[$answer->question_id] = (bool) $answer->is_correct;
        }

        return RaschMath::estimateAbility($itemDifficulties, $responses, $startingTheta);
    }
}
