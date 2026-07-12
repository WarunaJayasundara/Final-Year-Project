<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the existing irt_difficulty/irt_calibration_status lifecycle
 * (uncalibrated -> provisional -> calibrated) for expected solving time.
 * solving_time_seconds (Phase 6) stays the author/AI-estimated baseline;
 * learned_expected_time_seconds is the platform-observed value once enough
 * real response_time_ms samples exist (see ResponseTimeCalibrationService).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->float('learned_expected_time_seconds')->nullable()->after('solving_time_seconds');
            $table->unsignedInteger('time_sample_count')->default(0)->after('learned_expected_time_seconds');
            $table->string('time_calibration_status', 20)->default('uncalibrated')->after('time_sample_count');
        });
    }

    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['learned_expected_time_seconds', 'time_sample_count', 'time_calibration_status']);
        });
    }
};
