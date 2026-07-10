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
        Schema::create('session_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_session_id')->constrained('test_sessions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('selected_option_key', 1)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->text('ai_feedback_text')->nullable();
            $table->timestamp('ai_feedback_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['test_session_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('session_answers');
    }
};
