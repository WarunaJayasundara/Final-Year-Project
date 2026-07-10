<?php

namespace Database\Seeders\Questions;

use Illuminate\Database\Seeder;

class AttentionQuestionsSeeder extends Seeder
{
    use BuildsQuestions;

    private const PER_LEVEL_LETTER_COUNT = 27;
    private const PER_LEVEL_EVEN_ODD = 27;
    private const PER_LEVEL_ODD_WORD_OUT = 26;

    private const WORD_POOL = [
        'BANANA', 'APPLE', 'ORANGE', 'MANGO', 'PAPAYA', 'AVOCADO', 'ELEPHANT', 'ANTELOPE',
        'ALPACA', 'GIRAFFE', 'DOLPHIN', 'PENGUIN', 'KANGAROO', 'HAMSTER', 'CROCODILE', 'FLAMINGO',
        'OCTOPUS', 'BUTTERFLY', 'HEDGEHOG', 'PINEAPPLE',
    ];

    private const LETTERS = ['A', 'E', 'I', 'O', 'R', 'S', 'T', 'N'];

    private const ODD_WORD_BASES = [
        'APPLE', 'BREEZE', 'CIRCLE', 'FOREST', 'GARDEN', 'HAMMER', 'ISLAND', 'JACKET',
        'KETTLE', 'LANTERN', 'MARBLE', 'NEEDLE', 'ORCHARD', 'PENCIL', 'RIBBON', 'SILVER',
    ];

    public function run(): void
    {
        $rows = [];

        $letterCombosByLevel = [];
        foreach (range(1, 5) as $level) {
            $letterCombosByLevel[$level] = $this->buildLetterCountCombos($level, self::PER_LEVEL_LETTER_COUNT);
        }

        $oddWordCombos = $this->buildOddWordOutCombos();
        $oddWordCursor = 0;

        foreach (range(1, 5) as $level) {
            for ($i = 0; $i < self::PER_LEVEL_LETTER_COUNT; $i++) {
                $rows[] = $this->renderLetterCount($level, $letterCombosByLevel[$level][$i]);
            }
            for ($i = 0; $i < self::PER_LEVEL_EVEN_ODD; $i++) {
                $rows[] = $this->buildEvenOddCount($level, $i);
            }
            for ($i = 0; $i < self::PER_LEVEL_ODD_WORD_OUT; $i++) {
                $rows[] = $this->renderOddWordOut($level, $oddWordCombos[$oddWordCursor++]);
            }
        }

        $this->insertRows('attention', $rows);
    }

    private function optionsFromValues(array $values, $answer): array
    {
        $labels = array_map('strval', $values);
        $options = $this->options($labels, $labels);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];

        return [$options, $key];
    }

    /**
     * Picks $needed unique (word-subset, letter) combinations via rejection
     * sampling rather than materializing the full C(20, wordCount) x 8
     * combinatorial space (which for wordCount=7 is 77,520 x 8 - too big to
     * hold in memory for no real benefit when only ~27 are ever used).
     *
     * @return array<int,array{0:array<int,int>,1:int}> [wordIndices, letterIndex]
     */
    private function buildLetterCountCombos(int $level, int $needed): array
    {
        $wordCount = $level + 2;
        $poolSize = count(self::WORD_POOL);
        mt_srand(5100 + $level);

        $seen = [];
        $combos = [];
        $guard = 0;

        while (count($combos) < $needed && $guard < $needed * 50) {
            $guard++;
            $indices = (array) array_rand(range(0, $poolSize - 1), $wordCount);
            sort($indices);
            $letterIdx = mt_rand(0, count(self::LETTERS) - 1);

            $signature = implode(',', $indices).'|'.$letterIdx;
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $combos[] = [$indices, $letterIdx];
        }

        return $combos;
    }

    private function renderLetterCount(int $level, array $combo): array
    {
        [$wordIndices, $letterIdx] = $combo;
        $words = array_map(fn ($i) => self::WORD_POOL[$i], $wordIndices);
        $letter = self::LETTERS[$letterIdx];

        $phrase = implode(' ', $words);
        $answer = max(substr_count($phrase, $letter), 0);

        $distractors = array_values(array_unique([$answer + 1, max(0, $answer - 1), $answer + 2, $answer + 3]));
        mt_srand(crc32(implode(',', $wordIndices).'-'.$letterIdx.'-'.$level));
        shuffle($distractors);
        $values = array_slice(array_merge([$answer], $distractors), 0, 4);
        shuffle($values);
        [$options, $key] = $this->optionsFromValues($values, $answer);

        return [$level, 'mcq_text',
            "Count carefully: how many times does the letter '{$letter}' appear in \"{$phrase}\"?",
            "සැලකිල්ලෙන් ගණන් කරන්න: \"{$phrase}\" තුළ '{$letter}' අකුර කී වතාවක් දිස්වේද?",
            $options, $key,
            "The letter '{$letter}' appears {$answer} time(s) in the phrase.",
            "'{$letter}' අකුර වාක්‍යයේ {$answer} වතාවක් පෙනී යයි.", ];
    }

    private function buildEvenOddCount(int $level, int $variant): array
    {
        mt_srand($level * 700000 + $variant * 61 + 23);
        $count = 5 + $level;
        $numbers = [];
        for ($i = 0; $i < $count; $i++) {
            $numbers[] = mt_rand(1, 60 + $level * 10);
        }
        $askEven = $variant % 2 === 0;
        $answer = count(array_filter($numbers, fn ($n) => $askEven ? $n % 2 === 0 : $n % 2 === 1));
        $kind = $askEven ? 'even' : 'odd';
        $kindSi = $askEven ? 'ඉරට්ටේ' : 'ඔත්තේ';

        $listStr = implode(', ', $numbers);
        $distractors = array_values(array_unique([$answer + 1, max(0, $answer - 1), min($count, $answer + 2)]));
        $values = array_slice(array_merge([$answer], $distractors), 0, 4);
        shuffle($values);
        [$options, $key] = $this->optionsFromValues($values, $answer);

        return [$level, 'mcq_text',
            "How many {$kind} numbers are in this list: {$listStr}?",
            "මෙම ලැයිස්තුවේ {$kindSi} අංක කීයක් තිබේද: {$listStr}?",
            $options, $key,
            "There are {$answer} {$kind} numbers in the list.",
            "ලැයිස්තුවේ {$kindSi} අංක {$answer}ක් ඇත.", ];
    }

    /** @return array<int,array{0:int,1:int,2:int}> [wordIdx, wrongOptionIdx, swapPosition] */
    private function buildOddWordOutCombos(): array
    {
        $combos = [];
        foreach (self::ODD_WORD_BASES as $wordIdx => $word) {
            $length = strlen($word);
            for ($wrongIndex = 0; $wrongIndex < 4; $wrongIndex++) {
                for ($swapPos = 1; $swapPos < $length - 1; $swapPos++) {
                    $combos[] = [$wordIdx, $wrongIndex, $swapPos];
                }
            }
        }

        mt_srand(5200);
        shuffle($combos);

        return $combos;
    }

    private function renderOddWordOut(int $level, array $combo): array
    {
        [$wordIdx, $wrongIndex, $swapPos] = $combo;
        $word = self::ODD_WORD_BASES[$wordIdx];

        $chars = str_split($word);
        [$chars[$swapPos], $chars[$swapPos + 1]] = [$chars[$swapPos + 1], $chars[$swapPos]];
        $misspelled = implode('', $chars);

        $values = [];
        for ($i = 0; $i < 4; $i++) {
            $values[] = $i === $wrongIndex ? $misspelled : $word;
        }

        $options = $this->options($values, $values);
        $key = ['A', 'B', 'C', 'D'][$wrongIndex];

        return [$level, 'mcq_text',
            'Which one of these is spelled differently from the others?',
            'මේවායින් වෙනස් ලෙස අක්ෂර වින්‍යාසය කර ඇත්තේ කුමක්ද?',
            $options, $key,
            "\"{$misspelled}\" has its letters swapped compared to \"{$word}\".",
            "\"{$misspelled}\" හි අකුරු \"{$word}\" හා සසඳන විට හුවමාරු වී ඇත.", ];
    }
}
