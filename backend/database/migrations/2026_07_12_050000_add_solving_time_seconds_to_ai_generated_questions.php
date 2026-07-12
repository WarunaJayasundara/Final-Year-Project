<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ai_generated_questions never got solving_time_seconds when questions
 * gained it (Phase 6's 2026_07_10_062508 migration only touched the live
 * table), so a draft's AI-estimated solving time had nowhere to be
 * persisted - discovered while wiring Mock/GeminiAiQuestionGeneratorService
 * to compute it (brief §18: "Every AI-generated question must include...
 * Estimated solving time"). Closing that gap here.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('ai_generated_questions', function (Blueprint $table) {
            $table->unsignedSmallInteger('solving_time_seconds')->nullable()->after('difficulty_weight');
        });
    }

    public function down()
    {
        Schema::table('ai_generated_questions', function (Blueprint $table) {
            $table->dropColumn('solving_time_seconds');
        });
    }
};
