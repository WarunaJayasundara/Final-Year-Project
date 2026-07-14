<?php

namespace Database\Seeders\Questions\Bank2;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Competitive-grade memory and attention banks, using the original
 * seeders' verified bilingual frames but with an adult working-memory
 * load: digit spans up to 9, 4-6 item paired-association lists, 6-9 word
 * visual-search phrases, and 8-14 number scanning lists. Recall positions
 * are capped at 7 since the verified Sinhala ordinal corpus only goes up
 * to "seventh".
 */
class MemoryAttentionBank2Seeder extends Seeder
{
    use BuildsQuestions;

    private const ORDINAL_EN = ['first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh'];
    private const ORDINAL_SI = ['පළමු', 'දෙවන', 'තෙවන', 'සිව්වන', 'පස්වන', 'හයවන', 'හත්වන'];

    private const WORD_BANKS = [
        ['en' => ['Apple', 'Mango', 'Banana', 'Grape', 'Papaya', 'Orange'], 'si' => ['ඇපල්', 'අඹ', 'කෙසෙල්', 'මිදි', 'පැපොල්', 'දොඩම්']],
        ['en' => ['Lion', 'Tiger', 'Elephant', 'Deer', 'Rabbit', 'Monkey'], 'si' => ['සිංහයා', 'කොටියා', 'අලියා', 'මුවා', 'හාවා', 'වඳුරා']],
        ['en' => ['Red', 'Blue', 'Green', 'Yellow', 'Purple', 'Brown'], 'si' => ['රතු', 'නිල්', 'කොළ', 'කහ', 'දම්', 'දුඹුරු']],
        ['en' => ['Chair', 'Table', 'Lamp', 'Mirror', 'Shelf', 'Carpet'], 'si' => ['පුටුව', 'මේසය', 'ලාම්පුව', 'දර්පණය', 'රාක්කය', 'කාපට් එක']],
        ['en' => ['Doctor', 'Teacher', 'Farmer', 'Driver', 'Painter', 'Singer'], 'si' => ['වෛද්‍යවරයා', 'ගුරුවරයා', 'ගොවියා', 'රියදුරු', 'චිත්‍ර ශිල්පියා', 'ගායකයා']],
        ['en' => ['River', 'Mountain', 'Forest', 'Beach', 'Desert', 'Valley'], 'si' => ['ගඟ', 'කන්ද', 'වනාන්තරය', 'වෙරළ', 'කාන්තාරය', 'නිම්නය']],
        ['en' => ['Pencil', 'Eraser', 'Ruler', 'Notebook', 'Crayon', 'Scissors'], 'si' => ['පැන්සල', 'මකනය', 'පාලකය', 'සටහන් පොත', 'ක්‍රේයෝන්', 'කතුර']],
        ['en' => ['Cricket', 'Football', 'Tennis', 'Rugby', 'Hockey', 'Netball'], 'si' => ['ක්‍රිකට්', 'පාපන්දු', 'ටෙනිස්', 'රග්බි', 'හොකී', 'නෙට්බෝල්']],
    ];

    private const WORD_POOL = [
        'BANANA', 'APPLE', 'ORANGE', 'MANGO', 'PAPAYA', 'AVOCADO', 'ELEPHANT', 'ANTELOPE',
        'ALPACA', 'GIRAFFE', 'DOLPHIN', 'PENGUIN', 'KANGAROO', 'HAMSTER', 'CROCODILE', 'FLAMINGO',
        'OCTOPUS', 'BUTTERFLY', 'HEDGEHOG', 'PINEAPPLE', 'THUNDER', 'BLANKET', 'CUSHION', 'DIAMOND',
        'EMERALD', 'FOUNTAIN', 'GLACIER', 'HORIZON', 'JOURNAL', 'KINGDOM',
    ];

    private const LETTERS = ['A', 'E', 'I', 'O', 'R', 'S', 'T', 'N', 'L', 'D'];

    private const MISSPELL_BASES = [
        'THUNDER', 'BLANKET', 'CUSHION', 'DIAMOND', 'EMERALD', 'FOUNTAIN', 'GLACIER', 'HORIZON',
        'JOURNAL', 'KINGDOM', 'LIBRARY', 'MACHINE', 'OCTAGON', 'PYRAMID', 'SANDWICH', 'TRIANGLE',
        'UMBRELLA', 'VOLCANO', 'WHISTLE', 'COMPASS', 'FURNACE', 'HARVEST', 'LANTERN', 'MONSOON',
    ];

    private array $seenTexts = [];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $memoryRows = array_filter(array_merge(
            $this->digitSpans(),
            $this->pairAssociations(),
        ));
        $this->insertRows('memory', $memoryRows, [
            'exam_tags' => ['memory', 'gov_aptitude'],
            'cognitive_skill' => 'working-memory',
        ]);

        $attentionRows = array_filter(array_merge(
            $this->letterCounts(),
            $this->evenOddCounts(),
            $this->misspelledWords(),
        ));
        $this->insertRows('attention', $attentionRows, [
            'exam_tags' => ['attention_concentration', 'gov_aptitude', 'police_recruitment'],
            'cognitive_skill' => 'selective-attention',
        ]);
    }

    // ---------------------------------------------------------------
    // Memory: digit spans (5-9 digits, adult range)
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function digitSpans(): array
    {
        $rows = [];
        mt_srand(830001);

        foreach ([1 => 50, 2 => 50, 3 => 50, 4 => 50, 5 => 50] as $level => $count) {
            $length = $level + 4; // L1=5 ... L5=9 digits (classic adult digit-span range)
            $made = 0;
            $guard = 0;
            while ($made < $count && $guard < $count * 40) {
                $guard++;
                $digits = [];
                for ($i = 0; $i < $length; $i++) {
                    $digits[] = mt_rand(0, 9);
                }
                $askPosition = mt_rand(0, min(6, $length - 1)); // ordinal words verified up to "seventh"
                $correctValue = $digits[$askPosition];
                $sequenceStr = implode(', ', $digits);

                $textEn = "Memorize this sequence: {$sequenceStr}. What is the ".self::ORDINAL_EN[$askPosition].' number in the sequence?';
                if (isset($this->seenTexts[$textEn])) {
                    continue;
                }
                $this->seenTexts[$textEn] = true;
                $made++;

                $pool = array_values(array_diff(range(0, 9), [$correctValue]));
                shuffle($pool);
                $optionValues = array_slice(array_merge([$correctValue], $pool), 0, 4);
                shuffle($optionValues);
                $correctKey = ['A', 'B', 'C', 'D'][array_search($correctValue, $optionValues, true)];

                $rows[] = [$level, 'mcq_text',
                    $textEn,
                    "මෙම අනුක්‍රමය මතක තබා ගන්න: {$sequenceStr}. අනුක්‍රමයේ ".self::ORDINAL_SI[$askPosition].' අංකය කුමක්ද?',
                    $this->options(array_map('strval', $optionValues), array_map('strval', $optionValues)),
                    $correctKey,
                    "The sequence was {$sequenceStr}. The ".self::ORDINAL_EN[$askPosition]." number is {$correctValue}.",
                    "අනුක්‍රමය වූයේ {$sequenceStr} ".self::ORDINAL_SI[$askPosition]." අංකය {$correctValue} වේ.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'digit_span', 'solving_time_seconds' => 20 + $level * 8,
                        'bloom_level' => 'remember'],
                ];
            }
            if ($made < $count) {
                throw new \RuntimeException("Digit span L{$level}: only {$made}/{$count} generated.");
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Memory: paired associations (4-6 pairs)
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function pairAssociations(): array
    {
        $rows = [];
        mt_srand(830002);

        foreach ([1 => 50, 2 => 50, 3 => 50, 4 => 50, 5 => 50] as $level => $count) {
            $itemCount = min(6, 3 + (int) ceil($level / 2) + ($level >= 4 ? 1 : 0)); // L1-2: 4/5, L3: 5, L4-5: 6
            $made = 0;
            $guard = 0;
            while ($made < $count && $guard < $count * 40) {
                $guard++;
                $bank = self::WORD_BANKS[mt_rand(0, count(self::WORD_BANKS) - 1)];

                $indices = range(0, count($bank['en']) - 1);
                shuffle($indices);
                $indices = array_slice($indices, 0, $itemCount);
                $numbers = [];
                while (count($numbers) < $itemCount) {
                    $n = mt_rand(1, 99);
                    if (! in_array($n, $numbers, true)) {
                        $numbers[] = $n;
                    }
                }

                $pairsEn = [];
                $pairsSi = [];
                foreach ($indices as $i => $wordIndex) {
                    $pairsEn[] = "{$bank['en'][$wordIndex]}-{$numbers[$i]}";
                    $pairsSi[] = "{$bank['si'][$wordIndex]}-{$numbers[$i]}";
                }

                $askIndex = mt_rand(0, $itemCount - 1);
                $correctValue = $numbers[$askIndex];
                $askedWordEn = $bank['en'][$indices[$askIndex]];
                $askedWordSi = $bank['si'][$indices[$askIndex]];
                $listEn = implode(', ', $pairsEn);
                $listSi = implode(', ', $pairsSi);

                $textEn = "Memorize these pairs: {$listEn}. What number was paired with {$askedWordEn}?";
                if (isset($this->seenTexts[$textEn])) {
                    continue;
                }
                $this->seenTexts[$textEn] = true;
                $made++;

                $optionValues = $numbers;
                shuffle($optionValues);
                $optionValues = array_slice($optionValues, 0, 4);
                if (! in_array($correctValue, $optionValues, true)) {
                    $optionValues[0] = $correctValue;
                    shuffle($optionValues);
                }
                $correctKey = ['A', 'B', 'C', 'D'][array_search($correctValue, $optionValues, true)];

                $rows[] = [$level, 'mcq_text',
                    $textEn,
                    "මෙම යුගල මතක තබා ගන්න: {$listSi}. {$askedWordSi} සමඟ යුගල වූ අංකය කුමක්ද?",
                    $this->options(array_map('strval', $optionValues), array_map('strval', $optionValues)),
                    $correctKey,
                    "{$askedWordEn} was paired with {$correctValue}.",
                    "{$askedWordSi} සමඟ යුගල වූයේ {$correctValue} ය.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'paired_association', 'solving_time_seconds' => 25 + $level * 10,
                        'bloom_level' => 'remember'],
                ];
            }
            if ($made < $count) {
                throw new \RuntimeException("Pair association L{$level}: only {$made}/{$count} generated.");
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Attention: letter counting in long phrases
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function letterCounts(): array
    {
        $rows = [];
        mt_srand(830003);

        foreach ([1 => 40, 2 => 40, 3 => 40, 4 => 40, 5 => 40] as $level => $count) {
            $wordCount = $level + 4; // 5-9 words: a longer scan than the retired bank's 3-7
            $made = 0;
            $guard = 0;
            while ($made < $count && $guard < $count * 50) {
                $guard++;
                $pool = self::WORD_POOL;
                shuffle($pool);
                $words = array_slice($pool, 0, $wordCount);
                $letter = self::LETTERS[mt_rand(0, count(self::LETTERS) - 1)];
                $phrase = implode(' ', $words);
                $answer = substr_count($phrase, $letter);

                $textEn = "Count carefully: how many times does the letter '{$letter}' appear in \"{$phrase}\"?";
                if (isset($this->seenTexts[$textEn]) || $answer === 0) {
                    continue;
                }
                $this->seenTexts[$textEn] = true;
                $made++;

                $distractors = array_values(array_unique([$answer + 1, max(0, $answer - 1), $answer + 2, $answer + 3]));
                shuffle($distractors);
                $values = array_slice(array_merge([$answer], $distractors), 0, 4);
                shuffle($values);
                $labels = array_map('strval', $values);
                $correctKey = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];

                $rows[] = [$level, 'mcq_text',
                    $textEn,
                    "සැලකිල්ලෙන් ගණන් කරන්න: \"{$phrase}\" තුළ '{$letter}' අකුර කී වතාවක් දිස්වේද?",
                    $this->options($labels, $labels), $correctKey,
                    "The letter '{$letter}' appears {$answer} time(s) in the phrase.",
                    "'{$letter}' අකුර වාක්‍යයේ {$answer} වතාවක් පෙනී යයි.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'target_counting', 'solving_time_seconds' => 25 + $level * 10,
                        'bloom_level' => 'apply'],
                ];
            }
            if ($made < $count) {
                throw new \RuntimeException("Letter count L{$level}: only {$made}/{$count} generated.");
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Attention: even/odd scanning of long number lists
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function evenOddCounts(): array
    {
        $rows = [];
        mt_srand(830004);

        foreach ([1 => 30, 2 => 30, 3 => 30, 4 => 30, 5 => 30] as $level => $count) {
            $listLength = 7 + $level; // 8-12 numbers
            $made = 0;
            $guard = 0;
            while ($made < $count && $guard < $count * 40) {
                $guard++;
                $numbers = [];
                for ($i = 0; $i < $listLength; $i++) {
                    $numbers[] = mt_rand(10, 400 + $level * 100);
                }
                $askEven = $guard % 2 === 0;
                $answer = count(array_filter($numbers, fn ($n) => $askEven ? $n % 2 === 0 : $n % 2 === 1));
                $kind = $askEven ? 'even' : 'odd';
                $kindSi = $askEven ? 'ඉරට්ටේ' : 'ඔත්තේ';
                $listStr = implode(', ', $numbers);

                $textEn = "How many {$kind} numbers are in this list: {$listStr}?";
                if (isset($this->seenTexts[$textEn])) {
                    continue;
                }
                $this->seenTexts[$textEn] = true;
                $made++;

                $distractors = array_values(array_unique([$answer + 1, max(0, $answer - 1), min($listLength, $answer + 2)]));
                $values = array_slice(array_merge([$answer], array_diff($distractors, [$answer])), 0, 4);
                while (count($values) < 4) {
                    $values[] = $answer + count($values);
                }
                shuffle($values);
                $labels = array_map('strval', $values);
                $correctKey = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];

                $rows[] = [$level, 'mcq_text',
                    $textEn,
                    "මෙම ලැයිස්තුවේ {$kindSi} අංක කීයක් තිබේද: {$listStr}?",
                    $this->options($labels, $labels), $correctKey,
                    "There are {$answer} {$kind} numbers in the list.",
                    "ලැයිස්තුවේ {$kindSi} අංක {$answer}ක් ඇත.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'number_scanning', 'solving_time_seconds' => 30 + $level * 10,
                        'bloom_level' => 'apply'],
                ];
            }
            if ($made < $count) {
                throw new \RuntimeException("Even/odd L{$level}: only {$made}/{$count} generated.");
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Attention: misspelled word detection
    // ---------------------------------------------------------------

    /** @return array<int,array|null> */
    private function misspelledWords(): array
    {
        $combos = [];
        foreach (self::MISSPELL_BASES as $wordIdx => $word) {
            $length = strlen($word);
            for ($wrongIndex = 0; $wrongIndex < 4; $wrongIndex++) {
                for ($swapPos = 1; $swapPos < $length - 1; $swapPos++) {
                    // Swapping identical adjacent letters produces the same
                    // word - no detectable misspelling, skip.
                    if ($word[$swapPos] !== $word[$swapPos + 1]) {
                        $combos[] = [$wordIdx, $wrongIndex, $swapPos];
                    }
                }
            }
        }
        mt_srand(830005);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([1 => 30, 2 => 30, 3 => 30, 4 => 30, 5 => 30] as $level => $count) {
            for ($i = 0; $i < $count && $cursor < count($combos); $i++) {
                [$wordIdx, $wrongIndex, $swapPos] = $combos[$cursor++];
                $word = self::MISSPELL_BASES[$wordIdx];

                $chars = str_split($word);
                [$chars[$swapPos], $chars[$swapPos + 1]] = [$chars[$swapPos + 1], $chars[$swapPos]];
                $misspelled = implode('', $chars);

                $values = [];
                for ($v = 0; $v < 4; $v++) {
                    $values[] = $v === $wrongIndex ? $misspelled : $word;
                }
                $textEn = 'Which one of these is spelled differently from the others: '.implode(' | ', $values).'?';
                if (isset($this->seenTexts[$textEn])) {
                    continue;
                }
                $this->seenTexts[$textEn] = true;

                $rows[] = [$level, 'mcq_text',
                    $textEn,
                    'මේවායින් වෙනස් ලෙස අක්ෂර වින්‍යාසය කර ඇත්තේ කුමක්ද: '.implode(' | ', $values).'?',
                    $this->options($values, $values), ['A', 'B', 'C', 'D'][$wrongIndex],
                    "\"{$misspelled}\" has its letters swapped compared to \"{$word}\".",
                    "\"{$misspelled}\" හි අකුරු \"{$word}\" හා සසඳන විට හුවමාරු වී ඇත.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'error_detection', 'solving_time_seconds' => 20 + $level * 8,
                        'bloom_level' => 'apply'],
                ];
            }
        }

        return $rows;
    }
}
