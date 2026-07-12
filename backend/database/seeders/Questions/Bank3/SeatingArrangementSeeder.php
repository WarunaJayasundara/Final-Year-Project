<?php

namespace Database\Seeders\Questions\Bank3;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Linear seating/ranking arrangement reasoning - an archetype confirmed
 * missing from MindRise's existing categories by the Phase-1 PDF analysis.
 * Deliberately restricted to closed-form rank arithmetic (left-rank <->
 * right-rank conversion, and the "people between two ranks" count) rather
 * than multi-constraint puzzles that risk generating an ambiguous or
 * contradictory arrangement - every answer here is a direct formula
 * (total - rank + 1, or |rankA - rankB| - 1), never asserted.
 */
class SeatingArrangementSeeder extends Seeder
{
    use BuildsQuestions;

    private array $seenTexts = [];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->rankConversion(),
            $this->peopleBetween(),
        ));

        $this->insertRows('logical_reasoning', $rows, [
            'subcategory' => 'seating_arrangement',
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'seating_arrangement'],
            'cognitive_skill' => 'positional-reasoning',
            'source_type' => 'past_paper_inspired',
        ]);
    }

    /** Level 1-2: convert a left-side rank to a right-side rank. @return array<int,array> */
    private function rankConversion(): array
    {
        $combos = [];
        foreach (range(10, 40) as $total) {
            foreach (range(2, $total - 1) as $leftRank) {
                $combos[] = [$total, $leftRank];
            }
        }
        mt_srand(834001);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([1 => 30, 2 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$total, $leftRank] = $combos[$cursor++];
                $rightRank = $total - $leftRank + 1;

                $en = "In a row of {$total} people, P is {$leftRank}(th) from the left. What is P's position counted from the right?";
                $si = "පේළියේ මුළු ගණන {$total} වේ. P වම් පසින් ස්ථානය {$leftRank}. P දකුණු පසින් ස්ථානය කීයද?";

                $rows[] = $this->rankRow($level, $en, $si, $rightRank, "b3seat1-{$total}-{$leftRank}");
            }
        }

        return $rows;
    }

    /** Level 3-5: count people strictly between P (left-rank) and Q (right-rank). @return array<int,array> */
    private function peopleBetween(): array
    {
        $combos = [];
        foreach (range(15, 50) as $total) {
            foreach (range(2, intdiv($total, 2)) as $leftRank) {
                foreach (range(2, intdiv($total, 2)) as $rightRank) {
                    $qPositionFromLeft = $total - $rightRank + 1;
                    $between = abs($qPositionFromLeft - $leftRank) - 1;
                    if ($between >= 2 && $qPositionFromLeft !== $leftRank) {
                        $combos[] = [$total, $leftRank, $rightRank, $between];
                    }
                }
            }
        }
        mt_srand(834002);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([3 => 30, 4 => 30, 5 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$total, $leftRank, $rightRank, $between] = $combos[$cursor++];

                $en = "In a row of {$total} people, P is {$leftRank}(th) from the left and Q is {$rightRank}(th) from the right. How many people are seated strictly between P and Q?";
                $si = "පේළියේ මුළු ගණන {$total} වේ. P වම් පසින් ස්ථානය {$leftRank}. Q දකුණු පසින් ස්ථානය {$rightRank}. P සහ Q අතර ගණන කීයද?";

                $rows[] = $this->rankRow($level, $en, $si, $between, "b3seat2-{$total}-{$leftRank}-{$rightRank}");
            }
        }

        return $rows;
    }

    private function rankRow(int $level, string $textEn, string $textSi, int $answer, string $seedKey): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $distractors = array_values(array_unique(array_filter([
            $answer + 1, max(0, $answer - 1), $answer + 2,
        ], fn ($d) => $d !== $answer && $d >= 0)));
        while (count($distractors) < 3) {
            $distractors[] = $answer + 3 + count($distractors);
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
            "Converting ranks and counting positions gives {$answer}.",
            "පිළිතුර {$answer} වේ.",
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'seating_arrangement', 'solving_time_seconds' => 35 + $level * 10, 'bloom_level' => $level >= 4 ? 'analyze' : 'apply'],
        ];
    }
}
