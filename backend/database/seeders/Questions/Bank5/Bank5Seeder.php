<?php

namespace Database\Seeders\Questions\Bank5;

use Illuminate\Database\Seeder;

/**
 * Orchestrates the Bank5 layer: boolean shape overlays and chart-based
 * data interpretation, built with SvgFigureBuilder's chart/bool panel
 * types. Embedded-figure counting and 2D-to-3D object assembly were
 * left out (see CLAUDE.md) since no answer formula could be trusted yet.
 */
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
