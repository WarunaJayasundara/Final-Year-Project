<?php

namespace Database\Seeders;

use Database\Seeders\Questions\AdvancedLogicalQuestionsSeeder;
use Database\Seeders\Questions\AdvancedNumericalQuestionsSeeder;
use Database\Seeders\Questions\AdvancedSpatialQuestionsSeeder;
use Database\Seeders\Questions\Bank2\LogicalVerbalBank2Seeder;
use Database\Seeders\Questions\Bank2\MatrixSeriesImageSeeder;
use Database\Seeders\Questions\Bank2\MemoryAttentionBank2Seeder;
use Database\Seeders\Questions\Bank2\NumericalBank2Seeder;
use Database\Seeders\Questions\Bank2\SpatialImageSeeder;
use Database\Seeders\Questions\Bank3\Bank3Seeder;
use Database\Seeders\Questions\Bank4\Bank4Seeder;
use Database\Seeders\Questions\Bank5\Bank5Seeder;
use Database\Seeders\Questions\ExamLogicalQuestionsSeeder;
use Database\Seeders\Questions\ExamNumericalQuestionsSeeder;
use Illuminate\Database\Seeder;

/**
 * Orchestrates the competitive-exam question bank: the exam-authentic and
 * advanced waves modelled on Sri Lankan aptitude papers, plus the Bank2
 * generation layer (SVG matrix/rotation/mirror/folding/cube-net figures,
 * plus large numerical, logical/verbal, memory and attention banks). The
 * older starter bank was deactivated, not deleted, to keep response
 * history intact - see CompetitiveBankSeeder for the replace entry point.
 *
 * Order matters: earlier waves run first so later seeders' duplicate
 * guards can see their question texts before generating more.
 */
class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ExamNumericalQuestionsSeeder::class,
            ExamLogicalQuestionsSeeder::class,
            AdvancedNumericalQuestionsSeeder::class,
            AdvancedLogicalQuestionsSeeder::class,
            AdvancedSpatialQuestionsSeeder::class,
            MatrixSeriesImageSeeder::class,
            SpatialImageSeeder::class,
            NumericalBank2Seeder::class,
            LogicalVerbalBank2Seeder::class,
            MemoryAttentionBank2Seeder::class,
            Bank3Seeder::class,
            Bank4Seeder::class,
            Bank5Seeder::class,
        ]);
    }
}
