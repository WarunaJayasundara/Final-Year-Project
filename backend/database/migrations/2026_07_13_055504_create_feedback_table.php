<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('ui_rating')->nullable();
            $table->unsignedTinyInteger('question_quality_rating')->nullable();
            $table->unsignedTinyInteger('sinhala_quality_rating')->nullable();
            $table->unsignedTinyInteger('usefulness_rating')->nullable();
            $table->text('comment')->nullable();
            $table->text('suggestion')->nullable();
            // Locale the student was using when they submitted - lets the
            // admin dashboard show EN/SI feedback proportion without
            // guessing from comment text.
            $table->enum('locale', ['en', 'si']);
            $table->enum('status', ['new', 'reviewed'])->default('new');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            // Set only by the synthetic demo-data generator (task: Synthetic
            // demo users) - excluded from research exports by default, see
            // ResearchExportService's include_demo_data flag.
            $table->boolean('is_demo_feedback')->default(false);
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
        });
        Schema::dropIfExists('feedback');
    }
};
