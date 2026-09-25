<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional wall-clock time limit for a session. */
return new class extends Migration
{
    public function up()
    {
        Schema::table('test_sessions', function (Blueprint $table) {
            $table->unsignedInteger('time_limit_seconds')->nullable()->after('total_questions');
        });
    }

    public function down()
    {
        Schema::table('test_sessions', function (Blueprint $table) {
            $table->dropColumn('time_limit_seconds');
        });
    }
};
