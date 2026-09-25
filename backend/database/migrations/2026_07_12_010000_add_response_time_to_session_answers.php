<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Real per-question response time, captured client-side. */
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
