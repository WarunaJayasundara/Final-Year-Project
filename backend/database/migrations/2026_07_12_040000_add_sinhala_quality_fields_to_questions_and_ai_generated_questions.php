<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sinhala translation-quality fields (brief §16) - all nullable/additive. */
return new class extends Migration
{
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('translation_status', 20)->default('pending')->after('validation_status');
            $table->float('translation_quality_score')->nullable()->after('translation_status');
            $table->string('sinhala_review_status', 20)->default('pending')->after('translation_quality_score');
            $table->foreignId('reviewed_by')->nullable()->after('sinhala_review_status')->constrained('users')->nullOnDelete();
            $table->float('semantic_equivalence_score')->nullable()->after('reviewed_by');
        });

        Schema::table('ai_generated_questions', function (Blueprint $table) {
            $table->string('translation_status', 20)->default('pending')->after('validation_status');
            $table->float('translation_quality_score')->nullable()->after('translation_status');
            $table->string('sinhala_review_status', 20)->default('pending')->after('translation_quality_score');
            $table->float('semantic_equivalence_score')->nullable()->after('sinhala_review_status');
        });
    }

    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['translation_status', 'translation_quality_score', 'sinhala_review_status', 'semantic_equivalence_score']);
        });

        Schema::table('ai_generated_questions', function (Blueprint $table) {
            $table->dropColumn(['translation_status', 'translation_quality_score', 'sinhala_review_status', 'semantic_equivalence_score']);
        });
    }
};
