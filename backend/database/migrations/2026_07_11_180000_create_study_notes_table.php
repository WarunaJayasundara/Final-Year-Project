<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "self-learning" layer: teaching/study notes generated from admin-
 * uploaded theory-book source documents (never from past-paper-type
 * documents, which have no teaching content to summarize). Same human-in-
 * the-loop draft->review->publish gate as ai_generated_questions - a note
 * is never shown to students until an admin explicitly publishes it, since
 * a hallucinated "teaching" explanation is worse than none at all.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('study_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_document_id')->constrained('source_documents')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('subcategory', 60)->nullable();
            $table->string('title_en');
            $table->string('title_si');
            $table->text('content_en');
            $table->text('content_si');
            $table->json('key_concepts')->nullable();
            $table->string('generation_method', 20)->default('mock'); // mock|gemini
            $table->string('status', 20)->default('draft'); // draft|published|rejected
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'category_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('study_notes');
    }
};
