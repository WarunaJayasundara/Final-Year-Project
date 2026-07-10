<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadata for the competitive-exam question bank redesign: a fine-grained
 * subcategory taxonomy (the 7-category exam taxonomy maps onto the 5 fixed
 * platform categories via these), an estimated solving time, Bloom's
 * taxonomy level, government-exam tags, and the cognitive skill assessed.
 * All nullable so the pre-existing bank and admin-authored questions remain
 * valid without backfill.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('subcategory', 60)->nullable()->index()->after('question_type');
            $table->unsignedSmallInteger('solving_time_seconds')->nullable()->after('difficulty_weight');
            $table->string('bloom_level', 20)->nullable()->after('solving_time_seconds');
            $table->json('exam_tags')->nullable()->after('bloom_level');
            $table->string('cognitive_skill', 60)->nullable()->after('exam_tags');
        });
    }

    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['subcategory', 'solving_time_seconds', 'bloom_level', 'exam_tags', 'cognitive_skill']);
        });
    }
};
