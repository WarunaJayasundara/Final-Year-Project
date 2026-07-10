<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger of every XP/coin award - the audit trail behind
 * users.xp/coins (which are denormalized running totals for fast reads).
 * reason is a short code (e.g. "session_complete", "badge:streak_7") used
 * for the "recent activity" list on the gamification dashboard.
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
        Schema::create('xp_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('xp_amount')->default(0);
            $table->integer('coin_amount')->default(0);
            $table->string('reason', 100);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('xp_ledger');
    }
};
