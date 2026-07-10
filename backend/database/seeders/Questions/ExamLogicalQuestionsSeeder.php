<?php

namespace Database\Seeders\Questions;

use Illuminate\Database\Seeder;

/**
 * Second wave of exam-authentic logical-reasoning questions modelled on Sri
 * Lankan aptitude papers: alphabet-position coding, direction-sense walks
 * (Pythagorean triples), categorical syllogisms, and number odd-one-out sets.
 */
class ExamLogicalQuestionsSeeder extends Seeder
{
    use BuildsQuestions;

    private const CODE_WORDS = [
        'DOG', 'PEN', 'SUN', 'MAP', 'CUP', 'BAG', 'HAT', 'JAR', 'KEY', 'LOG',
        'NET', 'OWL', 'PIG', 'RAT', 'VAN', 'WEB', 'ZIP', 'BOX', 'FAN', 'GEM',
        'ICE', 'JET', 'KIT', 'LIP', 'MUD', 'NUT', 'OAK', 'PAW', 'RIB', 'SAW',
        'TAP', 'URN', 'VET', 'WAX', 'YAK', 'ARM', 'BEE', 'COW', 'DEW', 'EGG',
    ];

    private const TRIPLES = [
        [3, 4, 5], [6, 8, 10], [5, 12, 13], [9, 12, 15], [8, 15, 17], [12, 16, 20],
        [7, 24, 25], [20, 21, 29], [10, 24, 26], [15, 20, 25], [18, 24, 30], [9, 40, 41],
    ];

    private const DIR_PAIRS = [
        // [dir1 en, dir1 si, dir2 en, dir2 si] - always perpendicular
        ['north', 'උතුරට', 'east', 'නැගෙනහිරට'],
        ['south', 'දකුණට', 'west', 'බටහිරට'],
        ['east', 'නැගෙනහිරට', 'south', 'දකුණට'],
        ['west', 'බටහිරට', 'north', 'උතුරට'],
    ];

    private const SYLLOGISM_TRIPLES = [
        // [A plural en, A si, B plural en, B si, X name]
        ['dogs', 'බල්ලෝ', 'animals', 'සතුන්', 'Rex'],
        ['engineers', 'ඉංජිනේරුවන්', 'graduates', 'උපාධිධාරීන්', 'Ruwan'],
        ['nurses', 'හෙදියන්', 'hospital workers', 'රෝහල් සේවකයන්', 'Malini'],
        ['cricketers', 'ක්‍රිකට් ක්‍රීඩකයන්', 'athletes', 'ක්‍රීඩකයන්', 'Sanath'],
        ['pilots', 'ගුවන් නියමුවන්', 'travellers', 'සංචාරකයන්', 'Nihal'],
        ['farmers', 'ගොවීන්', 'hard workers', 'වෙහෙස මහන්සි වන අය', 'Siripala'],
        ['singers', 'ගායකයන්', 'artists', 'කලාකරුවන්', 'Chamari'],
        ['lawyers', 'නීතිඥයන්', 'professionals', 'වෘත්තිකයන්', 'Mahesh'],
        ['drivers', 'රියදුරන්', 'licence holders', 'බලපත්‍රලාභීන්', 'Sunil'],
        ['teachers', 'ගුරුවරුන්', 'readers', 'පාඨකයන්', 'Kamala'],
        ['soldiers', 'සොල්දාදුවන්', 'brave people', 'නිර්භීත අය', 'Aruna'],
        ['doctors', 'වෛද්‍යවරුන්', 'science graduates', 'විද්‍යා උපාධිධාරීන්', 'Shanika'],
        ['carpenters', 'වඩුවන්', 'craftsmen', 'ශිල්පීන්', 'Piyal'],
        ['fishermen', 'ධීවරයන්', 'swimmers', 'පිහිනන්නන්', 'Somasiri'],
        ['painters', 'චිත්‍ර ශිල්පීන්', 'creative people', 'නිර්මාණශීලී අය', 'Dilki'],
        ['dancers', 'නර්තන ශිල්පීන්', 'රංගන ශිල්පීන්' === '' ? '' : 'performers', 'රංගන ශිල්පීන්', 'Sandun'],
    ];

    public function run(): void
    {
        $rows = array_merge(
            $this->letterCodeQuestions(),
            $this->directionQuestions(),
            $this->syllogismQuestions(),
            $this->oddNumberOutQuestions(),
        );

        $this->insertRows('logical_reasoning', $rows);
    }

    /** @return array<int,array> */
    private function letterCodeQuestions(): array
    {
        $words = self::CODE_WORDS;
        mt_srand(32001);
        shuffle($words);

        $rows = [];
        $cursor = 0;

        // Scheme A: A=1..Z=26 position code (example CAT = 3-1-20).
        foreach ([3 => 15, 4 => 15, 5 => 10] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                $word = $words[$cursor++];
                $code = $this->positionCode($word, false);
                $rows[] = $this->codeRow($level, $word, $code, false);
            }
        }

        // Scheme B: reverse alphabet (A=26..Z=1), example CAT = 24-26-7.
        foreach ([5 => 20] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                $word = $words[$cursor % count($words)];
                $cursor++;
                $code = $this->positionCode($word, true);
                $rows[] = $this->codeRow($level, $word, $code, true);
            }
        }

        return $rows;
    }

    private function positionCode(string $word, bool $reverse): string
    {
        $nums = [];
        foreach (str_split($word) as $ch) {
            $pos = ord($ch) - 64;
            $nums[] = $reverse ? 27 - $pos : $pos;
        }

        return implode('-', $nums);
    }

    private function codeRow(int $level, string $word, string $answer, bool $reverse): array
    {
        $example = $reverse ? 'CAT is written as 24-26-7' : 'CAT is written as 3-1-20';
        $exampleSi = $reverse ? 'CAT යන්න 24-26-7 ලෙස ලියයි' : 'CAT යන්න 3-1-20 ලෙස ලියයි';
        $ruleEn = $reverse ? 'each letter is replaced by its position counted from Z (Z=1 ... A=26)' : 'each letter is replaced by its alphabet position (A=1 ... Z=26)';
        $ruleSi = $reverse ? 'සෑම අකුරක්ම Z සිට ගණන් කළ ස්ථානයෙන් (Z=1 ... A=26) ආදේශ වේ' : 'සෑම අකුරක්ම එහි අකාරාදී ස්ථානයෙන් (A=1 ... Z=26) ආදේශ වේ';

        $parts = array_map('intval', explode('-', $answer));
        $wrong1 = implode('-', array_map(fn ($n) => $n + 1, $parts));
        $wrong2 = implode('-', array_reverse($parts));
        $wrong3 = implode('-', array_map(fn ($n) => max(1, $n - 1), $parts));

        $values = [$answer, $wrong1, $wrong2, $wrong3];
        // A reversed palindrome-ish code could collide with the answer; nudge duplicates.
        $values = array_values(array_unique($values));
        $bump = 2;
        while (count($values) < 4) {
            $values[] = implode('-', array_map(fn ($n) => $n + $bump, $parts));
            $values = array_values(array_unique($values));
            $bump++;
        }

        mt_srand(crc32('code-'.$word.($reverse ? '-r' : '')));
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];

        return [$level, 'mcq_text',
            "In a certain code, {$example}. How is {$word} written in that code?",
            "යම් කේතයක {$exampleSi}. එම කේතයේ {$word} ලියන්නේ කෙසේද?",
            $this->options($values, $values), $key,
            "In this code {$ruleEn}, so {$word} = {$answer}.",
            "මෙම කේතයේ {$ruleSi}, එබැවින් {$word} = {$answer}.", ];
    }

    /** @return array<int,array> */
    private function directionQuestions(): array
    {
        $combos = [];
        foreach (self::TRIPLES as $t) {
            foreach (self::DIR_PAIRS as $d) {
                $combos[] = [$t, $d];
            }
        }
        mt_srand(32002);
        shuffle($combos);

        $names = [['Kamal', 'කමල්'], ['Nimal', 'නිමල්'], ['Sunila', 'සුනිලා'], ['Ravi', 'රවි']];
        $rows = [];
        $cursor = 0;

        foreach ([3 => 16, 4 => 16, 5 => 16] as $level => $count) {
            for ($i = 0; $i < $count && $cursor < count($combos); $i++) {
                [[$a, $b, $c], [$d1en, $d1si, $d2en, $d2si]] = $combos[$cursor];
                [$nameEn, $nameSi] = $names[$cursor % count($names)];
                $cursor++;

                $rows[] = $this->numericChoiceRow(
                    $level,
                    "{$nameEn} walks {$a} km {$d1en}, then turns and walks {$b} km {$d2en}. How far is he/she from the starting point (in km)?",
                    "{$nameSi} කිලෝමීටර {$a}ක් {$d1si} ගමන් කර, හැරී කිලෝමීටර {$b}ක් {$d2si} ගමන් කරයි. ආරම්භක ස්ථානයේ සිට ඔහු/ඇය කොපමණ දුරින්ද (කිලෝමීටර)?",
                    $c,
                    [$a + $b, $c + 1, abs($b - $a), $c - 1],
                    "The two legs are perpendicular, so the distance is the hypotenuse: √({$a}² + {$b}²) = {$c} km.",
                    "ගමන් මාර්ග දෙක ලම්බකව ඇති බැවින් දුර කර්ණය වේ: √({$a}² + {$b}²) = කිලෝමීටර {$c}.",
                    "dir-{$a}-{$b}-{$d1en}"
                );
            }
        }

        return $rows;
    }

    /** @return array<int,array> */
    private function syllogismQuestions(): array
    {
        $rows = [];
        $index = 0;

        // Valid form ("All A are B; X is an A") and undetermined form ("Some A are B; X is an A")
        // alternate across the curated triples; each triple is used exactly twice, once per form.
        $slots = [];
        foreach ([3 => 10, 4 => 10, 5 => 12] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                $slots[] = $level;
            }
        }

        foreach ($slots as $slot => $level) {
            $t = self::SYLLOGISM_TRIPLES[$slot % count(self::SYLLOGISM_TRIPLES)];
            [$aEn, $aSi, $bEn, $bSi, $x] = $t;
            $valid = $slot < count(self::SYLLOGISM_TRIPLES);

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

            $valuesEn = array_merge([$answerEn], $othersEn);
            $valuesSi = array_merge([$answerSi], $othersSi);
            mt_srand(crc32('syl-'.$slot.'-'.$x));
            $order = [0, 1, 2, 3];
            shuffle($order);
            $shuffledEn = array_map(fn ($j) => $valuesEn[$j], $order);
            $shuffledSi = array_map(fn ($j) => $valuesSi[$j], $order);
            $key = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

            $rows[] = [$level, 'mcq_text', $stemEn, $stemSi, $this->options($shuffledEn, $shuffledSi), $key, $explEn, $explSi];
            $index++;
        }

        return $rows;
    }

    /** @return array<int,array> */
    private function oddNumberOutQuestions(): array
    {
        $squares = [4, 9, 16, 25, 36, 49, 64, 81, 100, 121, 144];
        $primes = [2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43];
        $sevens = [14, 21, 28, 35, 42, 49, 56, 63, 70, 77, 84, 91];
        $composites = [9, 15, 21, 25, 27, 33, 35, 39, 49, 51];

        $kinds = [
            ['pool' => $squares, 'oddPool' => [12, 20, 30, 45, 50, 65, 82, 99, 110, 122], 'ruleEn' => 'perfect squares', 'ruleSi' => 'පූර්ණ වර්ග සංඛ්‍යා'],
            ['pool' => $primes, 'oddPool' => $composites, 'ruleEn' => 'prime numbers', 'ruleSi' => 'ප්‍රථමක සංඛ්‍යා'],
            ['pool' => $sevens, 'oddPool' => [15, 22, 30, 37, 44, 52, 58, 66, 73, 80], 'ruleEn' => 'multiples of 7', 'ruleSi' => '7හි ගුණාකාර'],
        ];

        mt_srand(32004);
        $seen = [];
        $sets = [];
        $attempts = 0;

        while (count($sets) < 100 && $attempts < 5000) {
            $attempts++;
            $kind = $kinds[mt_rand(0, 2)];
            $pool = $kind['pool'];
            shuffle($pool);
            $members = array_slice($pool, 0, 3);
            $odd = $kind['oddPool'][mt_rand(0, count($kind['oddPool']) - 1)];
            $all = array_merge($members, [$odd]);
            sort($all);
            $signature = $kind['ruleEn'].':'.implode(',', $all);
            if (isset($seen[$signature]) || in_array($odd, $members, true)) {
                continue;
            }
            $seen[$signature] = true;
            $sets[] = [$members, $odd, $kind['ruleEn'], $kind['ruleSi']];
        }

        $rows = [];
        $cursor = 0;

        foreach ([2 => 20, 3 => 30, 4 => 25, 5 => 25] as $level => $count) {
            for ($i = 0; $i < $count && $cursor < count($sets); $i++) {
                [$members, $odd, $ruleEn, $ruleSi] = $sets[$cursor++];
                $values = array_merge($members, [$odd]);
                mt_srand(crc32('odd-'.$cursor.'-'.$odd));
                shuffle($values);
                $key = ['A', 'B', 'C', 'D'][array_search($odd, $values, true)];
                $labels = array_map('strval', $values);

                $rows[] = [$level, 'mcq_text',
                    'Which number does not belong with the others?',
                    'අනෙක් ඒවාට අයත් නොවන සංඛ්‍යාව කුමක්ද?',
                    $this->options($labels, $labels), $key,
                    "All the other numbers are {$ruleEn}; {$odd} is not.",
                    "අනෙක් සියලුම සංඛ්‍යා {$ruleSi} වේ; {$odd} එසේ නොවේ.", ];
            }
        }

        return $rows;
    }

    /** Builds one numeric-answer mcq row (shared with direction questions). */
    private function numericChoiceRow(int $level, string $textEn, string $textSi, int $answer, array $distractorCandidates, string $explEn, string $explSi, string $seedKey): array
    {
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

        return [$level, 'mcq_text', $textEn, $textSi, $this->options($labels, $labels), $key, $explEn, $explSi];
    }
}
