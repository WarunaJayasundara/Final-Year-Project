<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Persisted per-item response count and a genuine calibration-status lifecycle (`uncalibrated` -> `provisional` -> `calibrated`). */
return new class extends Migration
{
    private const CALIBRATED_THRESHOLD = 30;

    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedInteger('irt_response_count')->default(0)->after('irt_calibrated_at');
            $table->string('irt_calibration_status', 20)->default('uncalibrated')->after('irt_response_count');
        });

        DB::statement(<<<'SQL'
            UPDATE questions q
            LEFT JOIN (
                SELECT question_id, COUNT(*) AS cnt
                FROM session_answers
                WHERE answered_at IS NOT NULL
                GROUP BY question_id
            ) sa ON sa.question_id = q.id
            SET q.irt_response_count = COALESCE(sa.cnt, 0)
        SQL);

        $threshold = self::CALIBRATED_THRESHOLD;
        DB::statement(<<<SQL
            UPDATE questions
            SET irt_calibration_status = CASE
                WHEN irt_difficulty IS NULL THEN 'uncalibrated'
                WHEN irt_response_count >= {$threshold} THEN 'calibrated'
                ELSE 'provisional'
            END
        SQL);
    }

    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['irt_response_count', 'irt_calibration_status']);
        });
    }
};
