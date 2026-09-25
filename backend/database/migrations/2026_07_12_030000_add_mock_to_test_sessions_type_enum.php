<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Adds 'mock' to test_sessions.session_type's enum (previously placement/daily/practice only. */
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
