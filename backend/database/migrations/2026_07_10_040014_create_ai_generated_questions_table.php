<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draft staging table for AI-generated question candidates - never served to
 * students directly. An admin must explicitly approve a draft (which copies
 * it into the real `questions` table) or reject it. This human-in-the-loop
 * gate exists because generated content feeds a real assessment instrument,
 * where a hallucinated wrong "correct answer" would silently corrupt a
 * student's ability estimate - unlike AI feedback text, which is advisory
 * and low-stakes if imperfect.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ai_generated_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('iq_levels');
            $table->string('question_type', 20)->default('mcq_text');
            $table->text('question_text_en');
            $table->text('question_text_si');
            $table->json('options');
            $table->string('correct_option_key', 1);
            $table->text('explanation_en')->nullable();
            $table->text('explanation_si')->nullable();
            $table->unsignedTinyInteger('difficulty_weight')->default(2);
            $table->string('source', 20)->default('mock');
            $table->string('status', 20)->default('pending');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('promoted_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ai_generated_questions');
    }
};
