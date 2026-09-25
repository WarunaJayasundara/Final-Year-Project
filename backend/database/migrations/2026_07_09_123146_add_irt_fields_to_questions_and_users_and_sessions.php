<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds the columns needed for the Rasch-model (1PL Item Response Theory) adaptive testing engine: - questions.irt_difficulty. */
return new class extends Migration
{
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->float('irt_difficulty')->nullable()->after('difficulty_weight');
            $table->float('irt_discrimination')->default(1.0)->after('irt_difficulty');
            $table->timestamp('irt_calibrated_at')->nullable()->after('irt_discrimination');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->float('theta_estimate')->nullable()->after('current_level_id');
            $table->float('theta_se')->nullable()->after('theta_estimate');
        });

        Schema::table('test_sessions', function (Blueprint $table) {
            $table->float('theta')->nullable()->after('score_percent');
            $table->float('theta_se')->nullable()->after('theta');
        });
    }

    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['irt_difficulty', 'irt_discrimination', 'irt_calibrated_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['theta_estimate', 'theta_se']);
        });

        Schema::table('test_sessions', function (Blueprint $table) {
            $table->dropColumn(['theta', 'theta_se']);
        });
    }
};
