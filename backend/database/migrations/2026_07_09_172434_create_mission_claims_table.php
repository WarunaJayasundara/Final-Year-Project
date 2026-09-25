<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Records a claimed daily/weekly mission reward. */
return new class extends Migration
{
    /**
     * Run the migrations.
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
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mission_claims');
    }
};
