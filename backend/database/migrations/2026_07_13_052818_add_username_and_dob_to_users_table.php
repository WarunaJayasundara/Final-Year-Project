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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->date('date_of_birth')->nullable()->after('email');
            // Marks accounts created by the synthetic demo-data generator so
            // research exports and analytics can exclude them by default -
            // never real research participants (see ResearchExportService).
            $table->boolean('is_demo_user')->default(false)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'date_of_birth', 'is_demo_user']);
        });
    }
};
