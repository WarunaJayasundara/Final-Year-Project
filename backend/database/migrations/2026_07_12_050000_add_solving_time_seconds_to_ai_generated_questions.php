<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ai_generated_questions never got solving_time_seconds when questions gained it. */
return new class extends Migration
{
    public function up()
    {
        Schema::table('ai_generated_questions', function (Blueprint $table) {
            $table->unsignedSmallInteger('solving_time_seconds')->nullable()->after('difficulty_weight');
        });
    }

    public function down()
    {
        Schema::table('ai_generated_questions', function (Blueprint $table) {
            $table->dropColumn('solving_time_seconds');
        });
    }
};
