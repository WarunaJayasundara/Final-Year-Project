<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            IqLevelSeeder::class,
            CategorySeeder::class,
            GameSeeder::class,
            SuperAdminSeeder::class,
            QuestionSeeder::class,
            BadgeSeeder::class,
        ]);
    }
}
