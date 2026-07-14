<?php

namespace Database\Seeders\Questions\Bank3;

use Illuminate\Database\Seeder;

/**
 * Orchestrates the Bank3 layer: 7 archetypes missing from the original
 * question bank - blood relations, direction sense, coding-decoding,
 * calendar/clock reasoning, seating arrangement, data interpretation, and
 * statement-sufficiency reasoning. Every question is generated with a real
 * computational solver, following the same BuildsQuestions/insertRows
 * pattern as Bank2. Runs after QuestionSeeder so the duplicate guard
 * ($seenTexts, seeded from is_active questions) sees prior content.
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
