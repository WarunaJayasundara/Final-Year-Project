<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per prediction run, kept as a history (not just the latest value)
 * so the student dashboard can show an exam-readiness trend line, and so
 * admin analytics can compute cohort-wide readiness distributions over time.
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
        Schema::create('exam_readiness_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('features');
            $table->decimal('readiness_percent', 5, 2);
            $table->enum('readiness_label', ['ready', 'almost_ready', 'needs_improvement', 'high_risk']);
            $table->json('reasons');
            $table->string('model_version', 50);
            $table->timestamp('predicted_at');
            $table->timestamps();

            $table->index(['user_id', 'predicted_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exam_readiness_predictions');
    }
};
