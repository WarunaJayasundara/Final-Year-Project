<?php

namespace Database\Seeders\Questions\Bank2;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Competitive-exam logical + verbal reasoning bank (~1,300 questions).
 * Archetypes: shift ciphers, alphabet-position codes, direction-sense
 * walks, categorical syllogisms, number classification (odd one out),
 * letter series, number analogies, blood relations, and ranking/position
 * puzzles. All bilingual frames reuse sentences already verified in the
 * existing seeders; the three genuinely new Sinhala words needed by the
 * ranking archetype (වම්, දකුණු, පසින්) are individually reviewed and
 * whitelisted in tools/validate_sinhala.py. Word pools are disjoint from
 * the Advanced/Exam seeders, and a run-time check against active question
 * text skips any residual cross-seeder duplicate.
 */
class LogicalVerbalBank2Seeder extends Seeder
{
    use BuildsQuestions;

    /** 6-letter cipher words - deliberately disjoint from AdvancedLogicalQuestionsSeeder's list. */
    private const CIPHER_WORDS = [
        'PLANET', 'ENGINE', 'TEMPLE', 'WINDOW', 'BOTTLE', 'CAMERA', 'PILLOW', 'ROCKET',
        'TUNNEL', 'VELVET', 'MEADOW', 'FALCON', 'JUNGLE', 'LADDER', 'NECTAR', 'OYSTER',
        'PARROT', 'QUARRY', 'SADDLE', 'TAILOR', 'WALNUT', 'BASKET', 'CANDLE', 'DRAGON',
    ];

    /** 4-letter code words - disjoint from ExamLogicalQuestionsSeeder's 3-letter list. */
    private const CODE_WORDS = [
        'LAMP', 'FISH', 'BIRD', 'MOON', 'STAR', 'TREE', 'DOOR', 'ROAD', 'BOOK', 'RAIN',
        'WIND', 'FIRE', 'SAND', 'GOLD', 'IRON', 'MILK', 'RICE', 'SALT', 'SHIP', 'KITE',
        'DRUM', 'BELL', 'CAKE', 'LEAF', 'ROCK', 'WAVE', 'CORN', 'DUST', 'FROG', 'GATE',
    ];

    private const TRIPLES = [
        [3, 4, 5], [6, 8, 10], [5, 12, 13], [9, 12, 15], [8, 15, 17], [12, 16, 20],
        [7, 24, 25], [20, 21, 29], [10, 24, 26], [15, 20, 25], [18, 24, 30], [9, 40, 41],
    ];

    private const DIR_PAIRS = [
        ['north', 'උතුරට', 'east', 'නැගෙනහිරට'],
        ['south', 'දකුණට', 'west', 'බටහිරට'],
        ['east', 'නැගෙනහිරට', 'south', 'දකුණට'],
        ['west', 'බටහිරට', 'north', 'උතුරට'],
    ];

    private const NAMES = [['Kamal', 'කමල්'], ['Nimal', 'නිමල්'], ['Sunila', 'සුනිලා'], ['Ravi', 'රවි']];

    /** [A plural en, A si, B plural en, B si] - the X name is now a free parameter for volume. */
    private const SYLLOGISM_TRIPLES = [
        ['dogs', 'බල්ලෝ', 'animals', 'සතුන්'],
        ['engineers', 'ඉංජිනේරුවන්', 'graduates', 'උපාධිධාරීන්'],
        ['nurses', 'හෙදියන්', 'hospital workers', 'රෝහල් සේවකයන්'],
        ['cricketers', 'ක්‍රිකට් ක්‍රීඩකයන්', 'athletes', 'ක්‍රීඩකයන්'],
        ['pilots', 'ගුවන් නියමුවන්', 'travellers', 'සංචාරකයන්'],
        ['farmers', 'ගොවීන්', 'hard workers', 'වෙහෙස මහන්සි වන අය'],
        ['singers', 'ගායකයන්', 'artists', 'කලාකරුවන්'],
        ['lawyers', 'නීතිඥයන්', 'professionals', 'වෘත්තිකයන්'],
        ['drivers', 'රියදුරන්', 'licence holders', 'බලපත්‍රලාභීන්'],
        ['teachers', 'ගුරුවරුන්', 'readers', 'පාඨකයන්'],
        ['soldiers', 'සොල්දාදුවන්', 'brave people', 'නිර්භීත අය'],
        ['doctors', 'වෛද්‍යවරුන්', 'science graduates', 'විද්‍යා උපාධිධාරීන්'],
        ['carpenters', 'වඩුවන්', 'craftsmen', 'ශිල්පීන්'],
        ['fishermen', 'ධීවරයන්', 'swimmers', 'පිහිනන්නන්'],
        ['painters', 'චිත්‍ර ශිල්පීන්', 'creative people', 'නිර්මාණශීලී අය'],
        ['dancers', 'නර්තන ශිල්පීන්', 'performers', 'රංගන ශිල්පීන්'],
    ];

    private const SYLLOGISM_NAMES = ['Ruwan', 'Malini', 'Sanath', 'Nihal', 'Chamari', 'Mahesh', 'Sunil', 'Kamala', 'Aruna', 'Shanika'];

    private array $seenTexts = [];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->shiftCiphers(),
            $this->positionCodes(),
            $this->directionWalks(),
            $this->syllogisms(),
            $this->oddNumberOut(),
            $this->letterSeries(),
            $this->numberAnalogies(),
            $this->rankingPuzzles(),
        ));

        $this->insertRows('logical_reasoning', $rows, [
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'psc_exam'],
            'cognitive_skill' => 'deductive-reasoning',
        ]);
    }

    // ---------------------------------------------------------------
    // Coding-decoding
    // ---------------------------------------------------------------

    private function shiftWord(string $word, int $shift): string
    {
        $out = '';
        foreach (str_split($word) as $char) {
            $pos = (ord($char) - 65 + $shift) % 26;
            $out .= chr(65 + ($pos < 0 ? $pos + 26 : $pos));
        }

        return $out;
    }

    /** @return array<int,array|null> */
    private function shiftCiphers(): array
    {
        $combos = [];
        $n = count(self::CIPHER_WORDS);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i !== $j) {
                    foreach ([1, 2, 3, 4, 5] as $shift) {
                        $combos[] = [$i, $j, $shift];
                    }
                }
            }
        }
        mt_srand(820001);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([2 => 60, 3 => 80, 4 => 80, 5 => 80] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$w1, $w2, $shift] = $combos[$cursor++];
                $word1 = self::CIPHER_WORDS[$w1];
                $word2 = self::CIPHER_WORDS[$w2];
                $cipher1 = $this->shiftWord($word1, $shift);
                $answer = $this->shiftWord($word2, $shift);

                $wrongShifts = array_values(array_diff([1, 2, 3, 4, 5, 6], [$shift]));
                mt_srand(crc32("b2ciph-{$w1}-{$w2}-{$shift}"));
                shuffle($wrongShifts);
                $wrongs = array_map(fn ($s) => $this->shiftWord($word2, $s), array_slice($wrongShifts, 0, 3));

                $rows[] = $this->textOptionRow(
                    $level,
                    "In a certain code, the word {$word1} is written as {$cipher1}. How would the word {$word2} be written in the same code?",
                    "යම් කේතයක දී {$word1} යන වචනය {$cipher1} ලෙස ලියැවේ. එම කේතයෙන්ම {$word2} යන වචනය ලියනු ලබන්නේ කෙසේද?",
                    $answer, $wrongs, $wrongs,
                    "Each letter is shifted forward by {$shift} position(s) in the alphabet, so {$word2} becomes {$answer}.",
                    "එක් එක් අකුර හෝඩියේ ස්ථාන {$shift}කින් ඉදිරියට මාරු කරන ලදී, එබැවින් {$word2}, {$answer} බවට පත් වේ.",
                    "b2ciph-{$w1}-{$w2}-{$shift}",
                    ['subcategory' => 'coding_decoding', 'solving_time_seconds' => 40 + $level * 12, 'bloom_level' => 'apply'],
                );
            }
        }

        return $rows;
    }

    /** @return array<int,array|null> */
    private function positionCodes(): array
    {
        $words = self::CODE_WORDS;
        mt_srand(820002);
        shuffle($words);

        $rows = [];
        $cursor = 0;
        foreach ([3 => 20, 4 => 20, 5 => 20] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                $word = $words[$cursor % count($words)];
                $reverse = $cursor >= count($words);
                $cursor++;

                $nums = [];
                foreach (str_split($word) as $ch) {
                    $pos = ord($ch) - 64;
                    $nums[] = $reverse ? 27 - $pos : $pos;
                }
                $answer = implode('-', $nums);

                $example = $reverse ? 'CAT is written as 24-26-7' : 'CAT is written as 3-1-20';
                $exampleSi = $reverse ? 'CAT යන්න 24-26-7 ලෙස ලියයි' : 'CAT යන්න 3-1-20 ලෙස ලියයි';
                $ruleEn = $reverse ? 'each letter is replaced by its position counted from Z (Z=1 ... A=26)' : 'each letter is replaced by its alphabet position (A=1 ... Z=26)';
                $ruleSi = $reverse ? 'සෑම අකුරක්ම Z සිට ගණන් කළ ස්ථානයෙන් (Z=1 ... A=26) ආදේශ වේ' : 'සෑම අකුරක්ම එහි අකාරාදී ස්ථානයෙන් (A=1 ... Z=26) ආදේශ වේ';

                $wrongs = [
                    implode('-', array_map(fn ($v) => $v + 1, $nums)),
                    implode('-', array_reverse($nums)),
                    implode('-', array_map(fn ($v) => max(1, $v - 1), $nums)),
                ];
                $wrongs = array_values(array_unique(array_diff($wrongs, [$answer])));
                $bump = 2;
                while (count($wrongs) < 3) {
                    $candidate = implode('-', array_map(fn ($v) => $v + $bump, $nums));
                    if ($candidate !== $answer && ! in_array($candidate, $wrongs, true)) {
                        $wrongs[] = $candidate;
                    }
                    $bump++;
                }

                $rows[] = $this->textOptionRow(
                    $level,
                    "In a certain code, {$example}. How is {$word} written in that code?",
                    "යම් කේතයක {$exampleSi}. එම කේතයේ {$word} ලියන්නේ කෙසේද?",
                    $answer, $wrongs, $wrongs,
                    "In this code {$ruleEn}, so {$word} = {$answer}.",
                    "මෙම කේතයේ {$ruleSi}, එබැවින් {$word} = {$answer}.",
                    "b2code-{$word}-".($reverse ? 'r' : 'n'),
                    ['subcategory' => 'letter_code', 'solving_time_seconds' => 45 + $level * 12, 'bloom_level' => 'apply'],
                );
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Direction sense
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function directionWalks(): array
    {
        $combos = [];
        foreach (self::TRIPLES as $ti => $t) {
            foreach (self::DIR_PAIRS as $di => $d) {
                foreach (array_keys(self::NAMES) as $ni) {
                    $combos[] = [$ti, $di, $ni];
                }
            }
        }
        mt_srand(820003);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([2 => 30, 3 => 40, 4 => 35, 5 => 35] as $level => $count) {
            for ($i = 0; $i < $count && $cursor < count($combos); $i++) {
                [$ti, $di, $ni] = $combos[$cursor++];
                [$a, $b, $c] = self::TRIPLES[$ti];
                [$d1en, $d1si, $d2en, $d2si] = self::DIR_PAIRS[$di];
                [$nameEn, $nameSi] = self::NAMES[$ni];

                $rows[] = $this->numericOptionRow(
                    $level,
                    "{$nameEn} walks {$a} km {$d1en}, then turns and walks {$b} km {$d2en}. How far is he/she from the starting point (in km)?",
                    "{$nameSi} කිලෝමීටර {$a}ක් {$d1si} ගමන් කර, හැරී කිලෝමීටර {$b}ක් {$d2si} ගමන් කරයි. ආරම්භක ස්ථානයේ සිට ඔහු/ඇය කොපමණ දුරින්ද (කිලෝමීටර)?",
                    $c,
                    [$a + $b, $c + 1, abs($b - $a), $c - 1],
                    "The two legs are perpendicular, so the distance is the hypotenuse: √({$a}² + {$b}²) = {$c} km.",
                    "ගමන් මාර්ග දෙක ලම්බකව ඇති බැවින් දුර කර්ණය වේ: √({$a}² + {$b}²) = කිලෝමීටර {$c}.",
                    "b2dir-{$ti}-{$di}-{$ni}",
                    ['subcategory' => 'direction_sense', 'solving_time_seconds' => 50 + $level * 12, 'bloom_level' => 'analyze'],
                );
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Syllogisms (verbal reasoning)
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function syllogisms(): array
    {
        $combos = [];
        foreach (array_keys(self::SYLLOGISM_TRIPLES) as $ti) {
            foreach ([true, false] as $valid) {
                foreach (array_keys(self::SYLLOGISM_NAMES) as $ni) {
                    $combos[] = [$ti, $valid, $ni];
                }
            }
        }
        mt_srand(820004);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([3 => 50, 4 => 50, 5 => 60] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$ti, $valid, $ni] = $combos[$cursor++];
                [$aEn, $aSi, $bEn, $bSi] = self::SYLLOGISM_TRIPLES[$ti];
                $x = self::SYLLOGISM_NAMES[$ni];

                if ($valid) {
                    $stemEn = "All {$aEn} are {$bEn}. {$x} is one of the {$aEn}. Which conclusion is definitely correct?";
                    $stemSi = "සියලුම {$aSi} {$bSi} වේ. {$x} {$aSi}ගෙන් කෙනෙකි. නිසැකවම නිවැරදි නිගමනය කුමක්ද?";
                    $answerEn = "{$x} is one of the {$bEn}.";
                    $answerSi = "{$x} {$bSi}ගෙන් කෙනෙකි.";
                    $othersEn = ["{$x} is not one of the {$bEn}.", "No {$bEn} are {$aEn}.", 'It cannot be determined.'];
                    $othersSi = ["{$x} {$bSi}ගෙන් කෙනෙක් නොවේ.", "කිසිදු {$bSi} කෙනෙක් {$aSi} නොවේ.", 'තීරණය කළ නොහැක.'];
                    $explEn = "Since every one of the {$aEn} is one of the {$bEn}, and {$x} is one of the {$aEn}, {$x} must be one of the {$bEn}.";
                    $explSi = "සියලුම {$aSi} {$bSi} වන අතර {$x} {$aSi}ගෙන් කෙනෙක් බැවින්, {$x} අනිවාර්යයෙන්ම {$bSi}ගෙන් කෙනෙකි.";
                } else {
                    $stemEn = "Some {$aEn} are {$bEn}. {$x} is one of the {$aEn}. Which conclusion is definitely correct?";
                    $stemSi = "සමහර {$aSi} {$bSi} වේ. {$x} {$aSi}ගෙන් කෙනෙකි. නිසැකවම නිවැරදි නිගමනය කුමක්ද?";
                    $answerEn = "It cannot be determined whether {$x} is one of the {$bEn}.";
                    $answerSi = "{$x} {$bSi}ගෙන් කෙනෙක්ද යන්න තීරණය කළ නොහැක.";
                    $othersEn = ["{$x} is definitely one of the {$bEn}.", "{$x} is definitely not one of the {$bEn}.", "All {$aEn} are {$bEn}."];
                    $othersSi = ["{$x} නිසැකවම {$bSi}ගෙන් කෙනෙකි.", "{$x} නිසැකවම {$bSi}ගෙන් කෙනෙක් නොවේ.", "සියලුම {$aSi} {$bSi} වේ."];
                    $explEn = "\"Some\" only tells us that at least one of the {$aEn} is one of the {$bEn} - {$x} may or may not be among them.";
                    $explSi = "\"සමහර\" යනුවෙන් කියවෙන්නේ {$aSi}ගෙන් අවම වශයෙන් කෙනෙක් {$bSi} බව පමණි - {$x} ඔවුන් අතර සිටිය හැක, නොසිටිය හැක.";
                }

                $rows[] = $this->textOptionRow(
                    $level, $stemEn, $stemSi, $answerEn, $othersEn, $othersSi, $explEn, $explSi,
                    "b2syl-{$ti}-".(int) $valid."-{$ni}",
                    ['subcategory' => 'syllogisms', 'solving_time_seconds' => 45 + $level * 12,
                        'bloom_level' => 'evaluate', 'exam_tags' => ['verbal_reasoning', 'gov_aptitude', 'psc_exam'],
                        'cognitive_skill' => 'deductive-reasoning'],
                    $answerSi,
                );
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Number classification (odd one out)
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function oddNumberOut(): array
    {
        $kinds = [
            ['pool' => [4, 9, 16, 25, 36, 49, 64, 81, 100, 121, 144, 169, 196], 'oddPool' => [12, 20, 30, 45, 50, 65, 82, 99, 110, 130, 150, 172], 'ruleEn' => 'perfect squares', 'ruleSi' => 'පූර්ණ වර්ග සංඛ්‍යා'],
            ['pool' => [2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43, 47, 53], 'oddPool' => [9, 15, 21, 25, 27, 33, 35, 39, 49, 51, 55, 57], 'ruleEn' => 'prime numbers', 'ruleSi' => 'ප්‍රථමක සංඛ්‍යා'],
            ['pool' => [14, 21, 28, 35, 42, 49, 56, 63, 70, 77, 84, 91, 98], 'oddPool' => [15, 22, 30, 37, 44, 52, 58, 66, 73, 80, 88, 95], 'ruleEn' => 'multiples of 7', 'ruleSi' => '7හි ගුණාකාර'],
            ['pool' => [18, 27, 36, 45, 54, 63, 72, 81, 90, 99, 108, 117], 'oddPool' => [20, 28, 35, 46, 55, 64, 75, 82, 91, 100, 110], 'ruleEn' => 'multiples of 9', 'ruleSi' => '9හි ගුණාකාර'],
            ['pool' => [12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72, 78], 'oddPool' => [14, 20, 26, 32, 38, 44, 50, 56, 62, 68, 74], 'ruleEn' => 'multiples of 6', 'ruleSi' => '6හි ගුණාකාර'],
            ['pool' => [16, 24, 32, 40, 48, 56, 64, 72, 80, 88, 96, 104], 'oddPool' => [18, 26, 34, 42, 50, 58, 66, 74, 82, 90, 98], 'ruleEn' => 'multiples of 8', 'ruleSi' => '8හි ගුණාකාර'],
        ];

        mt_srand(820005);
        $sets = [];
        $seen = [];
        $attempts = 0;
        while (count($sets) < 200 && $attempts < 20000) {
            $attempts++;
            $kind = $kinds[mt_rand(0, count($kinds) - 1)];
            $pool = $kind['pool'];
            shuffle($pool);
            $members = array_slice($pool, 0, 3);
            $odd = $kind['oddPool'][mt_rand(0, count($kind['oddPool']) - 1)];
            // The odd number must not accidentally satisfy the rule (e.g. a
            // multiple of 6 sneaking into the multiples-of-9 odd pool is
            // impossible by pool construction, but keep the guard explicit).
            if (in_array($odd, $kind['pool'], true) || in_array($odd, $members, true)) {
                continue;
            }
            $all = array_merge($members, [$odd]);
            sort($all);
            $signatureKey = $kind['ruleEn'].':'.implode(',', $all);
            if (isset($seen[$signatureKey])) {
                continue;
            }
            $seen[$signatureKey] = true;
            $sets[] = [$members, $odd, $kind['ruleEn'], $kind['ruleSi']];
        }

        $rows = [];
        $cursor = 0;
        foreach ([1 => 40, 2 => 40, 3 => 40, 4 => 30, 5 => 30] as $level => $count) {
            for ($i = 0; $i < $count && $cursor < count($sets); $i++) {
                [$members, $odd, $ruleEn, $ruleSi] = $sets[$cursor++];
                $values = array_merge($members, [$odd]);
                mt_srand(crc32('b2odd-'.$cursor.'-'.$odd));
                shuffle($values);
                $key = ['A', 'B', 'C', 'D'][array_search($odd, $values, true)];
                $labels = array_map('strval', $values);

                $textEn = 'Which number does not belong with the others: '.implode(', ', $labels).'?';
                if (isset($this->seenTexts[$textEn])) {
                    continue;
                }
                $this->seenTexts[$textEn] = true;

                $rows[] = [$level, 'mcq_text',
                    $textEn,
                    'අනෙක් ඒවාට අයත් නොවන සංඛ්‍යාව කුමක්ද: '.implode(', ', $labels).'?',
                    $this->options($labels, $labels), $key,
                    "All the other numbers are {$ruleEn}; {$odd} is not.",
                    "අනෙක් සියලුම සංඛ්‍යා {$ruleSi} වේ; {$odd} එසේ නොවේ.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'classification', 'solving_time_seconds' => 30 + $level * 10,
                        'bloom_level' => 'analyze', 'exam_tags' => ['verbal_reasoning', 'gov_aptitude'],
                        'cognitive_skill' => 'classification'],
                ];
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Letter series
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function letterSeries(): array
    {
        $rows = [];
        mt_srand(820006);

        foreach ([2 => 40, 3 => 50, 4 => 55, 5 => 55] as $level => $count) {
            $made = 0;
            $guard = 0;
            while ($made < $count && $guard < $count * 50) {
                $guard++;
                $start1 = mt_rand(0, 8);
                $start2 = mt_rand(0, 8) + 9;
                $start3 = mt_rand(0, 6) + 15;
                $step1 = mt_rand(1, $level >= 4 ? 3 : 2);
                $step2 = mt_rand(1, $level >= 4 ? 3 : 2);
                $step3 = mt_rand(1, 2);

                $letter = fn (int $v) => chr(65 + ($v % 26));
                $group = fn (int $i) => $letter($start1 + $i * $step1).$letter($start2 + $i * $step2).$letter($start3 + $i * $step3);

                $groups = [$group(0), $group(1), $group(2), $group(3), $group(4)];
                $answer = $groups[3];
                $sequenceDisplay = "{$groups[0]}, {$groups[1]}, {$groups[2]}, ..?.., {$groups[4]}";

                $textEn = "The following groups of letters follow a logical sequence. Which group correctly fills the blank? {$sequenceDisplay}";
                if (isset($this->seenTexts[$textEn])) {
                    continue;
                }

                $wrongs = [];
                $wGuard = 0;
                while (count($wrongs) < 3 && $wGuard < 30) {
                    $wGuard++;
                    $offset = mt_rand(1, 3);
                    $candidate = $letter($start1 + 3 * $step1 + ($wGuard % 2 === 0 ? $offset : -$offset))
                        .$letter($start2 + 3 * $step2 + ($wGuard % 3 === 0 ? $offset : 0))
                        .$letter($start3 + 3 * $step3);
                    if (! in_array($candidate, $groups, true) && ! in_array($candidate, $wrongs, true) && $candidate !== $answer) {
                        $wrongs[] = $candidate;
                    }
                }
                if (count($wrongs) < 3) {
                    continue;
                }
                $this->seenTexts[$textEn] = true;
                $made++;

                $values = array_merge([$answer], $wrongs);
                shuffle($values);
                $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];

                $rows[] = [$level, 'mcq_text',
                    $textEn,
                    "පහත අකුරු කාණ්ඩ තාර්කික අනුක්‍රමයක් අනුගමනය කරයි. හිස්තැන නිවැරදිව පුරවන කාණ්ඩය කුමක්ද? {$sequenceDisplay}",
                    $this->options($values, $values), $key,
                    "Each position in the group advances by its own fixed step through the alphabet; applying that pattern gives {$answer}.",
                    "කාණ්ඩයේ එක් එක් ස්ථානය හෝඩියේ ස්ථාවර පියවරකින් ඉදිරියට යයි; එම රටාව යෙදීමෙන් {$answer} ලැබේ.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'letter_series', 'solving_time_seconds' => 40 + $level * 12, 'bloom_level' => 'analyze'],
                ];
            }
            if ($made < $count) {
                throw new \RuntimeException("Letter series L{$level}: only {$made}/{$count} generated.");
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Number analogies (pair rule)
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function numberAnalogies(): array
    {
        $rules = [
            ['fn' => fn ($a) => $a * $a, 'desc' => 'second = first squared', 'range' => [4, 20]],
            ['fn' => fn ($a) => $a * $a + 1, 'desc' => 'second = first squared plus 1', 'range' => [3, 18]],
            ['fn' => fn ($a) => $a * $a - 1, 'desc' => 'second = first squared minus 1', 'range' => [3, 18]],
            ['fn' => fn ($a) => 2 * $a + 3, 'desc' => 'second = double the first plus 3', 'range' => [4, 40]],
            ['fn' => fn ($a) => 3 * $a - 2, 'desc' => 'second = triple the first minus 2', 'range' => [3, 30]],
            ['fn' => fn ($a) => $a * ($a + 1), 'desc' => 'second = first × (first + 1)', 'range' => [3, 15]],
            ['fn' => fn ($a) => 2 * $a - 1, 'desc' => 'second = double the first minus 1', 'range' => [5, 45]],
            ['fn' => fn ($a) => 3 * $a + 1, 'desc' => 'second = triple the first plus 1', 'range' => [3, 30]],
            ['fn' => fn ($a) => 4 * $a, 'desc' => 'second = four times the first', 'range' => [3, 25]],
            ['fn' => fn ($a) => 5 * $a + 2, 'desc' => 'second = five times the first plus 2', 'range' => [3, 20]],
        ];

        // Each combo: a rule + three first-numbers (two solved examples + the ask).
        $combos = [];
        foreach ($rules as $ri => $rule) {
            [$lo, $hi] = $rule['range'];
            for ($seed = 0; $seed < 30; $seed++) {
                mt_srand(820007 + $ri * 100 + $seed);
                $firsts = [];
                while (count($firsts) < 3) {
                    $v = mt_rand($lo, $hi);
                    if (! in_array($v, $firsts, true)) {
                        $firsts[] = $v;
                    }
                }
                $combos[] = [$ri, $firsts];
            }
        }
        mt_srand(820008);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([2 => 30, 3 => 40, 4 => 40, 5 => 40] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$ri, $firsts] = $combos[$cursor++];
                $rule = $rules[$ri];
                [$a1, $a2, $a3] = $firsts;
                $b1 = $rule['fn']($a1);
                $b2 = $rule['fn']($a2);
                $answer = $rule['fn']($a3);
                $grid = "({$a1}, {$b1})   ({$a2}, {$b2})   ({$a3}, ?)";

                $rows[] = $this->numericOptionRow(
                    $level,
                    "In each pair the second number follows the same rule. Find the missing number: {$grid}",
                    "සෑම කණ්ඩායමකම දෙවන සංඛ්‍යාව එකම රීතියක් අනුගමනය කරයි. නැතිවූ සංඛ්‍යාව සොයන්න: {$grid}",
                    $answer,
                    [$answer + 2, $answer - 2, $rule['fn']($a3 + 1), $a3 * 2],
                    "The rule is: {$rule['desc']}. Applying it to {$a3} gives {$answer}.",
                    "රීතිය අනුගමනය කිරීමෙන් නැතිවූ සංඛ්‍යාව {$answer} වේ.",
                    "b2ana-{$ri}-{$a1}-{$a2}-{$a3}",
                    ['subcategory' => 'number_analogy', 'solving_time_seconds' => 45 + $level * 12,
                        'bloom_level' => 'analyze', 'exam_tags' => ['verbal_reasoning', 'gov_aptitude'],
                        'cognitive_skill' => 'analogical-reasoning'],
                );
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Ranking / position puzzles
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function rankingPuzzles(): array
    {
        $combos = [];
        foreach (range(2, 15) as $x) {
            foreach (range(2, 15) as $y) {
                foreach (array_keys(self::NAMES) as $ni) {
                    $combos[] = [$x, $y, $ni];
                }
            }
        }
        mt_srand(820009);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([1 => 25, 2 => 25, 3 => 25, 4 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$x, $y, $ni] = $combos[$cursor++];
                // Level scales the row size.
                while ($level <= 2 && $x + $y > 18) {
                    [$x, $y, $ni] = $combos[$cursor++];
                }
                [$nameEn, $nameSi] = self::NAMES[$ni];
                $total = $x + $y - 1;

                $rows[] = $this->numericOptionRow(
                    $level,
                    "In a row of students, {$nameEn} is {$this->ordinal($x)} from the left and {$this->ordinal($y)} from the right. How many students are in the row?",
                    "{$nameSi} පේළියේ වම් පසින් {$x}වන ස්ථානයේ සහ දකුණු පසින් {$y}වන ස්ථානයේ සිටී. පේළියේ මුළු සිසුන් ගණන කීයද?",
                    $total,
                    [$x + $y, $x + $y - 2, $x + $y + 1, abs($x - $y) + 1],
                    "Total = (position from left) + (position from right) - 1 = {$x} + {$y} - 1 = {$total} (the person is counted twice).",
                    "මුළු සිසුන් ගණන = {$x} + {$y} - 1 = {$total} වේ.",
                    "b2rank-{$x}-{$y}-{$ni}",
                    ['subcategory' => 'ranking_order', 'solving_time_seconds' => 35 + $level * 10, 'bloom_level' => 'apply'],
                );
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Row builders
    // ---------------------------------------------------------------

    private function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return "{$n}{$suffix}";
    }

    /** Numeric answer + numeric distractors. */
    private function numericOptionRow(int $level, string $textEn, string $textSi, int $answer, array $distractorCandidates, string $explEn, string $explSi, string $seedKey, array $meta): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $distractors = [];
        foreach ($distractorCandidates as $candidate) {
            if ($candidate > 0 && $candidate !== $answer && ! in_array($candidate, $distractors, true)) {
                $distractors[] = $candidate;
            }
            if (count($distractors) === 3) {
                break;
            }
        }
        $bump = 2;
        while (count($distractors) < 3) {
            $candidate = $answer + $bump;
            if ($candidate !== $answer && ! in_array($candidate, $distractors, true)) {
                $distractors[] = $candidate;
            }
            $bump++;
        }

        $values = array_merge([$answer], $distractors);
        mt_srand(crc32($seedKey));
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];
        $labels = array_map('strval', $values);

        return [$level, 'mcq_text', $textEn, $textSi, $this->options($labels, $labels), $key, $explEn, $explSi,
            min(3, max(1, (int) ceil($level / 2))), $meta];
    }

    /**
     * Text answer + text distractors; when the answer/distractors have
     * distinct Sinhala renderings pass $answerSi and align $wrongsSi with
     * $wrongsEn, otherwise the English strings are reused for both locales
     * (appropriate for code words and letter groups).
     */
    private function textOptionRow(int $level, string $textEn, string $textSi, string $answerEn, array $wrongsEn, array $wrongsSi, string $explEn, string $explSi, string $seedKey, array $meta, ?string $answerSi = null): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $valuesEn = array_merge([$answerEn], $wrongsEn);
        $valuesSi = array_merge([$answerSi ?? $answerEn], $wrongsSi);
        $order = range(0, 3);
        mt_srand(crc32($seedKey));
        shuffle($order);
        $shuffledEn = array_map(fn ($idx) => $valuesEn[$idx], $order);
        $shuffledSi = array_map(fn ($idx) => $valuesSi[$idx], $order);
        $key = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        return [$level, 'mcq_text', $textEn, $textSi, $this->options($shuffledEn, $shuffledSi), $key, $explEn, $explSi,
            min(3, max(1, (int) ceil($level / 2))), $meta];
    }
}
