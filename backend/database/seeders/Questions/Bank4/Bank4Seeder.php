<?php

namespace Database\Seeders\Questions\Bank4;

use Illuminate\Database\Seeder;

/** Orchestrates the Bank4 layer: 5 archetypes targeting the Level 4-5 difficulty gap (truth/statement reasoning. */
class Bank4Seeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TruthTellerLogicSeeder::class,
            MultiConstraintSeatingSeeder::class,
            VennConsistencySeeder::class,
            AdultWordProblemSeeder::class,
            CriticalReasoningPassageSeeder::class,
        ]);
    }
}
