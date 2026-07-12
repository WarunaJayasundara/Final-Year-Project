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
 * Orchestrates the competitive-exam question bank (~5,300 questions):
 * the exam-authentic and advanced waves modelled on Sri Lankan aptitude
 * papers, plus the Bank2 generation layer (SVG matrix reasoning / figure
 * series / rotation / mirror / paper folding / cube nets, plus large
 * numerical, logical/verbal, memory and attention banks). The original
 * 2,000-question starter bank was retired (deactivated, kept only for
 * response-history integrity) after supervisor feedback that it was below
 * the target audience's level - see CompetitiveBankSeeder for the
 * deactivate-and-replace entry point used on an existing database.
 *
 * Order matters: the Exam/Advanced waves run first so the Bank2 seeders'
 * run-time duplicate guard can see their question texts. Bank3 (blood
 * relations / direction sense / coding-decoding / calendar-clock / seating
 * arrangement / data interpretation / statement sufficiency - archetypes
 * confirmed missing by the Phase-1 PDF document analysis, see CLAUDE.md)
 * runs last for the same reason.
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
