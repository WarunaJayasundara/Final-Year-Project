<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional real-exam structure, used to compute a target pace
 * (ExamProfile::targetSecondsPerQuestion()) and to size mock exams. All
 * nullable - the student can skip every one of these and the platform keeps
 * working exactly as before (target_score, the existing personal-goal field,
 * is untouched and distinct from pass_mark here).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('exam_profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('exam_total_questions')->nullable()->after('target_score');
            $table->unsignedSmallInteger('exam_duration_minutes')->nullable()->after('exam_total_questions');
            $table->unsignedTinyInteger('pass_mark')->nullable()->after('exam_duration_minutes');
            $table->boolean('negative_marking')->nullable()->after('pass_mark');
            $table->json('exam_sections')->nullable()->after('negative_marking');
        });
    }

    public function down()
    {
        Schema::table('exam_profiles', function (Blueprint $table) {
            $table->dropColumn(['exam_total_questions', 'exam_duration_minutes', 'pass_mark', 'negative_marking', 'exam_sections']);
        });
    }
};
