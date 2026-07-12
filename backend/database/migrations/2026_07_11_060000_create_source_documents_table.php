<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-uploaded reference PDFs (past papers, IQ/aptitude books, exam-prep
 * guides) used as topic/style/theory inspiration for question generation -
 * never a source of verbatim questions. `extracted_topics`/`detected_patterns`/
 * `extracted_theory_concepts` are populated by PdfIngestionService's
 * keyword-frequency heuristic against the existing taxonomy, not a claim of
 * deep NLP understanding - see PdfIngestionService docblock.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('source_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('document_type', 40)->default('other'); // past_paper|iq_book|exam_guide|theory_book|other
            $table->json('exam_type_tags')->nullable();
            $table->string('year', 10)->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->string('analysis_status', 20)->default('pending'); // pending|analyzing|analyzed|failed
            $table->json('extracted_topics')->nullable();
            $table->json('detected_patterns')->nullable();
            $table->json('extracted_theory_concepts')->nullable();
            $table->text('reliability_note')->nullable();
            $table->timestamps();

            $table->index(['analysis_status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('source_documents');
    }
};
