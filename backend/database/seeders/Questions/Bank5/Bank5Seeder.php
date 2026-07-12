<?php

namespace Database\Seeders\Questions\Bank5;

use Illuminate\Database\Seeder;

/**
 * Orchestrates the Bank5 layer: visual archetypes added by the
 * adult-content Visual IQ Engine v2 upgrade (boolean shape overlays,
 * chart-based data interpretation) using the new SvgFigureBuilder chart/
 * bool panel types and the new generation_rule/transformation_steps
 * metadata columns. Embedded-figure counting and 2D-to-3D object assembly
 * were scoped out of this pass (see CLAUDE.md) rather than shipped with
 * an unverified closed-form answer formula.
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
