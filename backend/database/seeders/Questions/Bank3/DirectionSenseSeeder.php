<?php

namespace Database\Seeders\Questions\Bank3;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Direction-sense reasoning (compass movement + shortest-distance
 * calculation) - an archetype confirmed missing from MindRise's existing
 * categories by the Phase-1 PDF analysis (appeared in the official
 * CommonGeneralTest specimen paper and several exam-prep guides). All
 * answers are computed via real coordinate geometry (Pythagorean distance
 * for the distance archetype; exact axis cancellation for the direction
 * archetype, so the net result is always a pure cardinal direction rather
 * than an intercardinal one this project has no verified Sinhala vocabulary
 * for) - never guessed. Subject is a letter label (P), avoiding any need
 * for a Sinhala "person" noun.
 */
class DirectionSenseSeeder extends Seeder
{
    use BuildsQuestions;

    private array $seenTexts = [];

    private const DIR_SI = ['North' => 'උතුර', 'South' => 'දකුණ', 'East' => 'නැගෙනහිර', 'West' => 'බටහිර'];

    private const OPPOSITE = ['North' => 'South', 'South' => 'North', 'East' => 'West', 'West' => 'East'];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->netDirection(),
            $this->netDistance(),
        ));

        $this->insertRows('logical_reasoning', $rows, [
            'subcategory' => 'direction_sense',
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'direction_sense'],
            'cognitive_skill' => 'spatial-relational-reasoning',
            'source_type' => 'past_paper_inspired',
        ]);
    }

    /** Level 1-2: two legs that cancel on one axis, net result is a pure cardinal direction. @return array<int,array> */
    private function netDirection(): array
    {
        $rows = [];
        $combos = [];
        foreach (['North', 'South'] as $axis1First) {
            foreach (['East', 'West'] as $axis2) {
                foreach (range(1, 20) as $cancelKm) {
                    foreach (range(1, 20) as $netKm) {
                        $combos[] = [$axis1First, $axis2, $cancelKm, $netKm];
                    }
                }
            }
        }
        mt_srand(831001);
        shuffle($combos);

        $cursor = 0;
        foreach ([1 => 40, 2 => 40] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$dir1, $dir2, $cancelKm, $netKm] = $combos[$cursor++];
                $dir1Opposite = self::OPPOSITE[$dir1];

                $en = "P walks {$cancelKm} km {$dir1}, then {$cancelKm} km {$dir1Opposite}, then {$netKm} km {$dir2}. In which direction is P now from the starting point?";
                $si = "P: {$this->diren($dir1)} කි.මී. {$cancelKm}, {$this->diren($dir1Opposite)} කි.මී. {$cancelKm}, {$this->diren($dir2)} කි.මී. {$netKm}. කුමක්ද?";

                $rows[] = $this->directionRow($level, $en, $si, $dir2, "b3dir1-{$dir1}-{$dir2}-{$cancelKm}-{$netKm}");
            }
        }

        return $rows;
    }

    /** Level 3-5: perpendicular two-leg journey, distance via Pythagorean triples. @return array<int,array> */
    private function netDistance(): array
    {
        $triples = [
            [3, 4, 5], [6, 8, 10], [5, 12, 13], [8, 15, 17], [9, 12, 15], [7, 24, 25],
            [12, 16, 20], [9, 40, 41], [10, 24, 26], [15, 20, 25], [20, 21, 29],
            [11, 60, 61], [16, 63, 65], [48, 55, 73],
        ];
        $combos = [];
        foreach ($triples as [$a, $b, $c]) {
            foreach (['North', 'South'] as $vertical) {
                foreach (['East', 'West'] as $horizontal) {
                    $combos[] = [$a, $b, $c, $vertical, $horizontal];
                    $combos[] = [$b, $a, $c, $vertical, $horizontal];
                }
            }
        }
        mt_srand(831002);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([3 => 30, 4 => 30, 5 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$a, $b, $c, $vertical, $horizontal] = $combos[$cursor++];

                $en = "P walks {$a} km {$vertical}, then {$b} km {$horizontal}. What is the shortest distance between P's current position and the starting point (in km)?";
                $si = "P කි.මී. {$a}ක් {$this->diren($vertical)}ට, පසුව කි.මී. {$b}ක් {$this->diren($horizontal)}ට ගමන් කරයි. P ගේ ස්ථානය සහ ආරම්භක ස්ථානය අතර දුර කීයද (කි.මී.)?";

                $rows[] = $this->distanceRow($level, $en, $si, $c, "b3dir2-{$a}-{$b}-{$vertical}-{$horizontal}");
            }
        }

        return $rows;
    }

    private function diren(string $en): string
    {
        return self::DIR_SI[$en];
    }

    private function directionRow(int $level, string $textEn, string $textSi, string $answerEn, string $seedKey): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $answerSi = self::DIR_SI[$answerEn];
        $distractorsEn = array_keys(array_diff(self::DIR_SI, [$answerEn => $answerSi]));

        $labelsEn = [$answerEn, ...$distractorsEn];
        $labelsSi = array_map(fn ($en) => self::DIR_SI[$en], $labelsEn);
        $order = [0, 1, 2, 3];
        mt_srand(crc32($seedKey));
        shuffle($order);
        $shuffledEn = array_map(fn ($i) => $labelsEn[$i], $order);
        $shuffledSi = array_map(fn ($i) => $labelsSi[$i], $order);
        $correctKey = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        return [
            $level, 'mcq_text', $textEn, $textSi,
            $this->options($shuffledEn, $shuffledSi), $correctKey,
            "The two opposite legs cancel out, leaving only the {$answerEn} leg - so P ends up {$answerEn} of the starting point.",
            "පිළිතුර {$answerSi} වේ.",
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'direction_sense', 'solving_time_seconds' => 35 + $level * 10, 'bloom_level' => 'apply'],
        ];
    }

    private function distanceRow(int $level, string $textEn, string $textSi, int $answer, string $seedKey): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $distractors = array_values(array_unique([$answer + 2, max(1, $answer - 2), $answer + 4]));
        while (count($distractors) < 3) {
            $distractors[] = $answer + 6 + count($distractors);
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
            "This is a right-angled path: the two legs form a Pythagorean triple, so the direct distance is {$answer} km.",
            "පිළිතුර කි.මී. {$answer} වේ.",
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'direction_sense', 'solving_time_seconds' => 40 + $level * 12, 'bloom_level' => 'analyze'],
        ];
    }
}
