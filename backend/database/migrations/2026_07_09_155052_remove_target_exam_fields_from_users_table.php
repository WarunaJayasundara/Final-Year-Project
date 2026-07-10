<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superseded by the exam_profiles table (see the sibling migration created
 * moments earlier) - these two columns were a minimal Phase-1 stopgap for
 * the days_until_exam ML feature, now replaced by a full exam profile.
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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['target_exam_name', 'target_exam_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('target_exam_name', 150)->nullable()->after('theta_se');
            $table->date('target_exam_date')->nullable()->after('target_exam_name');
        });
    }
};
