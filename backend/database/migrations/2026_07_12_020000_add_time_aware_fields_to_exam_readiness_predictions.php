<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive fields from app.py's time-aware /predict response (see
 * ml-service/app.py's PredictionResponse) - both nullable since they only
 * populate when the caller sends the optional exam_pace_gap/
 * time_efficiency_score signals (time_management_readiness_percent) or when
 * the multi-output regression + its RMSE are both available
 * (predicted_score_range), same additive/backward-compatible pattern as the
 * earlier Phase 7 migration on this table.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('exam_readiness_predictions', function (Blueprint $table) {
            $table->decimal('time_management_readiness_percent', 5, 2)->nullable()->after('plain_english_explanation');
            $table->json('predicted_score_range')->nullable()->after('time_management_readiness_percent');
        });
    }

    public function down()
    {
        Schema::table('exam_readiness_predictions', function (Blueprint $table) {
            $table->dropColumn(['time_management_readiness_percent', 'predicted_score_range']);
        });
    }
};
