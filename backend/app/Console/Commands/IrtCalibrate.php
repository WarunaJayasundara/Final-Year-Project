<?php

namespace App\Console\Commands;

use App\Services\Irt\RaschCalibrationService;
use Illuminate\Console\Command;

class IrtCalibrate extends Command
{
    protected $signature = 'irt:calibrate';

    protected $description = 'Recalibrate question difficulty parameters (Rasch/PROX) from response data';

    public function handle(RaschCalibrationService $calibration)
    {
        $this->info('Calibrating item difficulties from response history...');

        $result = $calibration->calibrate();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total responses used', $result['total_responses']],
                ['Items calibrated', $result['calibrated_items']],
                ['Items skipped (too few responses)', $result['skipped_low_data']],
            ]
        );

        return Command::SUCCESS;
    }
}
