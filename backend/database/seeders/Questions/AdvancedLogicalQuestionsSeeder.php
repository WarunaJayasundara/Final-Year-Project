<?php

namespace Database\Seeders\Questions;

use Illuminate\Database\Seeder;

/**
 * Harder logical-reasoning questions modelled on real Sri Lankan
 * competitive exam papers: coding-decoding ciphers, blood relations,
 * custom-operator arithmetic, and letter series. Concentrated at
 * levels 3-5.
 */
class AdvancedLogicalQuestionsSeeder extends Seeder
{
    use BuildsQuestions;

    private const PER_LEVEL = ['3' => 5, '4' => 6, '5' => 6];
    private const BLOOD_PER_LEVEL = ['3' => 3, '4' => 3, '5' => 4];

    private const CIPHER_WORDS = [
        'FOLLOW', 'ATTACK', 'MARKET', 'GARDEN', 'WINTER', 'SILVER', 'ORANGE', 'YELLOW',
        'DOCTOR', 'JOURNEY', 'MOUNTAIN', 'RIVER', 'FOREST', 'CASTLE', 'BRIDGE', 'HARBOR',
        'VALLEY', 'DESERT', 'STUDENT', 'TEACHER',
    ];

    /** [premiseEn, premiseSi, correctEn, correctSi, wrong1En, wrong1Si, wrong2En, wrong2Si] */
    private const BLOOD_RELATIONS = [
        ["Pointing to a photograph, Kumar said, 'She is the daughter of my grandfather's only son.' Kumar has no brothers. How is the woman in the photograph related to Kumar?",
            "Kumar ඡායාරූපයක් පෙන්වමින්, 'ඇය මගේ සීයාගේ එකම පුතුගේ දුවයි' යැයි පැවසීය. Kumarට සහෝදරයන් නැත. ඡායාරූපයේ සිටින කාන්තාව Kumar හා සම්බන්ධ වන්නේ කෙසේද?",
            'Sister', 'සහෝදරිය', 'Mother', 'මව', 'Daughter', 'දුව'],
        ["Introducing a boy, a woman said, 'He is the son of my husband's sister.' How is the boy related to the woman?",
            "පිරිමි ළමයෙකු හඳුන්වා දෙමින් කාන්තාවක, 'ඔහු මගේ ස්වාමිපුරුෂයාගේ සහෝදරියගේ පුතායි' යැයි පැවසුවාය. එම පිරිමි ළමයා කාන්තාව හා සම්බන්ධ වන්නේ කෙසේද?",
            'Nephew', 'බෑණා', 'Son', 'පුත්', 'Brother', 'සහෝදරයා'],
        ["A is B's sister. C is B's mother. D is C's father. E is D's mother. How is A related to D?",
            "A යනු B ගේ සහෝදරියයි. C යනු B ගේ මවයි. D යනු C ගේ පියායි. E යනු D ගේ මවයි. A, D හා සම්බන්ධ වන්නේ කෙසේද?",
            'Granddaughter', 'මුණුබුරිය', 'Daughter', 'දුව', 'Niece', 'ලේලිය'],
        ["Introducing a man, a woman said, 'He is the only son of my mother's mother.' How is the man related to the woman?",
            "පිරිමියෙකු හඳුන්වා දෙමින් කාන්තාවක, 'ඔහු මගේ මවගේ මවගේ එකම පුතායි' යැයි පැවසුවාය. එම පිරිමියා කාන්තාව හා සම්බන්ධ වන්නේ කෙසේද?",
            'Maternal uncle', 'මාමා', 'Brother', 'සහෝදරයා', 'Father', 'පියා'],
        ["Pointing to a girl, Raj said, 'She is the daughter of my wife's mother's only son.' Raj's wife has no brothers other than that one. How is the girl related to Raj?",
            "දැරියක් පෙන්වමින් Raj, 'ඇය මගේ බිරිඳගේ මවගේ එකම පුතාගේ දුවයි' යැයි පැවසීය. එම දැරිය Raj හා සම්බන්ධ වන්නේ කෙසේද?",
            'Niece', 'ලේලිය', 'Daughter', 'දුව', 'Sister', 'සහෝදරිය'],
        ["A's father is B. B's sister is C. C's mother is D. D's husband is E. How is A related to E?",
            "A ගේ පියා B ය. B ගේ සහෝදරිය C ය. C ගේ මව D ය. D ගේ ස්වාමිපුරුෂයා E ය. A, E හා සම්බන්ධ වන්නේ කෙසේද?",
            'Grandchild', 'මුනුබුරා/මිණිබිරිය', 'Child', 'දරුවා', 'Nephew', 'බෑණා'],
        ["Pointing to a man, a woman said, 'His wife is the only daughter of my father.' How is the man related to the woman?",
            "පිරිමියෙකු පෙන්වමින් කාන්තාවක, 'ඔහුගේ බිරිඳ මගේ පියාගේ එකම දුවයි' යැයි පැවසුවාය. එම පිරිමියා කාන්තාව හා සම්බන්ධ වන්නේ කෙසේද?",
            'Husband', 'ස්වාමිපුරුෂයා', 'Brother', 'සහෝදරයා', 'Father', 'පියා'],
        ["Introducing a woman, a man said, 'She is the mother of my son's wife.' How is the woman related to the man?",
            "කාන්තාවක් හඳුන්වා දෙමින් පිරිමියෙක්, 'ඇය මගේ පුතාගේ බිරිඳගේ මවයි' යැයි පැවසීය. එම කාන්තාව පිරිමියා හා සම්බන්ධ වන්නේ කෙසේද?",
            "Son's mother-in-law", 'මස්සිනා මව', 'Wife', 'බිරිඳ', 'Sister', 'සහෝදරිය'],
        ["Introducing a lady, Ravi said, 'She is the daughter of my father's only sister.' How is the lady related to Ravi?",
            "කාන්තාවක් හඳුන්වා දෙමින් Ravi, 'ඇය මගේ පියාගේ එකම සහෝදරියගේ දුවයි' යැයි පැවසීය. එම කාන්තාව Ravi හා සම්බන්ධ වන්නේ කෙසේද?",
            'Cousin', 'ම්ස්සිනා/නැන්දම්මා දුව', 'Sister', 'සහෝදරිය', 'Aunt', 'නැන්දා'],
        ["A lady said, 'The boy standing there is the son of my maternal grandmother's only daughter.' The lady has no sisters. How is the boy related to the lady?",
            "කාන්තාවක්, 'එහි සිටින පිරිමි ළමයා මගේ මිත්තණියගේ එකම දුවගේ පුතායි' යැයි පැවසුවාය. එම කාන්තාවට සහෝදරියන් නැත. එම පිරිමි ළමයා කාන්තාව හා සම්බන්ධ වන්නේ කෙසේද?",
            'Brother', 'සහෝදරයා', 'Son', 'පුත්', 'Nephew', 'බෑණා'],
    ];

    /** [aCoef, bCoef, cCoef, dCoef] for oplus(x,y)=a.x+b.y and ominus(x,y)=c.x+d.y */
    private const OPERATOR_DEFS = [
        [2, -3, 3, 2],
        [1, 2, 2, -1],
        [3, -1, 1, 3],
        [2, 1, -2, 3],
        [1, -2, 2, 2],
        [3, 2, -1, 1],
    ];

    public function run(): void
    {
        $rows = [];

        $cipherCombos = $this->buildCipherCombos();
        $bloodCombos = $this->buildBloodCombos();
        $operatorSeed = 0;
        $letterSeed = 0;
        $cipherCursor = 0;
        $bloodCursor = 0;

        foreach (self::PER_LEVEL as $level => $count) {
            $level = (int) $level;
            for ($i = 0; $i < $count; $i++) {
                $rows[] = $this->renderCipher($level, $cipherCombos[$cipherCursor++]);
            }
            for ($i = 0; $i < $count; $i++) {
                $rows[] = $this->buildOperatorPuzzle($level, $operatorSeed++);
            }
            for ($i = 0; $i < $count; $i++) {
                $rows[] = $this->buildLetterSeries($level, $letterSeed++);
            }
        }

        foreach (self::BLOOD_PER_LEVEL as $level => $count) {
            $level = (int) $level;
            for ($i = 0; $i < $count; $i++) {
                $rows[] = $this->renderBloodRelation($level, $bloodCombos[$bloodCursor++]);
            }
        }

        // Guards against the same question text appearing across levels.
        $seen = [];
        $rows = array_values(array_filter($rows, function (array $row) use (&$seen) {
            if (isset($seen[$row[2]])) {
                return false;
            }
            $seen[$row[2]] = true;

            return true;
        }));

        $this->insertRows('logical_reasoning', $rows, [
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'al_common_general'],
            'cognitive_skill' => 'deductive-reasoning',
            'bloom_level' => 'analyze',
        ]);
    }

    private function shiftWord(string $word, int $shift): string
    {
        $out = '';
        foreach (str_split($word) as $char) {
            $pos = (ord($char) - 65 + $shift) % 26;
            $pos = $pos < 0 ? $pos + 26 : $pos;
            $out .= chr(65 + $pos);
        }

        return $out;
    }

    /** @return array<int,array{0:int,1:int,2:int}> [word1Idx, word2Idx, shift] */
    private function buildCipherCombos(): array
    {
        $combos = [];
        $n = count(self::CIPHER_WORDS);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    continue;
                }
                foreach ([1, 2, 3, 4, 5] as $shift) {
                    $combos[] = [$i, $j, $shift];
                }
            }
        }
        mt_srand(9600);
        shuffle($combos);

        return $combos;
    }

    private function renderCipher(int $level, array $combo): array
    {
        [$word1Idx, $word2Idx, $shift] = $combo;
        $word1 = self::CIPHER_WORDS[$word1Idx];
        $word2 = self::CIPHER_WORDS[$word2Idx];
        $cipher1 = $this->shiftWord($word1, $shift);
        $answer = $this->shiftWord($word2, $shift);

        $wrongShifts = array_values(array_diff([1, 2, 3, 4, 5], [$shift]));
        mt_srand(crc32(implode('-', $combo)));
        shuffle($wrongShifts);
        $wrongs = array_map(fn ($s) => $this->shiftWord($word2, $s), array_slice($wrongShifts, 0, 3));

        $values = array_merge([$answer], $wrongs);
        $order = range(0, 3);
        shuffle($order);
        $shuffled = array_map(fn ($i) => $values[$i], $order);
        $key = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        $options = $this->options($shuffled, $shuffled);

        return [$level, 'mcq_text',
            "In a certain code, the word {$word1} is written as {$cipher1}. How would the word {$word2} be written in the same code?",
            "යම් කේතයක දී {$word1} යන වචනය {$cipher1} ලෙස ලියැවේ. එම කේතයෙන්ම {$word2} යන වචනය ලියනු ලබන්නේ කෙසේද?",
            $options, $key,
            "Each letter is shifted forward by {$shift} position(s) in the alphabet, so {$word2} becomes {$answer}.",
            "එක් එක් අකුර හෝඩියේ ස්ථාන {$shift}කින් ඉදිරියට මාරු කරන ලදී, එබැවින් {$word2}, {$answer} බවට පත් වේ.", ];
    }

    /** @return array<int,int> shuffled indices into BLOOD_RELATIONS */
    private function buildBloodCombos(): array
    {
        $indices = array_keys(self::BLOOD_RELATIONS);
        mt_srand(9700);
        shuffle($indices);

        return $indices;
    }

    private function renderBloodRelation(int $level, int $idx): array
    {
        [$premiseEn, $premiseSi, $correctEn, $correctSi, $wrong1En, $wrong1Si, $wrong2En, $wrong2Si] = self::BLOOD_RELATIONS[$idx];

        $extraWrongPool = ['Cousin', 'Uncle', 'Aunt', 'Grandmother'];
        $extraWrongPoolSi = ['ම්ස්සිනා', 'මාමා', 'නැන්දා', 'ආච්චි'];
        $usedEn = [$correctEn, $wrong1En, $wrong2En];
        $poolIdx = $idx % 4;
        for ($attempt = 0; $attempt < 4; $attempt++) {
            if (! in_array($extraWrongPool[$poolIdx], $usedEn, true)) {
                break;
            }
            $poolIdx = ($poolIdx + 1) % 4;
        }
        $extraWrong = $extraWrongPool[$poolIdx];
        $extraWrongSi = $extraWrongPoolSi[$poolIdx];

        $values = [$correctEn, $wrong1En, $wrong2En, $extraWrong];
        $valuesSi = [$correctSi, $wrong1Si, $wrong2Si, $extraWrongSi];

        mt_srand(crc32('blood-'.$idx.'-'.$level));
        $order = range(0, 3);
        shuffle($order);
        $shuffledEn = array_map(fn ($i) => $values[$i], $order);
        $shuffledSi = array_map(fn ($i) => $valuesSi[$i], $order);
        $key = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        $options = $this->options($shuffledEn, $shuffledSi);

        return [$level, 'mcq_text', $premiseEn, $premiseSi, $options, $key,
            "Working through the family relationships step by step gives the answer: {$correctEn}.",
            "පවුල් සම්බන්ධතා පියවරෙන් පියවර විශ්ලේෂණය කිරීමෙන් ලැබෙන පිළිතුර: {$correctSi}.", ];
    }

    /** Custom-operator arithmetic: oplus(x,y)=ax+by, ominus(x,y)=cx+dy, evaluate P (+) (Q (-) R). */
    private function buildOperatorPuzzle(int $level, int $seed): array
    {
        mt_srand($level * 950000 + $seed * 61 + 47);

        [$a, $b, $c, $d] = self::OPERATOR_DEFS[$seed % count(self::OPERATOR_DEFS)];
        $p = mt_rand(-5, 5);
        $q = mt_rand(-5, 5);
        $r = mt_rand(-5, 5);

        $inner = $c * $q + $d * $r;
        $answer = $a * $p + $b * $inner;

        $distractors = [];
        $guard = 0;
        while (count($distractors) < 3 && $guard < 50) {
            $guard++;
            $delta = mt_rand(1, 6);
            $candidate = mt_rand(0, 1) ? $answer + $delta : $answer - $delta;
            if (! in_array($candidate, $distractors, true) && $candidate !== $answer) {
                $distractors[] = $candidate;
            }
        }

        $values = array_merge([$answer], $distractors);
        shuffle($values);
        [$options, $key] = [$this->options(array_map('strval', $values), array_map('strval', $values)),
            ['A', 'B', 'C', 'D'][array_search($answer, $values, true)]];

        $signB = $b >= 0 ? "+ {$b}y" : "- ".abs($b).'y';
        $signD = $d >= 0 ? "+ {$d}y" : "- ".abs($d).'y';

        $en = "For any two numbers x and y, the operations \u{2295} and \u{2296} are defined as: x \u{2295} y = {$a}x {$signB} and x \u{2296} y = {$c}x {$signD}. What is the value of {$p} \u{2295} ({$q} \u{2296} {$r})?";
        $si = "ඕනෑම සංඛ්‍යා දෙකක් x සහ y සඳහා, \u{2295} සහ \u{2296} ක්‍රියාවලි මෙසේ අර්ථ දක්වා ඇත: x \u{2295} y = {$a}x {$signB} සහ x \u{2296} y = {$c}x {$signD}. {$p} \u{2295} ({$q} \u{2296} {$r}) හි අගය කුමක්ද?";

        return [$level, 'mcq_text', $en, $si, $options, $key,
            "First compute {$q} \u{2296} {$r} = {$inner}, then {$p} \u{2295} {$inner} = {$answer}.",
            "පළමුව {$q} \u{2296} {$r} = {$inner} ගණනය කර, පසුව {$p} \u{2295} {$inner} = {$answer} ලෙස ගණනය කරන්න.", ];
    }

    /** Three-letter grouped series where each position advances by its own fixed step. */
    private function buildLetterSeries(int $level, int $seed): array
    {
        mt_srand($level * 960000 + $seed * 59 + 53);

        $start1 = mt_rand(0, 8);
        $start2 = mt_rand(0, 8) + 9;
        $start3 = mt_rand(0, 6) + 15;
        $step1 = mt_rand(1, 2);
        $step2 = mt_rand(1, 2);
        $step3 = mt_rand(1, 2);

        $letter = fn (int $n) => chr(65 + ($n % 26));
        $group = fn (int $i) => $letter($start1 + $i * $step1).$letter($start2 + $i * $step2).$letter($start3 + $i * $step3);

        $groups = [$group(0), $group(1), $group(2), $group(3), $group(4)];
        $answer = $groups[3];

        $wrongs = [];
        $guard = 0;
        while (count($wrongs) < 3 && $guard < 30) {
            $guard++;
            $offset = mt_rand(1, 3);
            $candidate = $letter($start1 + 3 * $step1 + ($guard % 2 === 0 ? $offset : -$offset))
                .$letter($start2 + 3 * $step2 + ($guard % 3 === 0 ? $offset : 0))
                .$letter($start3 + 3 * $step3);
            if (! in_array($candidate, $groups, true) && ! in_array($candidate, $wrongs, true)) {
                $wrongs[] = $candidate;
            }
        }

        $values = array_merge([$answer], $wrongs);
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];
        $options = $this->options($values, $values);

        $sequenceDisplay = "{$groups[0]}, {$groups[1]}, {$groups[2]}, ..?.., {$groups[4]}";

        return [$level, 'mcq_text',
            "The following groups of letters follow a logical sequence. Which group correctly fills the blank? {$sequenceDisplay}",
            "පහත අකුරු කාණ්ඩ තාර්කික අනුක්‍රමයක් අනුගමනය කරයි. හිස්තැන නිවැරදිව පුරවන කාණ්ඩය කුමක්ද? {$sequenceDisplay}",
            $options, $key,
            "Each position in the group advances by its own fixed step through the alphabet; applying that pattern gives {$answer}.",
            "කාණ්ඩයේ එක් එක් ස්ථානය හෝඩියේ ස්ථාවර පියවරකින් ඉදිරියට යයි; එම රටාව යෙදීමෙන් {$answer} ලැබේ.", ];
    }
}
