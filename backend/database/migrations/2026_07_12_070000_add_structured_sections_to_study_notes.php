<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restructures a study note from a single text blob into distinguishable
 * teaching sections (learning objective already existed dormant on
 * questions/ai_generated_questions from an earlier session - this is the
 * study_notes equivalent). `content_en`/`content_si` remain as the
 * intro/concept-explanation section (backward compatible with every
 * pre-existing generated note); the 3 new sections are additive and
 * nullable so older notes simply omit them rather than breaking.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('study_notes', function (Blueprint $table) {
            $table->text('learning_objective_en')->nullable()->after('subcategory');
            $table->text('learning_objective_si')->nullable()->after('learning_objective_en');
            $table->text('worked_example_en')->nullable()->after('content_si');
            $table->text('worked_example_si')->nullable()->after('worked_example_en');
            $table->text('key_technique_en')->nullable()->after('worked_example_si');
            $table->text('key_technique_si')->nullable()->after('key_technique_en');
            $table->text('common_mistakes_en')->nullable()->after('key_technique_si');
            $table->text('common_mistakes_si')->nullable()->after('common_mistakes_en');
        });
    }

    public function down()
    {
        Schema::table('study_notes', function (Blueprint $table) {
            $table->dropColumn([
                'learning_objective_en', 'learning_objective_si',
                'worked_example_en', 'worked_example_si',
                'key_technique_en', 'key_technique_si',
                'common_mistakes_en', 'common_mistakes_si',
            ]);
        });
    }
};
