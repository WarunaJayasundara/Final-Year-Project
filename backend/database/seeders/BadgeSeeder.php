<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

/**
 * Fixed achievement catalog evaluated live by BadgeService. Deliberately
 * spans onboarding, streak, mastery/volume, and cross-feature milestones
 * (exam_ready ties into the Phase-1 ML readiness model, study_planner ties
 * into the Phase-2 study planner) so gamification reinforces the platform's
 * other features rather than sitting alongside them as an unrelated bolt-on.
 */
class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'code' => 'first_placement',
                'name_en' => 'First Steps',
                'name_si' => 'පළමු පියවර',
                'description_en' => 'Complete your placement test.',
                'description_si' => 'ඔබේ ස්ථානගත කිරීමේ පරීක්ෂණය සම්පූර්ණ කරන්න.',
                'icon' => 'footprints',
                'xp_reward' => 50,
                'coin_reward' => 20,
            ],
            [
                'code' => 'streak_3',
                'name_en' => 'Getting Started',
                'name_si' => 'ආරම්භය',
                'description_en' => 'Reach a 3-day practice streak.',
                'description_si' => 'දින 3ක අඛණ්ඩ පුහුණු ගණනයකට ළඟා වන්න.',
                'icon' => 'flame',
                'xp_reward' => 20,
                'coin_reward' => 10,
            ],
            [
                'code' => 'streak_7',
                'name_en' => 'Week Warrior',
                'name_si' => 'සතියේ රණශූරයා',
                'description_en' => 'Reach a 7-day practice streak.',
                'description_si' => 'දින 7ක අඛණ්ඩ පුහුණු ගණනයකට ළඟා වන්න.',
                'icon' => 'flame',
                'xp_reward' => 50,
                'coin_reward' => 25,
            ],
            [
                'code' => 'streak_14',
                'name_en' => 'Fortnight Focus',
                'name_si' => 'සති දෙකේ අවධානය',
                'description_en' => 'Reach a 14-day practice streak.',
                'description_si' => 'දින 14ක අඛණ්ඩ පුහුණු ගණනයකට ළඟා වන්න.',
                'icon' => 'flame',
                'xp_reward' => 100,
                'coin_reward' => 50,
            ],
            [
                'code' => 'streak_30',
                'name_en' => 'Unstoppable',
                'name_si' => 'නොනැවතිය හැකි',
                'description_en' => 'Reach a 30-day practice streak.',
                'description_si' => 'දින 30ක අඛණ්ඩ පුහුණු ගණනයකට ළඟා වන්න.',
                'icon' => 'flame',
                'xp_reward' => 250,
                'coin_reward' => 100,
            ],
            [
                'code' => 'perfect_score',
                'name_en' => 'Perfectionist',
                'name_si' => 'පරිපූර්ණවාදියා',
                'description_en' => 'Score 100% on any test session.',
                'description_si' => 'ඕනෑම පරීක්ෂණ සැසියකින් 100% ලකුණු ලබා ගන්න.',
                'icon' => 'star',
                'xp_reward' => 30,
                'coin_reward' => 15,
            ],
            [
                'code' => 'questions_100',
                'name_en' => 'Century Club',
                'name_si' => 'සියයේ සමාජිකයා',
                'description_en' => 'Answer 100 questions in total.',
                'description_si' => 'මුළුමනින් ප්‍රශ්න 100කට පිළිතුරු දෙන්න.',
                'icon' => 'target',
                'xp_reward' => 40,
                'coin_reward' => 20,
            ],
            [
                'code' => 'questions_500',
                'name_en' => 'Question Master',
                'name_si' => 'ප්‍රශ්න ප්‍රවීණයා',
                'description_en' => 'Answer 500 questions in total.',
                'description_si' => 'මුළුමනින් ප්‍රශ්න 500කට පිළිතුරු දෙන්න.',
                'icon' => 'trophy',
                'xp_reward' => 150,
                'coin_reward' => 75,
            ],
            [
                'code' => 'level_3_reached',
                'name_en' => 'Rising Star',
                'name_si' => 'නැගී එන තරුව',
                'description_en' => 'Reach IQ Level 3.',
                'description_si' => 'IQ මට්ටම 3 ට ළඟා වන්න.',
                'icon' => 'trending-up',
                'xp_reward' => 60,
                'coin_reward' => 30,
            ],
            [
                'code' => 'level_5_reached',
                'name_en' => 'Peak Performer',
                'name_si' => 'ඉහළම ක්‍රියාකාරිත්වය',
                'description_en' => 'Reach IQ Level 5, the platform maximum.',
                'description_si' => 'වේදිකාවේ උපරිම IQ මට්ටම වන 5 ට ළඟා වන්න.',
                'icon' => 'crown',
                'xp_reward' => 150,
                'coin_reward' => 75,
            ],
            [
                'code' => 'game_explorer',
                'name_en' => 'Game Explorer',
                'name_si' => 'ක්‍රීඩා ගවේෂකයා',
                'description_en' => 'Play every mini-game at least once.',
                'description_si' => 'සෑම කුඩා ක්‍රීඩාවක්ම අවම වශයෙන් එක් වරක් ක්‍රීඩා කරන්න.',
                'icon' => 'gamepad-2',
                'xp_reward' => 40,
                'coin_reward' => 20,
            ],
            [
                'code' => 'high_scorer',
                'name_en' => 'High Scorer',
                'name_si' => 'ඉහළ ලකුණු ලබන්නා',
                'description_en' => 'Post a top-tier score in any mini-game.',
                'description_si' => 'ඕනෑම කුඩා ක්‍රීඩාවක ඉහළම ලකුණු මට්ටමක් ලබා ගන්න.',
                'icon' => 'zap',
                'xp_reward' => 50,
                'coin_reward' => 25,
            ],
            [
                'code' => 'exam_ready',
                'name_en' => 'Exam Ready',
                'name_si' => 'විභාගයට සූදානම්',
                'description_en' => 'Reach a "Ready" AI exam readiness prediction.',
                'description_si' => 'AI විභාග සූදානම් අනාවැකියෙන් "සූදානම්" තත්ත්වයට ළඟා වන්න.',
                'icon' => 'check-circle',
                'xp_reward' => 60,
                'coin_reward' => 30,
            ],
            [
                'code' => 'study_planner',
                'name_en' => 'Planner',
                'name_si' => 'සැලසුම්කරු',
                'description_en' => 'Set up your government exam profile and study plan.',
                'description_si' => 'ඔබේ රාජ්‍ය විභාග පැතිකඩ සහ අධ්‍යයන සැලැස්ම සකසන්න.',
                'icon' => 'calendar-check',
                'xp_reward' => 20,
                'coin_reward' => 10,
            ],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(['code' => $badge['code']], $badge);
        }
    }
}
