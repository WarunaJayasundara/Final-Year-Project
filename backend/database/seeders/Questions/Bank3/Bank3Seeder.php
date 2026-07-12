<?php

namespace Database\Seeders\Questions\Bank3;

use Illuminate\Database\Seeder;

/**
 * Orchestrates the Bank3 layer: 7 archetypes confirmed missing from
 * MindRise's question bank by the Phase-1 PDF document analysis (see
 * CLAUDE.md §"Advanced 7,500+ Question Bank" and
 * docs/ML_RESEARCH_METHODOLOGY.md's source-document inventory) - blood
 * relations, direction sense, coding-decoding, calendar/clock reasoning,
 * seating arrangement, data interpretation, and statement-sufficiency
 * critical reasoning. Every question is deterministically generated with a
 * real computational solver (never an asserted/guessed answer), following
 * the exact same BuildsQuestions/insertRows pattern as Bank2. Run after
 * QuestionSeeder's existing waves so this batch's run-time duplicate guard
 * (each seeder's $seenTexts, seeded from is_active questions) can see all
 * prior content.
 */
class Bank3Seeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BloodRelationsSeeder::class,
            DirectionSenseSeeder::class,
            CodingDecodingSeeder::class,
            CalendarClockSeeder::class,
            SeatingArrangementSeeder::class,
            DataInterpretationSeeder::class,
            StatementSufficiencyReasoningSeeder::class,
        ]);
    }
}
