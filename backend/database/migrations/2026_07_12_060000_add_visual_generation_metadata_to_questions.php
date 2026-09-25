<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Persists the transformation rule behind each generated image question. */
return new class extends Migration
{
    public function up()
    {
        // questions has an image_path column to anchor after; ai_generated_questions
        // does not (confirmed by the adult-content audit), so it gets the
        // same 3 columns appended at the end instead.
        Schema::table('questions', function (Blueprint $table) {
            $table->string('generation_rule', 60)->nullable()->after('image_path');
            $table->json('transformation_steps')->nullable()->after('generation_rule');
            $table->float('visual_complexity_score')->nullable()->after('transformation_steps');
        });

        Schema::table('ai_generated_questions', function (Blueprint $table) {
            $table->string('generation_rule', 60)->nullable();
            $table->json('transformation_steps')->nullable();
            $table->float('visual_complexity_score')->nullable();
        });
    }

    public function down()
    {
        $dropColumns = function (Blueprint $table) {
            $table->dropColumn(['generation_rule', 'transformation_steps', 'visual_complexity_score']);
        };

        Schema::table('questions', $dropColumns);
        Schema::table('ai_generated_questions', $dropColumns);
    }
};
