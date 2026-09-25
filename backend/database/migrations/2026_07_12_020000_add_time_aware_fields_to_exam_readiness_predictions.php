<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Additive fields from app.py's time-aware /predict response (see ml-service/app.py's PredictionResponse). */
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
