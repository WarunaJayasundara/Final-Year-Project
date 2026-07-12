<?php

namespace App\Services\Analytics;

use App\Models\TestSession;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A bounded [0,100] score combining accuracy, item difficulty, and response
 * time - answering the brief's "can the student answer correctly,
 * consistently, AND quickly enough" question in one number, without ever
 * touching Rasch theta or item calibration (those stay pure
 * accuracy-only, per the brief's explicit "do not put an arbitrary time
 * bonus into Rasch theta" instruction - see RaschMath/RaschCalibrationService,
 * both untouched by this file).
 *
 * Two formulations were considered and rejected before this one:
 *
 *   Formulation A (additive): score = accuracy - guessing_penalty -
 *   slowness_penalty. Rejected: additive penalties are hard to bound to
 *   [0,100] without ad-hoc clamping at every step, and double-penalizing a
 *   wrong+slow answer (already scored 0 for being wrong) adds no signal.
 *
 *   Formulation C (continuous, correctness-agnostic): score derived purely
 *   from time_performance_ratio regardless of correctness. Rejected: this
 *   rewards confidently-fast WRONG answers as much as confidently-fast
 *   correct ones, which is exactly the "don't reward random fast guessing"
 *   failure mode the brief explicitly warns against.
 *
 * Formulation B (selected, implemented below): correctness is a hard gate
 * (wrong = 0 for that item, full stop); a correct answer at or faster than
 * the expected pace earns full (100) credit - being fast doesn't earn a
 * bonus above full marks, since full marks already represents the maximum
 * possible credit for one item. Answering correctly but slower than
 * expected applies a mild penalty (down to -15% at 2x the expected time or
 * slower), so one unusually slow item can't swing the score wildly. Items
 * are weighted by their authored difficulty_weight (1-5) so solving harder
 * items counts for more, matching how difficulty already weights
 * `avg_difficulty_solved` in FeatureExtractionService.
 *
 * This is a deterministic bounded transform of already-verified inputs
 * (is_correct, time_performance_ratio, difficulty_weight) - not a latent
 * trait estimated from noisy data - so it isn't validated via the IRT
 * Monte Carlo recovery harness (irt:validate-simulation), which exists to
 * check parameter *recovery* under simulated noise. Instead it's covered by
 * property-based unit tests asserting the formula's documented invariants
 * (wrong answers always score 0; faster-than-expected correct answers never
 * score below a same-difficulty on-pace correct answer; speed's influence
 * never exceeds the documented +/-15% band) - see
 * tests/Unit/SpeedAccuracyScoreServiceTest.php.
 */
class SpeedAccuracyScoreService
{
    /** Ratio (actual/expected) below which a wrong answer is flagged as a likely guess. */
    private const GUESS_RATIO_THRESHOLD = 0.3;

    private const SPEED_BAND = 0.15;

    public function forSession(TestSession $session): ?array
    {
        $answers = $session->answers()->with('question')->whereNotNull('answered_at')->get();

        return $this->scoreAnswers($answers);
    }

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
     * Pure, DB-free scoring core (Formulation B, see class docstring) -
     * deliberately decoupled from Eloquent so the formula's invariants can
     * be unit-tested directly against plain arrays.
     *
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
