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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('iq_levels')->cascadeOnDelete();
            $table->enum('question_type', ['mcq_text', 'mcq_image'])->default('mcq_text');
            $table->text('question_text_en');
            $table->text('question_text_si');
            $table->string('image_path')->nullable();
            $table->json('options');
            $table->string('correct_option_key', 1);
            $table->text('explanation_en')->nullable();
            $table->text('explanation_si')->nullable();
            $table->unsignedTinyInteger('difficulty_weight')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category_id', 'level_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('questions');
    }
};
