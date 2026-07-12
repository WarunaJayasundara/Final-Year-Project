<?php

namespace Database\Seeders\Questions\Bank4;

use Illuminate\Database\Seeder;

/**
 * Orchestrates the Bank4 layer: 5 archetypes targeting the Level 4-5
 * difficulty gap confirmed by the adult-content audit (statement/truth
 * reasoning, multi-constraint puzzles, set-relational reasoning, chained
 * multi-step numeric word problems, and passage-based argument evaluation)
 * - all modelled on the difficulty bar of the uploaded GCE A/L Common
 * General Test specimen paper and the Environmental Officer exam guide,
 * never copied verbatim. Every question is deterministically generated
 * with a real computational solver. Run after Bank3 so this batch's
 * run-time duplicate guard can see all prior content.
 */
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
