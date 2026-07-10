<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'code' => 'memory',
                'name_en' => 'Memory',
                'name_si' => 'මතකය',
                'description_en' => 'Short-term recall, sequences, and pair association.',
                'description_si' => 'කෙටිකාල මතකය, අනුක්‍රම සහ යුගල ගැලපීම.',
                'icon' => 'brain',
            ],
            [
                'code' => 'logical_reasoning',
                'name_en' => 'Logical Reasoning',
                'name_si' => 'තාර්කික තර්කනය',
                'description_en' => 'Syllogisms, odd-one-out, and if-then deductions.',
                'description_si' => 'තර්ක ගැටළු, වෙනස් වූ අයිතමය සොයාගැනීම සහ නිගමන.',
                'icon' => 'puzzle',
            ],
            [
                'code' => 'numerical_ability',
                'name_en' => 'Numerical Ability',
                'name_si' => 'සංඛ්‍යාත්මක හැකියාව',
                'description_en' => 'Arithmetic, number series, ratios and percentages.',
                'description_si' => 'ගණිතමය ගැටළු, සංඛ්‍යා ශ්‍රේණි, අනුපාත සහ ප්‍රතිශත.',
                'icon' => 'calculator',
            ],
            [
                'code' => 'attention',
                'name_en' => 'Attention',
                'name_si' => 'අවධානය',
                'description_en' => 'Focus, rapid classification, and interference control.',
                'description_si' => 'අවධානය, වේගවත් වර්ගීකරණය සහ බාධා පාලනය.',
                'icon' => 'eye',
            ],
            [
                'code' => 'spatial_pattern',
                'name_en' => 'Spatial & Pattern Recognition',
                'name_si' => 'අවකාශීය හා රටා හඳුනාගැනීම',
                'description_en' => 'Matrix completion, shape rotation, and visual pattern sequences.',
                'description_si' => 'රටා සම්පූර්ණ කිරීම, හැඩ භ්‍රමණය සහ දෘශ්‍ය රටා අනුක්‍රම.',
                'icon' => 'shapes',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['code' => $category['code']], $category);
        }
    }
}
