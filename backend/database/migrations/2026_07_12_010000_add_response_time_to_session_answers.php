<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real per-question response time, captured client-side (performance.now()
 * at question mount vs. submit) and sent alongside the answer. Previously
 * the only timing signal was answered_at (server-set), which only supports
 * inter-answer deltas, not true per-item timing. time_performance_ratio and
 * answered_within_expected_time are computed once at write time against the
 * question's learned_expected_time_seconds (falling back to the authored
 * solving_time_seconds) so downstream analytics never need to re-join and
 * recompute them.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('session_answers', function (Blueprint $table) {
            $table->unsignedInteger('response_time_ms')->nullable()->after('answered_at');
            $table->float('time_performance_ratio')->nullable()->after('response_time_ms');
            $table->boolean('answered_within_expected_time')->nullable()->after('time_performance_ratio');
        });
    }

    public function down()
    {
        Schema::table('session_answers', function (Blueprint $table) {
            $table->dropColumn(['response_time_ms', 'time_performance_ratio', 'answered_within_expected_time']);
        });
    }
};
