<?php

namespace Database\Seeders\Questions\Bank3;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Coding-decoding (letter-shift cipher) reasoning, an archetype missing
 * from the question bank. Every code is a real Caesar-shift computation
 * (mod 26) on a real word pool - the answer is computed by the same
 * shiftWord() function that builds the question, never asserted.
 */
class CodingDecodingSeeder extends Seeder
{
    use BuildsQuestions;

    private array $seenTexts = [];

    private const WORDS = [
        'CAT', 'DOG', 'SUN', 'BUS', 'PEN', 'CUP', 'HAT', 'BOX',
        'BOOK', 'PLAY', 'FISH', 'LAMP', 'RAIN', 'GOLD', 'MILK', 'SAND',
        'TRAIN', 'HOUSE', 'GREEN', 'CHAIR', 'BREAD', 'CLOUD', 'STONE', 'RIVER',
        'GARDEN', 'PENCIL', 'ORANGE', 'SILVER', 'WINDOW', 'BASKET',
    ];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->encodeQuestions(),
            $this->decodeQuestions(),
        ));

        $this->insertRows('logical_reasoning', $rows, [
            'subcategory' => 'coding_decoding',
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'coding_decoding'],
            'cognitive_skill' => 'symbolic-transformation',
            'source_type' => 'past_paper_inspired',
        ]);
    }

    private function shiftWord(string $word, int $shift): string
    {
        $result = '';
        for ($i = 0; $i < strlen($word); $i++) {
            $ord = ord($word[$i]) - ord('A');
            $result .= chr((($ord + $shift) % 26 + 26) % 26 + ord('A'));
        }

        return $result;
    }

    /** @return array<int,array> */
    private function encodeQuestions(): array
    {
        $combos = [];
        foreach (array_slice(self::WORDS, 0, 8) as $word) {
            foreach (range(1, 9) as $shift) {
                $combos[] = [$word, $shift];
            }
        }
        mt_srand(832001);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([1 => 30, 2 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$word, $shift] = $combos[$cursor++];
                $answer = $this->shiftWord($word, $shift);

                $en = "In a certain code, every letter moves forward by {$shift} position(s) in the alphabet. How is the word {$word} coded?";
                $si = "සෑම අකුරක්ම ස්ථාන {$shift}කින් ඉදිරියට යයි. {$word} = ?";

                $rows[] = $this->cipherRow($level, $en, $si, $answer, $word, $shift, "b3code1-{$word}-{$shift}");
            }
        }

        return $rows;
    }

    /** @return array<int,array> */
    private function decodeQuestions(): array
    {
        $combos = [];
        foreach (array_slice(self::WORDS, 8, 12) as $word) {
            foreach (range(2, 11) as $shift) {
                $combos[] = [$word, $shift];
            }
        }
        mt_srand(832002);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([3 => 30, 4 => 30, 5 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$word, $shift] = $combos[$cursor++];
                $coded = $this->shiftWord($word, $shift);

                $en = "In a certain code, every letter moves forward by {$shift} position(s) in the alphabet. If a word is coded as {$coded}, what was the original word?";
                $si = "සෑම අකුරක්ම ස්ථාන {$shift}කින් ඉදිරියට යයි. කේතය {$coded} නම්, වචනය කුමක්ද?";

                $rows[] = $this->cipherRow($level, $en, $si, $word, $coded, $shift, "b3code2-{$word}-{$shift}");
            }
        }

        return $rows;
    }

    private function cipherRow(int $level, string $textEn, string $textSi, string $answer, string $otherWord, int $shift, string $seedKey): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        // Distractors: off-by-one-shift versions of the same word - genuine
        // near-misses (wrong shift amount), not arbitrary noise.
        $distractors = array_unique(array_filter([
            $this->shiftWord($otherWord, $shift + 1),
            $this->shiftWord($otherWord, max(0, $shift - 1)),
            $this->shiftWord($otherWord, $shift + 2),
        ], fn ($d) => $d !== $answer));
        $distractors = array_slice(array_values($distractors), 0, 3);
        while (count($distractors) < 3) {
            $distractors[] = $this->shiftWord($otherWord, $shift + 3 + count($distractors));
        }

        $values = [$answer, ...$distractors];
        mt_srand(crc32($seedKey));
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];

        return [
            $level, 'mcq_text', $textEn, $textSi,
            $this->options($values, $values), $key,
            "Each letter shifts by {$shift}, so the answer is {$answer}.",
            "පිළිතුර {$answer} වේ.",
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'coding_decoding', 'solving_time_seconds' => 35 + $level * 10, 'bloom_level' => $level >= 3 ? 'analyze' : 'apply'],
        ];
    }
}
