<?php

namespace Database\Seeders;

use Database\Seeders\Questions\AdvancedLogicalQuestionsSeeder;
use Database\Seeders\Questions\AdvancedNumericalQuestionsSeeder;
use Database\Seeders\Questions\AdvancedSpatialQuestionsSeeder;
use Database\Seeders\Questions\AttentionQuestionsSeeder;
use Database\Seeders\Questions\LogicalReasoningQuestionsSeeder;
use Database\Seeders\Questions\MemoryQuestionsSeeder;
use Database\Seeders\Questions\NumericalAbilityQuestionsSeeder;
use Database\Seeders\Questions\SpatialPatternQuestionsSeeder;
use Illuminate\Database\Seeder;

/**
 * Orchestrates the full question bank: a base layer of 5 categories x 5
 * levels x 80 questions each (2000 total), plus an "advanced" layer of
 * harder, real-exam-style questions (work & time, simple interest, blood
 * relations, coding-decoding, custom operators, rotation sequences)
 * concentrated at levels 3-5 for competitive-exam-candidate difficulty.
 */
class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MemoryQuestionsSeeder::class,
            LogicalReasoningQuestionsSeeder::class,
            NumericalAbilityQuestionsSeeder::class,
            AttentionQuestionsSeeder::class,
            SpatialPatternQuestionsSeeder::class,
            AdvancedNumericalQuestionsSeeder::class,
            AdvancedLogicalQuestionsSeeder::class,
            AdvancedSpatialQuestionsSeeder::class,
        ]);
    }
}
