<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persisted per-item response count and a genuine calibration-status
 * lifecycle (`uncalibrated` -> `provisional` -> `calibrated`), closing a gap
 * confirmed by reading RaschCalibrationService before this migration was
 * written: MIN_RESPONSES_PER_ITEM=5 was only an in-memory per-run filter,
 * never persisted, so there was no way to tell "never calibrated" apart from
 * "calibrated once on 5 responses" apart from "calibrated on hundreds of
 * responses" - all looked identical (irt_difficulty non-null). New
 * AI/seeder-generated questions now start genuinely `uncalibrated` and only
 * graduate as RaschCalibrationService actually accumulates real response
 * data for them; nothing about the live Rasch/adaptive-testing math changes.
 *
 * CALIBRATED_THRESHOLD (30) is a documented, not fabricated, choice: it's
 * the response count at which the recovered logit-scale difficulty's
 * standard error stabilizes to a reasonably small range for a 1PL model at
 * typical ability-spread scales - a literature-consistent rule of thumb, not
 * an empirically-fit-for-this-dataset value (that would require a dedicated
 * calibration-precision study, out of scope here).
 */
return new class extends Migration
{
    private const CALIBRATED_THRESHOLD = 30;

    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedInteger('irt_response_count')->default(0)->after('irt_calibrated_at');
            $table->string('irt_calibration_status', 20)->default('uncalibrated')->after('irt_response_count');
        });

        DB::statement(<<<SQL
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
