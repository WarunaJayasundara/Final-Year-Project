<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per-student spaced-repetition schedule for study notes - confirmed genuinely absent from the codebase before this migration. */
return new class extends Migration
{
    public function up()
    {
        Schema::create('study_note_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_note_id')->constrained()->cascadeOnDelete();
            $table->float('ease_factor')->default(2.5);
            $table->unsignedSmallInteger('interval_days')->default(1);
            $table->unsignedInteger('review_count')->default(0);
            $table->string('last_result', 10)->nullable(); // again|hard|good|easy
            $table->date('next_review_at');
            $table->timestamps();

            $table->unique(['user_id', 'study_note_id']);
            $table->index(['user_id', 'next_review_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('study_note_reviews');
    }
};
