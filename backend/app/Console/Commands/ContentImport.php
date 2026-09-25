<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Loads a snapshot written by content:export. */
class ContentImport extends Command
{
    protected $signature = 'content:import {--path= : Snapshot file (default database/content/content.json.gz)} {--force : Replace existing content}';

    protected $description = 'Import levels, categories, games, badges and questions from a snapshot file';

    public function handle()
    {
        $path = $this->option('path') ?: database_path('content/content.json.gz');

        if (! is_file($path)) {
            $this->error("Snapshot not found: {$path}");

            return Command::FAILURE;
        }

        if (DB::table('questions')->exists() && ! $this->option('force')) {
            $this->info('Question bank already populated; nothing to import (use --force to replace).');

            return Command::SUCCESS;
        }

        $snapshot = json_decode(gzdecode(file_get_contents($path)), true);
        if (! isset($snapshot['tables'])) {
            $this->error('Snapshot is not valid.');

            return Command::FAILURE;
        }

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($snapshot) {
                if ($this->option('force')) {
                    foreach (array_reverse(ContentExport::TABLES) as $table) {
                        DB::table($table)->delete();
                    }
                }

                foreach (ContentExport::TABLES as $table) {
                    $rows = $snapshot['tables'][$table] ?? [];
                    foreach (array_chunk($rows, 250) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                    $this->line(sprintf('  %-12s %d rows', $table, count($rows)));
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Content imported.');

        return Command::SUCCESS;
    }
}
