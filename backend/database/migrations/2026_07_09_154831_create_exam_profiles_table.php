<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One-to-one exam preparation profile per student. */
return new class extends Migration
{
    /**
     * Run the migrations.
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
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exam_profiles');
    }
};
