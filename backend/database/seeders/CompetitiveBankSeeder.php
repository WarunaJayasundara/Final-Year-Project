<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

/**
 * Replaces the live question bank on an existing database: deactivates
 * every current question (kept, not deleted, for session_answers history)
 * then seeds the full new bank active. Run with:
 *
 *   php artisan db:seed --class=CompetitiveBankSeeder
 *
 * Not idempotent - running it twice seeds the bank twice. Check
 * `Question::where('is_active', true)->count()` before re-running.
 */
class CompetitiveBankSeeder extends Seeder
{
    public function run(): void
    {
        $retired = Question::where('is_active', true)->update(['is_active' => false]);
        $this->command?->info("Retired {$retired} existing question(s) (kept inactive for answer-history integrity).");

        $this->call(QuestionSeeder::class);

        $active = Question::where('is_active', true)->count();
        $this->command?->info("Active competitive bank: {$active} questions.");
    }
}
