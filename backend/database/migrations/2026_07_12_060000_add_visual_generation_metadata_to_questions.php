<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persists the transformation rule behind each generated image question -
 * confirmed absent from the schema by the adult-content audit
 * (SvgFigureBuilder's rotation/mirror/etc. parameters previously lived only
 * in seeder PHP code, never stored alongside the question row). Additive
 * and nullable so every pre-existing image question (Bank2's matrix/
 * spatial seeders) remains valid with these simply absent.
 *
 * `visual_complexity_score` is a documented heuristic (independent
 * transformation-dimension count x distractor plausibility), not a
 * validated psychometric difficulty measure - see SvgFigureBuilder.
 */
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
