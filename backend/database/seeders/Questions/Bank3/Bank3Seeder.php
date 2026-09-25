<?php

namespace Database\Seeders\Questions\Bank3;

use Illuminate\Database\Seeder;

/** Orchestrates the Bank3 layer: 7 archetypes missing from the original question bank - blood relations, direction sense. */
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
