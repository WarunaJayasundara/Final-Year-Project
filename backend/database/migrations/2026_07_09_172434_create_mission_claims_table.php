<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records a claimed daily/weekly mission reward. Missions themselves are
 * defined in code (MissionService), not stored - only the claim (and its
 * period_key, e.g. "2026-07-09" for a daily mission or "2026-W28" for a
 * weekly one) is persisted, both to prevent double-claiming and to know
 * which missions are already claimed for the current period.
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
        Schema::create('mission_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mission_code', 50);
            $table->string('period_key', 20);
            $table->unsignedInteger('xp_awarded');
            $table->unsignedInteger('coin_awarded');
            $table->timestamp('claimed_at');
            $table->timestamps();

            $table->unique(['user_id', 'mission_code', 'period_key']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mission_claims');
    }
};
