<?php

namespace App\Console\Commands;

use App\Services\Irt\ResponseTimeCalibrationService;
use Illuminate\Console\Command;

class TimeCalibrate extends Command
{
    protected $signature = 'time:calibrate';

    protected $description = 'Learn expected question-solving time from real response_time_ms samples (median, outlier-robust)';

    public function handle(ResponseTimeCalibrationService $calibration)
    {
        $this->info('Calibrating expected solving times from response history...');

        $result = $calibration->calibrate();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total response-time samples', $result['total_samples']],
                ['Questions with a learned time', $result['learned_items']],
                ['Questions skipped (too few samples)', $result['skipped_low_data']],
            ]
        );

        return Command::SUCCESS;
    }
}
