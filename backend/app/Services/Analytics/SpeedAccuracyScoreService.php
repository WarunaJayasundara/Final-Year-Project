<?php

namespace App\Services\Analytics;

use App\Models\TestSession;
use App\Models\User;
use Illuminate\Support\Collection;

/** A bounded [0,100] score combining accuracy, item difficulty, and response time. */
class SpeedAccuracyScoreService
{
    /** Ratio (actual/expected) below which a wrong answer is flagged as a likely guess. */
    private const GUESS_RATIO_THRESHOLD = 0.3;

    private const SPEED_BAND = 0.15;

    public function forUser(User $user, int $lookbackSessions = 10): ?array
    {
        $sessionIds = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit($lookbackSessions)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return null;
        }

        $answers = \App\Models\SessionAnswer::whereIn('test_session_id', $sessionIds)
            ->with('question')
            ->whereNotNull('answered_at')
            ->get();

        return $this->scoreAnswers($answers);
    }

    private function scoreAnswers(Collection $answers): ?array
    {
        $items = $answers
            ->filter(fn ($a) => $a->question !== null)
            ->map(fn ($a) => [
                'is_correct' => (bool) $a->is_correct,
                'time_performance_ratio' => $a->time_performance_ratio,
                'difficulty_weight' => (int) $a->question->difficulty_weight,
            ])
            ->all();

        return self::scoreForItems($items);
    }

    /**
     * Pure, DB-free scoring core (Formulation B, see class docstring).
     * @param  array<int, array{is_correct: bool, time_performance_ratio: ?float, difficulty_weight: int}>  $items
     * @return array{score: float, guess_rate: float, sample_size: int}|null
     */
    public static function scoreForItems(array $items): ?array
    {
        if (empty($items)) {
            return null;
        }

        $weightedScoreSum = 0.0;
        $weightSum = 0.0;
        $guessCount = 0;
        $wrongCount = 0;

        foreach ($items as $item) {
            $weight = max(1, $item['difficulty_weight']);
            $ratio = $item['time_performance_ratio'];

            if (! $item['is_correct']) {
                $wrongCount++;
                if ($ratio !== null && $ratio < self::GUESS_RATIO_THRESHOLD) {
                    $guessCount++;
                }
                $weightSum += $weight;

                continue;
            }

            // No bonus for answering faster than the expected pace (ratio <= 1
            // stays at full 1.0 credit); a mild penalty, floored at -15%,
            // for answering slower than expected.
            $speedMultiplier = $ratio === null
                ? 1.0
                : max(1 - self::SPEED_BAND, min(1.0, 1 + self::SPEED_BAND - (self::SPEED_BAND * $ratio)));

            $weightedScoreSum += 100.0 * $speedMultiplier * $weight;
            $weightSum += $weight;
        }

        if ($weightSum === 0.0) {
            return null;
        }

        return [
            'score' => round(min(100.0, max(0.0, $weightedScoreSum / $weightSum)), 1),
            'guess_rate' => $wrongCount > 0 ? round($guessCount / $wrongCount, 3) : 0.0,
            'sample_size' => count($items),
        ];
    }
}
