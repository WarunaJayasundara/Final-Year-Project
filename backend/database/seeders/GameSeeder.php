<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'code' => 'memory_match',
                'name_en' => 'Memory Match',
                'name_si' => 'මතක ගැලපීම',
                'description_en' => 'Flip cards to find matching pairs as fast as you can.',
                'description_si' => 'හැකි ඉක්මනින් ගැලපෙන කාඩ්පත් සොයාගන්න.',
            ],
            [
                'code' => 'sequence_puzzle',
                'name_en' => 'Sequence Puzzle',
                'name_si' => 'අනුක්‍රම ප්‍රහේලිකාව',
                'description_en' => 'Spot the pattern and pick what comes next.',
                'description_si' => 'රටාව හඳුනාගෙන ඊළඟට එන දේ තෝරන්න.',
            ],
            [
                'code' => 'math_rush',
                'name_en' => 'Mental Math Rush',
                'name_si' => 'මානසික ගණිත වේගය',
                'description_en' => '60 seconds of rapid-fire arithmetic. How many can you solve?',
                'description_si' => 'තත්පර 60ක් තුළ වේගවත් ගණිත ගැටළු විසඳන්න.',
            ],
            [
                'code' => 'mental_rotation',
                'name_en' => 'Mental Rotation Challenge',
                'name_si' => 'මානසික භ්‍රමණ අභියෝගය',
                'description_en' => 'Pick the shape that is a true rotation of the target, not a mirror image.',
                'description_si' => 'ඉලක්ක හැඩයේ සැබෑ භ්‍රමණය වන හැඩය තෝරන්න, දර්පණ රූපය නොවේ.',
            ],
            [
                'code' => 'selective_attention',
                'name_en' => 'Selective Attention Challenge',
                'name_si' => 'තෝරාගත් අවධාන අභියෝගය',
                'description_en' => 'Spot and click the one odd symbol in the grid as fast as you can.',
                'description_si' => 'ජාලකයේ ඇති එකම වෙනස් සංකේතය හැකි ඉක්මනින් සොයාගෙන ක්ලික් කරන්න.',
            ],
        ];

        foreach ($games as $game) {
            Game::updateOrCreate(['code' => $game['code']], $game);
        }
    }
}
