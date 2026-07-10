<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-to-one exam preparation profile per student, replacing the minimal
 * users.target_exam_name/target_exam_date columns added for Phase 1 of the
 * ML readiness module (see the companion migration removing those columns).
 * exam_category is a fixed list of common Sri Lankan competitive government
 * examinations; exam_name holds the official name when exam_category is
 * "other" (or a custom label). daily_study_hours_target and target_score are
 * the student's own stated goals, used by StudyPlanService to size the
 * generated study plan - distinct from user_daily_checkins.study_hours,
 * which is what they *actually* reported doing.
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
        Schema::create('exam_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('exam_category', 50);
            $table->string('exam_name', 150)->nullable();
            $table->date('exam_date')->nullable();
            $table->decimal('daily_study_hours_target', 4, 1)->default(1.5);
            $table->unsignedTinyInteger('target_score')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exam_profiles');
    }
};
