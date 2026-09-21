<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes the curated learning content (levels, categories, games, badges and
 * the ACTIVE question bank) to a single compressed JSON snapshot. Paired with
 * content:import, this lets a fresh host (or a new developer) get exactly the
 * validated question bank without re-running the non-idempotent seeders, which
 * would also resurrect questions that were deliberately retired.
 *
 * User data, sessions and history are never exported.
 */
class ContentExport extends Command
{
    protected $signature = 'content:export {--path= : Output file (default database/content/content.json.gz)}';

    protected $description = 'Export levels, categories, games, badges and active questions to a snapshot file';

    /** Content tables in dependency order (parents first). */
    public const TABLES = ['iq_levels', 'categories', 'games', 'badges', 'questions'];

    /** Columns that point at data which is not part of the snapshot. */
    private const DETACHED_COLUMNS = ['created_by', 'reviewed_by', 'source_document_id'];

    public function handle()
    {
        $path = $this->option('path') ?: database_path('content/content.json.gz');
        $snapshot = ['exported_at' => now()->toIso8601String(), 'tables' => []];

        foreach (self::TABLES as $table) {
            $query = DB::table($table)->orderBy('id');
            if ($table === 'questions') {
                $query->where('is_active', true);
            }

            $rows = $query->get()->map(function ($row) use ($table) {
                $row = (array) $row;
                if ($table === 'questions') {
                    foreach (self::DETACHED_COLUMNS as $column) {
                        $row[$column] = null;
                    }
                }

                return $row;
            })->all();

            $snapshot['tables'][$table] = $rows;
            $this->line(sprintf('  %-12s %d rows', $table, count($rows)));
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, gzencode(json_encode($snapshot, JSON_UNESCAPED_UNICODE), 9));

        $this->info(sprintf('Wrote %s (%s KB).', $path, number_format(filesize($path) / 1024, 0)));

        return Command::SUCCESS;
    }
}
