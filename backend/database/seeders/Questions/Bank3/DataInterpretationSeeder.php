<?php

namespace Database\Seeders\Questions\Bank3;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Data interpretation (reading a small dataset and computing a numeric
 * answer) - an archetype confirmed missing from MindRise's existing
 * categories by the Phase-1 PDF analysis (the official CommonGeneralTest
 * specimen paper includes bar-chart data-interpretation items). Presented
 * as an inline text dataset (letter-labelled categories) rather than a
 * rendered chart image in this batch - a deliberate, documented scope
 * decision: SvgFigureBuilder's chart-rendering integration is a natural
 * follow-up, not attempted here to keep every answer a directly-verifiable
 * arithmetic computation on the exact numbers shown to the student. Only
 * sum and difference questions are included (not "highest/lowest category"
 * or percentage phrasing), matching the Sinhala vocabulary this project's
 * corpus has actually verified - see validate_sinhala.py's review log.
 */
class DataInterpretationSeeder extends Seeder
{
    use BuildsQuestions;

    private array $seenTexts = [];

    private const LABELS = ['A', 'B', 'C', 'D', 'E'];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->totalQuestions(),
            $this->differenceQuestions(),
        ));

        $this->insertRows('numerical_ability', $rows, [
            'subcategory' => 'data_interpretation',
            'exam_tags' => ['numerical_reasoning', 'gov_aptitude', 'data_interpretation'],
            'cognitive_skill' => 'quantitative-data-reading',
            'source_type' => 'past_paper_inspired',
        ]);
    }

    /** @return array<int,int> 5 values for A-E */
    private function dataset(int $seed, int $min, int $max): array
    {
        mt_srand($seed);
        $values = [];
        foreach (self::LABELS as $label) {
            $values[$label] = random_int($min, $max);
        }

        return $values;
    }

    private function dataLine(array $values): string
    {
        $parts = [];
        foreach ($values as $label => $value) {
            $parts[] = "{$label}={$value}";
        }

        return implode(', ', $parts);
    }

    /** Level 1-3: sum of all 5 values. @return array<int,array> */
    private function totalQuestions(): array
    {
        $rows = [];
        $seedBase = 835001;
        foreach ([1 => 25, 2 => 25, 3 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                $values = $this->dataset($seedBase++, 10 * $level, 60 * $level + 40);
                $total = array_sum($values);
                $dataLine = $this->dataLine($values);

                $en = "The following data was recorded: {$dataLine}. What is the total?";
                $si = "දත්ත: {$dataLine}. එකතුව කීයද?";

                $rows[] = $this->numberRow($level, $en, $si, $total, "b3data1-{$seedBase}");
            }
        }

        return $rows;
    }

    /** Level 2-5: difference between two of the five values. @return array<int,array> */
    private function differenceQuestions(): array
    {
        $rows = [];
        $seedBase = 835501;
        foreach ([2 => 25, 3 => 25, 4 => 25, 5 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                $values = $this->dataset($seedBase++, 10 * $level, 60 * $level + 40);
                $dataLine = $this->dataLine($values);
                $keys = array_keys($values);
                $labelA = $keys[$i % 5];
                $labelB = $keys[($i + 2) % 5];
                if ($labelA === $labelB) {
                    continue;
                }
                $diff = abs($values[$labelA] - $values[$labelB]);

                $en = "The following data was recorded: {$dataLine}. What is the difference between {$labelA} and {$labelB}?";
                $si = "දත්ත: {$dataLine}. {$labelA} සහ {$labelB} අතර වෙනස කීයද?";

                $rows[] = $this->numberRow($level, $en, $si, $diff, "b3data2-{$seedBase}");
            }
        }

        return $rows;
    }

    private function numberRow(int $level, string $textEn, string $textSi, int $answer, string $seedKey): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $spread = max(2, intdiv($answer, 10) + 1);
        $distractors = array_values(array_unique(array_filter([
            $answer + $spread, max(0, $answer - $spread), $answer + 2 * $spread,
        ], fn ($d) => $d !== $answer && $d >= 0)));
        while (count($distractors) < 3) {
            $distractors[] = $answer + $spread * (3 + count($distractors));
        }
        $distractors = array_slice($distractors, 0, 3);

        $values = [$answer, ...$distractors];
        mt_srand(crc32($seedKey));
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];
        $labels = array_map('strval', $values);

        return [
            $level, 'mcq_text', $textEn, $textSi,
            $this->options($labels, $labels), $key,
            "Computed directly from the given data: {$answer}.",
            "පිළිතුර {$answer} වේ.",
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'data_interpretation', 'solving_time_seconds' => 35 + $level * 10, 'bloom_level' => 'apply'],
        ];
    }
}
