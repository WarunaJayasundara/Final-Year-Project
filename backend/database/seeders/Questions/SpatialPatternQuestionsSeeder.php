<?php

namespace Database\Seeders\Questions;

use Illuminate\Database\Seeder;

/**
 * Spatial & pattern-recognition questions rendered as Unicode shape/emoji
 * sequences (mcq_text) rather than raster images, keeping the bank
 * text-based with no generated image assets needed. The mcq_image type
 * and its upload endpoint (Admin\QuestionController::uploadImage) are
 * still available if real images are wanted later.
 *
 * Combo pools are built once as a single shuffled list per question type,
 * then sliced per level, so the same combo can't be reused across levels.
 */
class SpatialPatternQuestionsSeeder extends Seeder
{
    use BuildsQuestions;

    private const PER_LEVEL_SEQUENCE = 27;
    private const PER_LEVEL_ODD_SHAPE = 27;
    private const PER_LEVEL_SHAPE_COUNT = 26;

    private const SHAPE_SETS = [
        ['🔺', '🔷', '⬛'],
        ['🔴', '🟡', '🟢'],
        ['⬛', '⬤', '▲'],
        ['🔻', '🔶', '⬤'],
        ['🟦', '🟧', '🟪'],
        ['⭐', '🌙', '☀️'],
        ['🍎', '🍌', '🍇'],
        ['🐱', '🐶', '🐰'],
        ['⚽', '🏀', '🎾'],
        ['🌸', '🌼', '🌻'],
        ['🔵', '🔶', '🔺'],
        ['♠️', '♥️', '♦️'],
        ['🚗', '🚕', '🚙'],
        ['🎵', '🎶', '🎼'],
        ['📘', '📗', '📙'],
        ['🔔', '🔕', '📯'],
    ];

    public function run(): void
    {
        $rows = [];

        // Levels 1-3 share a period-2 pattern; levels 4-5 share period-3.
        $period2Pool = $this->buildSequencePool(2, self::PER_LEVEL_SEQUENCE * 3);
        $period3Pool = $this->buildSequencePool(3, self::PER_LEVEL_SEQUENCE * 2);
        $oddShapePool = $this->buildOddShapePool(self::PER_LEVEL_ODD_SHAPE * 5);

        $seqCursor2 = 0;
        $seqCursor3 = 0;
        $oddCursor = 0;

        foreach (range(1, 5) as $level) {
            $periodLength = $level >= 4 ? 3 : 2;
            $pool = $periodLength === 2 ? $period2Pool : $period3Pool;
            $cursor = $periodLength === 2 ? $seqCursor2 : $seqCursor3;

            for ($i = 0; $i < self::PER_LEVEL_SEQUENCE; $i++) {
                $rows[] = $this->renderSequenceCompletion($level, $periodLength, $pool[$cursor++]);
            }

            if ($periodLength === 2) {
                $seqCursor2 = $cursor;
            } else {
                $seqCursor3 = $cursor;
            }

            for ($i = 0; $i < self::PER_LEVEL_ODD_SHAPE; $i++) {
                $rows[] = $this->renderOddShapeOut($level, $oddShapePool[$oddCursor++]);
            }
            for ($i = 0; $i < self::PER_LEVEL_SHAPE_COUNT; $i++) {
                $rows[] = $this->buildShapeCount($level, $i);
            }
        }

        $this->insertRows('spatial_pattern', $rows);
    }

    private function optionsFromValues(array $values, $answer): array
    {
        $labels = array_map('strval', $values);
        $options = $this->options($labels, $labels);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];

        return [$options, $key];
    }

    /** @return array<int,array{0:int,1:int,2:int}> [setIdx, rotationOffset, repeats] */
    private function buildSequencePool(int $periodLength, int $needed): array
    {
        $combos = [];
        foreach (array_keys(self::SHAPE_SETS) as $setIdx) {
            for ($rotation = 0; $rotation < $periodLength; $rotation++) {
                foreach ([3, 4, 5] as $repeats) {
                    $combos[] = [$setIdx, $rotation, $repeats];
                }
            }
        }

        mt_srand(6100 + $periodLength);
        shuffle($combos);

        if (count($combos) < $needed) {
            throw new \RuntimeException("Sequence combo pool too small: need {$needed}, have ".count($combos));
        }

        return $combos;
    }

    private function renderSequenceCompletion(int $level, int $periodLength, array $combo): array
    {
        [$setIdx, $rotation, $repeats] = $combo;
        $set = self::SHAPE_SETS[$setIdx];

        // Rotate the starting phase so the same shape-set can still produce
        // visibly different sequences.
        $basePattern = array_slice($set, 0, $periodLength);
        $pattern = array_merge(array_slice($basePattern, $rotation), array_slice($basePattern, 0, $rotation));

        $sequence = [];
        for ($i = 0; $i < $repeats * $periodLength; $i++) {
            $sequence[] = $pattern[$i % $periodLength];
        }
        $answer = $pattern[($repeats * $periodLength) % $periodLength];

        $others = array_values(array_diff($set, [$answer]));
        $extraSet = self::SHAPE_SETS[($setIdx + 1) % count(self::SHAPE_SETS)];
        $others = array_values(array_unique(array_merge($others, array_diff($extraSet, [$answer]))));
        $values = array_slice(array_merge([$answer], $others), 0, 4);
        shuffle($values);
        [$options, $key] = $this->optionsFromValues($values, $answer);

        $seqStr = implode(' ', $sequence);

        return [$level, 'mcq_text',
            "What comes next in this pattern? {$seqStr} ?",
            "මෙම රටාවේ ඊළඟට එන්නේ කුමක්ද? {$seqStr} ?",
            $options, $key,
            "The pattern repeats every {$periodLength} shapes, so the next one is {$answer}.",
            "රටාව සෑම හැඩ {$periodLength}කින්ම පුනරාවර්තනය වේ, එබැවින් ඊළඟට එන්නේ {$answer} ය.", ];
    }

    /** @return array<int,array{0:int,1:int,2:int,3:int,4:int}> [setIdx, commonIdx, oddIdx, wrongOptionIdx, repeatCount] */
    private function buildOddShapePool(int $needed): array
    {
        $combos = [];
        foreach (array_keys(self::SHAPE_SETS) as $setIdx) {
            for ($commonIdx = 0; $commonIdx < 3; $commonIdx++) {
                for ($oddIdx = 0; $oddIdx < 3; $oddIdx++) {
                    if ($oddIdx === $commonIdx) {
                        continue;
                    }
                    for ($wrongOptionIdx = 0; $wrongOptionIdx < 4; $wrongOptionIdx++) {
                        foreach ([2, 3, 4] as $repeatCount) {
                            $combos[] = [$setIdx, $commonIdx, $oddIdx, $wrongOptionIdx, $repeatCount];
                        }
                    }
                }
            }
        }

        mt_srand(6200);
        shuffle($combos);

        if (count($combos) < $needed) {
            throw new \RuntimeException("Odd-shape combo pool too small: need {$needed}, have ".count($combos));
        }

        return $combos;
    }

    private function renderOddShapeOut(int $level, array $combo): array
    {
        [$setIdx, $commonIdx, $oddIdx, $wrongOptionIdx, $repeatCount] = $combo;
        $set = self::SHAPE_SETS[$setIdx];
        $common = $set[$commonIdx];
        $odd = $set[$oddIdx];

        $labels = [];
        for ($i = 0; $i < 4; $i++) {
            $shape = $i === $wrongOptionIdx ? $odd : $common;
            $labels[] = str_repeat($shape, $repeatCount);
        }

        $options = $this->options($labels, $labels);
        $key = ['A', 'B', 'C', 'D'][$wrongOptionIdx];

        return [$level, 'mcq_text',
            'Which group of shapes is different from the others?',
            'මෙම හැඩ සමූහවලින් වෙනස් වන්නේ කුමක්ද?',
            $options, $key,
            "All the other groups use \"{$common}\" repeated, but this one uses \"{$odd}\".",
            "අනෙක් සියලුම සමූහ \"{$common}\" පුනරාවර්තනය කරන අතර, මෙය \"{$odd}\" භාවිතා කරයි.", ];
    }

    private function buildShapeCount(int $level, int $variant): array
    {
        mt_srand($level * 800000 + $variant * 67 + 29);
        $set = self::SHAPE_SETS[$variant % count(self::SHAPE_SETS)];
        $target = $set[mt_rand(0, count($set) - 1)];
        $length = 6 + $level + ($variant % 3);

        $row = [];
        $targetCount = 0;
        for ($i = 0; $i < $length; $i++) {
            $shape = $set[mt_rand(0, count($set) - 1)];
            if ($shape === $target) {
                $targetCount++;
            }
            $row[] = $shape;
        }
        if ($targetCount === 0) {
            $row[0] = $target;
            $targetCount = 1;
        }

        $rowStr = implode(' ', $row);
        $distractors = array_values(array_unique([$targetCount + 1, max(0, $targetCount - 1), $targetCount + 2]));
        $values = array_slice(array_merge([$targetCount], $distractors), 0, 4);
        shuffle($values);
        [$options, $key] = $this->optionsFromValues($values, $targetCount);

        return [$level, 'mcq_text',
            "How many times does {$target} appear in this row? {$rowStr}",
            "මෙම පේළියේ {$target} කී වතාවක් දිස්වේද? {$rowStr}",
            $options, $key,
            "{$target} appears {$targetCount} time(s) in the row.",
            "{$target} පේළියේ {$targetCount} වතාවක් පෙනී යයි.", ];
    }
}
