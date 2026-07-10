<?php

namespace Database\Seeders\Questions;

use Illuminate\Database\Seeder;

/**
 * Harder spatial-reasoning questions modelled on the "logical sequence of
 * diagrams" style rotation puzzles from the reference exam papers (G.C.E.
 * A/L Common General Test Q19-style), using 8-direction arrows as a
 * text-safe stand-in for rotating shape diagrams. Layered on top of the
 * existing 80/level bank, concentrated at levels 3-5.
 */
class AdvancedSpatialQuestionsSeeder extends Seeder
{
    use BuildsQuestions;

    private const PER_LEVEL = ['3' => 8, '4' => 10, '5' => 10];

    private const ARROWS = ['↑', '↗', '→', '↘', '↓', '↙', '←', '↖'];

    public function run(): void
    {
        $rows = [];

        foreach (self::PER_LEVEL as $level => $count) {
            $level = (int) $level;
            $combos = $this->buildCombos((int) $level, $count);
            foreach ($combos as $combo) {
                $rows[] = $this->renderRotation($level, $combo);
            }
        }

        $this->insertRows('spatial_pattern', $rows);
    }

    /** @return array<int,array{0:int,1:int}> [startIdx, stepIdx] unique per level */
    private function buildCombos(int $level, int $needed): array
    {
        $combos = [];
        foreach (range(0, 7) as $start) {
            foreach ([1, 2, 3, -1, -2, -3] as $step) {
                $combos[] = [$start, $step];
            }
        }
        mt_srand(9800 + $level);
        shuffle($combos);

        return array_slice($combos, 0, $needed);
    }

    private function renderRotation(int $level, array $combo): array
    {
        [$start, $step] = $combo;
        $n = count(self::ARROWS);

        $pos = fn (int $i) => (($start + $i * $step) % $n + $n) % $n;
        $sequence = array_map(fn ($i) => self::ARROWS[$pos($i)], range(0, 3));
        $answer = self::ARROWS[$pos(4)];

        $wrongPositions = array_values(array_diff(range(0, $n - 1), [$pos(4)]));
        mt_srand(crc32(implode('-', $combo).'-'.$level));
        shuffle($wrongPositions);
        $wrongs = array_map(fn ($p) => self::ARROWS[$p], array_slice($wrongPositions, 0, 3));

        $values = array_merge([$answer], $wrongs);
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];
        $options = $this->options($values, $values);

        $seqStr = implode(' ', $sequence);
        $direction = $step > 0 ? 'clockwise' : 'anticlockwise';
        $directionSi = $step > 0 ? 'ඔරලෝසු දිශාවට' : 'ඔරලෝසු විරුද්ධ දිශාවට';
        $steps = abs($step);

        return [$level, 'mcq_text',
            "The arrow rotates by a fixed amount each step. What comes next in this sequence? {$seqStr} ?",
            "එක් එක් පියවරේදී ඊතලය නියත ප්‍රමාණයකින් භ්‍රමණය වේ. මෙම අනුක්‍රමයේ ඊළඟට එන්නේ කුමක්ද? {$seqStr} ?",
            $options, $key,
            "Each arrow rotates {$steps} position(s) {$direction} around the compass, so the next arrow is {$answer}.",
            "එක් එක් ඊතලය දිශා {$steps}කින් {$directionSi} භ්‍රමණය වේ, එබැවින් ඊළඟ ඊතලය {$answer} වේ.", ];
    }
}
