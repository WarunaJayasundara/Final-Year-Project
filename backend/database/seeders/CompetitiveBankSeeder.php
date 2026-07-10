<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

/**
 * Replaces the live question bank with the competitive-exam bank on an
 * EXISTING database: every current question is deactivated (rows are kept,
 * not deleted, because session_answers history references them), then the
 * full new bank is seeded active. Run with:
 *
 *   php artisan db:seed --class=CompetitiveBankSeeder
 *
 * Safe to reason about but not idempotent - running it twice would seed
 * the bank twice. Check `Question::where('is_active', true)->count()`
 * before re-running.
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
