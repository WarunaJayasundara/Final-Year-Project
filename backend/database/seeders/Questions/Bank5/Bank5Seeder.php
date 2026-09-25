<?php

namespace Database\Seeders\Questions\Bank5;

use Illuminate\Database\Seeder;

/** Orchestrates the Bank5 layer: boolean shape overlays and chart-based data interpretation. */
class Bank5Seeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BooleanOverlaySeeder::class,
            ChartDataInterpretationSeeder::class,
        ]);
    }
}
