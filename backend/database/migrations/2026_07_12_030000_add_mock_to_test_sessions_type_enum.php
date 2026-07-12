<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'mock' to test_sessions.session_type's enum (previously
 * placement/daily/practice only - see 2026_07_07_034627_create_test_sessions_table.php).
 * Laravel's schema builder can't alter a MySQL enum's value list without
 * doctrine/dbal (which itself doesn't model MySQL enums well), so this is a
 * raw ALTER TABLE - safe here since it's purely additive to the allowed
 * value list, no existing row's session_type is touched.
 */
return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE test_sessions MODIFY session_type ENUM('placement', 'daily', 'practice', 'mock') NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE test_sessions MODIFY session_type ENUM('placement', 'daily', 'practice') NOT NULL");
    }
};
