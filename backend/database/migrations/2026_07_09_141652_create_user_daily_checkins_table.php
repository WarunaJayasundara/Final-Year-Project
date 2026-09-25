<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Self-reported daily check-ins (study hours, motivation, attendance). */
return new class extends Migration
{
    /**
     * Run the migrations.
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
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_daily_checkins');
    }
};
