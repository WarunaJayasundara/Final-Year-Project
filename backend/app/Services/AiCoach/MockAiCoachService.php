<?php

namespace App\Services\AiCoach;

use App\Contracts\AiCoachServiceInterface;
use App\Models\User;
use App\Services\Analytics\StudentContextService;

/**
 * Rule-based coach used until a Gemini API key is configured. No external
 * calls - it does simple keyword-intent matching on the student's message and
 * writes a bilingual, templated reply grounded in their live StudentContextService
 * snapshot, so it still feels personalized without any AI call or model training.
 */
class MockAiCoachService implements AiCoachServiceInterface
{
    private const GAME_BY_CATEGORY = [
        'memory' => ['memory_match', 'Memory Match', 'මතක ගැලපීම'],
        'numerical_ability' => ['math_rush', 'Mental Math Rush', 'මානසික ගණිත වේගය'],
        'logical_reasoning' => ['sequence_puzzle', 'Sequence Puzzle', 'අනුක්‍රම ප්‍රහේලිකාව'],
        'attention' => ['sequence_puzzle', 'Sequence Puzzle', 'අනුක්‍රම ප්‍රහේලිකාව'],
        'spatial_pattern' => ['sequence_puzzle', 'Sequence Puzzle', 'අනුක්‍රම ප්‍රහේලිකාව'],
    ];

    public function __construct(private StudentContextService $context)
    {
    }

    public function chat(User $user, string $message, array $history, string $locale): string
    {
        $ctx = $this->context->build($user);

        if (! $ctx['has_placement']) {
            return $locale === 'si'
                ? "ආයුබෝවන් {$ctx['name']}! මට ඔබට උදව් කිරීමට පෙර කරුණාකර ස්ථානගත කිරීමේ පරීක්ෂණය සම්පූර්ණ කරන්න - එවිට ඔබේ ප්‍රතිඵල මත පදනම් වූ උපදෙස් දිය හැක."
                : "Hi {$ctx['name']}! Please finish your placement test first - once that's done I can give you advice based on your actual results.";
        }

        $lower = mb_strtolower(trim($message));

        if ($lower === '' || $this->matchesAny($lower, ['hi', 'hello', 'hey', 'ආයුබෝ'])) {
            return $this->greeting($ctx, $locale);
        }

        if ($this->matchesAny($lower, ['iq', 'score', 'ලකුණු'])) {
            return $this->iqExplanation($ctx, $locale);
        }

        if ($this->matchesAny($lower, ['progress', 'how am i', 'doing', 'දියුණුව'])) {
            return $this->progressSummary($ctx, $locale);
        }

        if ($this->matchesAny($lower, ['game', 'play', 'ක්‍රීඩා'])) {
            return $this->gameSuggestion($ctx, $locale);
        }

        if ($this->matchesAny($lower, ['practice', 'improve', 'weak', 'focus', 'study', 'පුහුණු', 'දුර්වල'])) {
            return $this->practiceSuggestion($ctx, $locale);
        }

        return $this->fallback($ctx, $locale);
    }

    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function greeting(array $ctx, string $locale): string
    {
        $levelName = $locale === 'si' ? $ctx['level_name_si'] : $ctx['level_name_en'];

        return $locale === 'si'
            ? "ආයුබෝවන් {$ctx['name']}! ඔබ දැනට \"{$levelName}\" මට්ටමේ සිටිනවා. ඔබේ දියුණුව, පුහුණු කළ යුතු දේ, හෝ ක්‍රීඩා ගැන අහන්න."
            : "Hi {$ctx['name']}! You're currently at \"{$levelName}\" level. Ask me about your progress, what to practice next, or which game to try.";
    }

    private function iqExplanation(array $ctx, string $locale): string
    {
        if (! $ctx['iq_estimate']) {
            return $locale === 'si'
                ? 'IQ ලකුණු ගණනය කිරීමට තවම ප්‍රමාණවත් දත්ත නැත.'
                : "I don't have enough data yet to estimate your IQ score.";
        }

        $iq = $ctx['iq_estimate']['iq_score'];

        return $locale === 'si'
            ? "ඔබේ ඇස්තමේන්තුගත IQ ලකුණු {$iq} කි. මෙය ඔබේ ස්ථානගත කිරීමේ පරීක්ෂණ ප්‍රතිඵලය මත පදනම් වූ අපගමන IQ ලකුණුවක් (සාමාන්‍යය 100). මතක තබාගන්න, එය තනි මිනුමක් පමණි - අඛණ්ඩ පුහුණුවෙන් ඔබට තව දියුණු විය හැක!"
            : "Your estimated IQ score is {$iq}. This is a deviation-IQ score (mean 100) based on your placement test result - it's just one snapshot, and consistent practice can move it further!";
    }

    private function progressSummary(array $ctx, string $locale): string
    {
        $levelName = $locale === 'si' ? $ctx['level_name_si'] : $ctx['level_name_en'];
        $avg = $ctx['recent_avg_score_percent'];
        $avgText = $avg !== null ? "{$avg}%" : ($locale === 'si' ? 'තවම නැත' : 'not available yet');

        return $locale === 'si'
            ? "ඔබ දැනට \"{$levelName}\" මට්ටමේ සිටිනවා, දින {$ctx['streak_days']}ක අඛණ්ඩ පුහුණු දිනයන් සමඟින්. ඔබේ මෑත සැසිවල සාමාන්‍ය ලකුණු: {$avgText}. සම්පූර්ණ කළ සැසි: {$ctx['sessions_completed']}."
            : "You're at \"{$levelName}\" level with a {$ctx['streak_days']}-day streak. Your recent sessions average {$avgText}, across {$ctx['sessions_completed']} completed sessions so far.";
    }

    private function practiceSuggestion(array $ctx, string $locale): string
    {
        $weakest = $ctx['weakest_category'];

        if (! $weakest) {
            return $locale === 'si'
                ? 'ඔබේ දුර්වල ප්‍රවර්ගය හඳුනාගැනීමට තවත් සැසි කිහිපයක් සම්පූර්ණ කරන්න. දැනට, දෛනික අභ්‍යාසය දිගටම කරගෙන යන්න!'
                : 'Complete a few more sessions so I can spot your weakest area. For now, keep up with your daily practice!';
        }

        $name = $locale === 'si' ? $weakest['name_si'] : $weakest['name_en'];
        $accuracy = $weakest['accuracy_percent'];

        return $locale === 'si'
            ? "ඔබේ දත්ත අනුව, \"{$name}\" ({$accuracy}% නිරවද්‍යතාව) ඔබට වඩාත් අවධානය යොමු කළ යුතු ප්‍රවර්ගයයි. පුහුණු ටැබ් එකෙන් එය තෝරා අභ්‍යාස කරන්න."
            : "Based on your data, \"{$name}\" ({$accuracy}% accuracy) is the category that needs the most attention right now - pick it from Practice to drill it specifically.";
    }

    private function gameSuggestion(array $ctx, string $locale): string
    {
        $weakest = $ctx['weakest_category'];
        $code = $weakest['code'] ?? 'memory';
        [$gameCode, $gameNameEn, $gameNameSi] = self::GAME_BY_CATEGORY[$code] ?? self::GAME_BY_CATEGORY['memory'];
        $gameName = $locale === 'si' ? $gameNameSi : $gameNameEn;

        return $locale === 'si'
            ? "ඔබේ දුර්වල ප්‍රවර්ගයට ගැලපෙන ක්‍රීඩාව \"{$gameName}\" කි. එය නිතිපතා ක්‍රීඩා කිරීම ඔබේ අඛණ්ඩතාවයටත් උදව් වේ!"
            : "\"{$gameName}\" is the best match for your weakest category right now - playing it regularly also keeps your streak alive!";
    }

    private function fallback(array $ctx, string $locale): string
    {
        return $locale === 'si'
            ? "එය රසවත් ප්‍රශ්නයක්! මට ඔබේ දියුණුව, පුහුණු කළ යුතු දේ, ක්‍රීඩා, හෝ IQ ලකුණු ගැන අහන්න - මම ඔබේ සජීවී දත්ත භාවිතයෙන් පිළිතුරු දෙන්නම්."
            : "That's an interesting question! Try asking me about your progress, what to practice, which game to play, or your IQ score - I'll answer using your live data.";
    }
}
