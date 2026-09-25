<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Source-traceability + quality-pipeline metadata for both the live bank (`questions`) and the draft staging table. */
return new class extends Migration
{
    public function up()
    {
        $addColumns = function (Blueprint $table) {
            $table->foreignId('source_document_id')->nullable()->after('id')
                ->constrained('source_documents')->nullOnDelete();
            $table->string('source_type', 30)->default('original');
            // original|book_inspired|past_paper_inspired|theory_derived
            $table->string('generation_method', 30)->default('manual');
            // manual|seeder|ai_mock|ai_gemini|admin_pdf_pipeline
            $table->text('learning_objective')->nullable();
            $table->text('difficulty_reason')->nullable();
            $table->float('quality_score')->nullable();
            $table->string('validation_status', 20)->default('draft');
            // draft|auto_validated|human_approved|rejected
        };

        Schema::table('questions', $addColumns);
        Schema::table('ai_generated_questions', $addColumns);

        // Pre-existing live questions were all manually reviewed/seeded and are
        // already serving students - backfill them to a settled state so the
        // new validation_status has meaning only for genuinely new content.
        DB::table('questions')->update([
            'source_type' => 'original',
            'generation_method' => 'manual',
            'validation_status' => 'human_approved',
        ]);
    }

    public function down()
    {
        $dropColumns = function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_document_id');
            $table->dropColumn([
                'source_type',
                'generation_method',
                'learning_objective',
                'difficulty_reason',
                'quality_score',
                'validation_status',
            ]);
        };

        Schema::table('questions', $dropColumns);
        Schema::table('ai_generated_questions', $dropColumns);
    }
};
