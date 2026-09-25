<?php

namespace App\Console\Commands;

use App\Services\QuestionBank\SinhalaTextGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Runs the Sinhala script-integrity guard over every active question and reports what it finds. */
class SinhalaAudit extends Command
{
    protected $signature = 'sinhala:audit {--list=10 : How many example question ids to print per issue}';

    protected $description = 'Check the Sinhala of all active questions for corruption and untranslated text';

    public function handle()
    {
        $guard = new SinhalaTextGuard();
        $byCode = [];
        $questionsChecked = 0;
        $questionsFlagged = [];

        DB::table('questions')->where('is_active', true)->orderBy('id')->chunk(500, function ($rows) use ($guard, &$byCode, &$questionsChecked, &$questionsFlagged) {
            foreach ($rows as $row) {
                $questionsChecked++;
                $checks = [
                    'question' => [$row->question_text_si, $row->question_text_en, true],
                    'explanation' => [$row->explanation_si, $row->explanation_en, true],
                ];
                foreach ((array) json_decode($row->options ?? '[]', true) as $option) {
                    $checks['option '.($option['key'] ?? '?')] = [$option['text_si'] ?? '', $option['text_en'] ?? null, false];
                }

                foreach ($checks as $field => [$si, $en, $sentence]) {
                    if (! is_string($si) || trim($si) === '') {
                        continue;
                    }
                    foreach ($guard->inspect($si, $en, $sentence) as $issue) {
                        $code = $issue['severity'].':'.$issue['code'];
                        $byCode[$code][$row->id] = true;
                        $questionsFlagged[$row->id] = true;
                    }
                }
            }
        });

        $this->info("Checked {$questionsChecked} active questions (question, explanation and every option).");
        if ($byCode === []) {
            $this->info('No issues found.');

            return Command::SUCCESS;
        }

        $this->table(['Issue', 'Questions'], collect($byCode)->map(fn ($ids, $code) => [$code, count($ids)])->values()->all());
        foreach ($byCode as $code => $ids) {
            $this->line(sprintf('  %s e.g. ids: %s', $code, implode(', ', array_slice(array_keys($ids), 0, (int) $this->option('list')))));
        }
        $this->warn(count($questionsFlagged).' question(s) need a human look.');

        return Command::SUCCESS;
    }
}
