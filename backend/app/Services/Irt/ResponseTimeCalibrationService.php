<?php

namespace App\Services\Irt;

use App\Models\Question;
use App\Models\SessionAnswer;
use Illuminate\Support\Facades\DB;

/**
 * Learns each question's real expected solving time from actual
 * response_time_ms samples, replacing the author/AI-authored
 * solving_time_seconds baseline once enough data exists - the response-time
 * analogue of RaschCalibrationService, with the same
 * uncalibrated -> provisional -> calibrated lifecycle (irt_calibration_status
 * mirrored as time_calibration_status).
 *
 * Uses the median, not the mean: the brief this was built for explicitly
 * asks for outlier-robust statistics so a few abandoned tabs/browser-inactive
 * sessions don't drag the "expected" time around. Run via
 * `php artisan time:calibrate`.
 */
class ResponseTimeCalibrationService
{
    /** Below this sample count, a question stays 'uncalibrated' (keeps the authored baseline). */
    private const PROVISIONAL_THRESHOLD = 10;

    /** Same order-of-magnitude threshold as RaschCalibrationService::CALIBRATED_THRESHOLD. */
    private const CALIBRATED_THRESHOLD = 30;

    public function calibrate(): array
    {
        $samplesByQuestion = SessionAnswer::query()
            ->whereNotNull('response_time_ms')
            ->select(['question_id', 'response_time_ms'])
            ->get()
            ->groupBy('question_id');

        if ($samplesByQuestion->isEmpty()) {
            return ['learned_items' => 0, 'total_samples' => 0, 'skipped_low_data' => 0];
        }

        $learned = 0;
        $skipped = 0;
        $totalSamples = 0;

        DB::transaction(function () use ($samplesByQuestion, &$learned, &$skipped, &$totalSamples) {
            foreach ($samplesByQuestion as $questionId => $samples) {
                $count = $samples->count();
                $totalSamples += $count;

                if ($count < self::PROVISIONAL_THRESHOLD) {
                    Question::whereKey($questionId)->update([
                        'time_sample_count' => $count,
                        'time_calibration_status' => 'uncalibrated',
                    ]);
                    $skipped++;

                    continue;
                }

                $medianSeconds = $this->median($samples->pluck('response_time_ms')->all()) / 1000;

                Question::whereKey($questionId)->update([
                    'learned_expected_time_seconds' => round($medianSeconds, 1),
                    'time_sample_count' => $count,
                    'time_calibration_status' => $count >= self::CALIBRATED_THRESHOLD ? 'calibrated' : 'provisional',
                ]);
                $learned++;
            }
        });

        return [
            'learned_items' => $learned,
            'total_samples' => $totalSamples,
            'skipped_low_data' => $skipped,
        ];
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        return $count % 2 === 0
            ? ($values[$mid - 1] + $values[$mid]) / 2
            : (float) $values[$mid];
    }
}
