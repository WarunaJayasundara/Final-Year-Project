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
        Schema::create('test_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('session_type', ['placement', 'daily', 'practice']);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('level_id')->constrained('iq_levels');
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('level_before_id')->nullable()->constrained('iq_levels')->nullOnDelete();
            $table->foreignId('level_after_id')->nullable()->constrained('iq_levels')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'session_type', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('test_sessions');
    }
};
