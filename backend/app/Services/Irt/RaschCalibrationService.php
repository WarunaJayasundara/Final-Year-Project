<?php

namespace App\Services\Irt;

use App\Models\Question;
use App\Models\SessionAnswer;
use Illuminate\Support\Facades\DB;

/**
 * DB-backed wrapper around RaschMath::calibrateItems(): pulls every answered
 * response from session_answers, runs the PROX joint calibration, and writes
 * the recovered item difficulties back onto questions.irt_difficulty. Run via
 * `php artisan irt:calibrate` (see App\Console\Commands\IrtCalibrate), and
 * safe to re-run at any time as more response data accumulates - each run
 * fully recomputes from the current response history rather than incrementally
 * updating, which is the standard approach for periodic re-calibration.
 */
class RaschCalibrationService
{
    /** Items with fewer responses than this keep their prior (level-derived) difficulty. */
    private const MIN_RESPONSES_PER_ITEM = 5;

    /**
     * Response count at which irt_calibration_status graduates from
     * 'provisional' to 'calibrated' - same value/rationale as the migration
     * that introduced the column (2026_07_11_060200_...), kept in sync here
     * since this is the only place that updates the status after that
     * migration's one-time backfill.
     */
    private const CALIBRATED_THRESHOLD = 30;

    public function calibrate(): array
    {
        $responses = SessionAnswer::query()
            ->whereNotNull('answered_at')
            ->select(['test_session_id', 'question_id', 'is_correct'])
            ->get()
            ->map(fn (SessionAnswer $answer) => [
                'person' => $answer->test_session_id,
                'item' => $answer->question_id,
                'correct' => (bool) $answer->is_correct,
            ])
            ->all();

        if (count($responses) === 0) {
            return ['calibrated_items' => 0, 'total_responses' => 0, 'skipped_low_data' => 0];
        }

        $itemResponseCounts = [];
        foreach ($responses as $r) {
            $itemResponseCounts[$r['item']] = ($itemResponseCounts[$r['item']] ?? 0) + 1;
        }

        // Persist response counts + status for every item seen, not just the
        // ones eligible for re-calibration this run, so an item's count keeps
        // climbing toward CALIBRATED_THRESHOLD even between eligible runs.
        DB::transaction(function () use ($itemResponseCounts) {
            foreach ($itemResponseCounts as $questionId => $count) {
                $question = Question::find($questionId);
                if (! $question) {
                    continue;
                }

                $status = $question->irt_difficulty === null
                    ? 'uncalibrated'
                    : ($count >= self::CALIBRATED_THRESHOLD ? 'calibrated' : 'provisional');

                $question->update([
                    'irt_response_count' => $count,
                    'irt_calibration_status' => $status,
                ]);
            }
        });

        $eligibleItemIds = array_keys(array_filter(
            $itemResponseCounts,
            fn (int $count) => $count >= self::MIN_RESPONSES_PER_ITEM
        ));

        $eligibleResponses = array_values(array_filter(
            $responses,
            fn (array $r) => in_array($r['item'], $eligibleItemIds, true)
        ));

        if (count($eligibleResponses) === 0) {
            return [
                'calibrated_items' => 0,
                'total_responses' => count($responses),
                'skipped_low_data' => count($itemResponseCounts),
            ];
        }

        $result = RaschMath::calibrateItems($eligibleResponses);
        $now = now();

        DB::transaction(function () use ($result, $now) {
            foreach ($result['item_difficulty'] as $questionId => $difficulty) {
                Question::whereKey($questionId)->update([
                    'irt_difficulty' => $difficulty,
                    'irt_calibrated_at' => $now,
                    'irt_calibration_status' => Question::whereKey($questionId)->value('irt_response_count') >= self::CALIBRATED_THRESHOLD
                        ? 'calibrated'
                        : 'provisional',
                ]);
            }
        });

        return [
            'calibrated_items' => count($result['item_difficulty']),
            'total_responses' => count($responses),
            'skipped_low_data' => count($itemResponseCounts) - count($eligibleItemIds),
        ];
    }

    /**
     * Prior difficulty for an item that hasn't been calibrated yet (or ever will
     * be, if it's rarely served): derived from its authored level/difficulty_weight
     * so the system has a sane starting point from day one, then gets refined by
     * real calibration as response data accumulates.
     */
    public static function priorDifficulty(Question $question): float
    {
        $levelNumber = optional($question->level)->level_number ?? 3;

        // Spreads the 5 authored levels across roughly a -1.6 to +1.6 logit range,
        // which is the same order of magnitude as a typical calibrated item bank.
        return ($levelNumber - 3) * 0.8;
    }

    public static function difficultyFor(Question $question): float
    {
        return $question->irt_difficulty ?? self::priorDifficulty($question);
    }
}
