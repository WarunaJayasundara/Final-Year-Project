<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-reported daily check-ins (study hours, motivation, attendance).
 * These three ML-model inputs have no objective source elsewhere in the
 * platform (no screen-time instrumentation, no physical attendance system),
 * so they are captured directly from the student via a short daily form
 * rather than fabricated - FeatureExtractionService falls back to neutral
 * defaults when a student hasn't checked in.
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
        Schema::create('user_daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('checkin_date');
            $table->decimal('study_hours', 4, 1)->default(0);
            $table->unsignedTinyInteger('motivation_score')->default(5);
            $table->boolean('attended')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'checkin_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_daily_checkins');
    }
};
