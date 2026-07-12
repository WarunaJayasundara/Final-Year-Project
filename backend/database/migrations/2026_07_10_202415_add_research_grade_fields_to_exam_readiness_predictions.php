<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive columns for the research-grade ML upgrade's multi-output
 * predictions (ml-service/app.py's expanded /predict response) - all
 * nullable so every pre-existing row (predicted before this upgrade)
 * remains perfectly valid with these fields simply absent, and the
 * `features` JSON column already accommodates the 18 new advanced
 * features with no schema change at all.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('exam_readiness_predictions', function (Blueprint $table) {
            $table->decimal('risk_of_dropping_practice_probability', 4, 3)->nullable()->after('reasons');
            $table->boolean('at_risk_of_dropping_practice')->nullable()->after('risk_of_dropping_practice_probability');
            $table->decimal('predicted_next_assessment_score', 5, 2)->nullable()->after('at_risk_of_dropping_practice');
            $table->decimal('predicted_score_change', 5, 2)->nullable()->after('predicted_next_assessment_score');
            $table->text('plain_english_explanation')->nullable()->after('predicted_score_change');
        });
    }

    public function down()
    {
        Schema::table('exam_readiness_predictions', function (Blueprint $table) {
            $table->dropColumn([
                'risk_of_dropping_practice_probability',
                'at_risk_of_dropping_practice',
                'predicted_next_assessment_score',
                'predicted_score_change',
                'plain_english_explanation',
            ]);
        });
    }
};
