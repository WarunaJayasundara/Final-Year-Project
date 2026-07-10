<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed achievement catalog (seeded by BadgeSeeder), evaluated by
 * BadgeService after session completion, game scores, exam-profile setup,
 * and readiness predictions. xp_reward/coin_reward are granted the moment a
 * badge is newly earned (see user_badges).
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
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_en', 100);
            $table->string('name_si', 100);
            $table->string('description_en', 200);
            $table->string('description_si', 200);
            $table->string('icon', 50);
            $table->unsignedInteger('xp_reward')->default(0);
            $table->unsignedInteger('coin_reward')->default(0);
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
        Schema::dropIfExists('badges');
    }
};
