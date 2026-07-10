<?php

namespace Database\Seeders;

use App\Models\IqLevel;
use Illuminate\Database\Seeder;

class IqLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['level_number' => 1, 'name_en' => 'Level 1 - Foundation', 'name_si' => 'මට්ටම 1 - මූලික'],
            ['level_number' => 2, 'name_en' => 'Level 2 - Developing', 'name_si' => 'මට්ටම 2 - වර්ධනය වන'],
            ['level_number' => 3, 'name_en' => 'Level 3 - Proficient', 'name_si' => 'මට්ටම 3 - දක්ෂ'],
            ['level_number' => 4, 'name_en' => 'Level 4 - Advanced', 'name_si' => 'මට්ටම 4 - උසස්'],
            ['level_number' => 5, 'name_en' => 'Level 5 - Expert', 'name_si' => 'මට්ටම 5 - ප්‍රවීණ'],
        ];

        foreach ($levels as $level) {
            IqLevel::updateOrCreate(['level_number' => $level['level_number']], $level);
        }
    }
}
