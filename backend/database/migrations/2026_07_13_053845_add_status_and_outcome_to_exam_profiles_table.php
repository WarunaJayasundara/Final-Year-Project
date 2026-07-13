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
        // A student can now accumulate multiple exam_profiles rows over time
        // (one active + any number of completed/past ones for the "Past
        // Exams" history), so the original one-row-per-user unique
        // constraint no longer holds - replaced with a plain index. The
        // plain index must be created BEFORE the unique one is dropped,
        // since MySQL requires an index to always back the foreign key.
        Schema::table('exam_profiles', function (Blueprint $table) {
            $table->index('user_id', 'exam_profiles_user_id_index');
        });

        Schema::table('exam_profiles', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });

        Schema::table('exam_profiles', function (Blueprint $table) {
            $table->enum('status', ['active', 'completed'])->default('active')->after('user_id');
            $table->boolean('outcome_attended')->nullable()->after('exam_sections');
            $table->boolean('outcome_passed')->nullable()->after('outcome_attended');
            $table->integer('outcome_score')->nullable()->after('outcome_passed');
            $table->timestamp('outcome_recorded_at')->nullable()->after('outcome_score');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('exam_profiles', function (Blueprint $table) {
            $table->dropColumn(['status', 'outcome_attended', 'outcome_passed', 'outcome_score', 'outcome_recorded_at']);
            $table->unique('user_id');
            $table->dropIndex('exam_profiles_user_id_index');
        });
    }
};
