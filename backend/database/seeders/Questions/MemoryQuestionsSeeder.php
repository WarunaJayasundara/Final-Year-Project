<?php

namespace Database\Seeders\Questions;

use Illuminate\Database\Seeder;

class MemoryQuestionsSeeder extends Seeder
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

    public function run(): void
    {
        $rows = [];

        foreach (range(1, 5) as $level) {
            $sequenceLength = $level + 2; // L1=3 ... L5=7

            for ($i = 0; $i < 40; $i++) {
                $rows[] = $this->buildSequenceQuestion($level, $sequenceLength, $i);
            }

            for ($i = 0; $i < 40; $i++) {
                $rows[] = $this->buildPairAssociationQuestion($level, $i);
            }
        }

        $this->insertRows('memory', $rows);
    }

    private function buildSequenceQuestion(int $level, int $length, int $variant): array
    {
        mt_srand($level * 100000 + $variant * 37 + 7);
        // Digits may repeat within the sequence (real memory tests, e.g. phone
        // numbers, often do) - this also widens the space of distinct
        // sequences well beyond 9P(length) so a 40-variant run per level
        // doesn't collide into duplicate questions.
        $digits = [];
        for ($i = 0; $i < $length; $i++) {
            $digits[] = mt_rand(0, 9);
        }

        $askPosition = $variant % $length;
        $correctValue = $digits[$askPosition];

        $pool = array_values(array_diff(range(0, 9), [$correctValue]));
        shuffle($pool);
        $optionValues = array_slice(array_merge([$correctValue], $pool), 0, 4);
        shuffle($optionValues);
        $correctKey = ['A', 'B', 'C', 'D'][array_search($correctValue, $optionValues, true)];

        $sequenceStr = implode(', ', $digits);

        return [
            $level,
            'mcq_text',
            "Memorize this sequence: {$sequenceStr}. What is the ".self::ORDINAL_EN[$askPosition]." number in the sequence?",
            "මෙම අනුක්‍රමය මතක තබා ගන්න: {$sequenceStr}. අනුක්‍රමයේ ".self::ORDINAL_SI[$askPosition]." අංකය කුමක්ද?",
            $this->options(array_map('strval', $optionValues), array_map('strval', $optionValues)),
            $correctKey,
            "The sequence was {$sequenceStr}. The ".self::ORDINAL_EN[$askPosition]." number is {$correctValue}.",
            "අනුක්‍රමය වූයේ {$sequenceStr} ".self::ORDINAL_SI[$askPosition]." අංකය {$correctValue} වේ.",
        ];
    }

    private function buildPairAssociationQuestion(int $level, int $variant): array
    {
        mt_srand($level * 200000 + $variant * 53 + 11);
        $bank = self::WORD_BANKS[$variant % count(self::WORD_BANKS)];
        $itemCount = min(count($bank['en']), 3 + intdiv($level, 2));

        $indices = range(0, count($bank['en']) - 1);
        shuffle($indices);
        $indices = array_slice($indices, 0, $itemCount);
        $numbers = [];
        while (count($numbers) < $itemCount) {
            $n = mt_rand(1, 9);
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

        $askIndex = $variant % $itemCount;
        $correctValue = $numbers[$askIndex];
        $askedWordEn = $bank['en'][$indices[$askIndex]];
        $askedWordSi = $bank['si'][$indices[$askIndex]];

        $optionValues = $numbers;
        shuffle($optionValues);
        $optionValues = array_slice($optionValues, 0, min(4, count($optionValues)));
        if (! in_array($correctValue, $optionValues, true)) {
            $optionValues[0] = $correctValue;
        }
        $correctKey = ['A', 'B', 'C', 'D'][array_search($correctValue, $optionValues, true)];

        $listEn = implode(', ', $pairsEn);
        $listSi = implode(', ', $pairsSi);

        return [
            $level,
            'mcq_text',
            "Memorize these pairs: {$listEn}. What number was paired with {$askedWordEn}?",
            "මෙම යුගල මතක තබා ගන්න: {$listSi}. {$askedWordSi} සමඟ යුගල වූ අංකය කුමක්ද?",
            $this->options(array_map('strval', $optionValues), array_map('strval', $optionValues)),
            $correctKey,
            "{$askedWordEn} was paired with {$correctValue}.",
            "{$askedWordSi} සමඟ යුගල වූයේ {$correctValue} ය.",
        ];
    }
}
