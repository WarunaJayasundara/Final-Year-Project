<?php

namespace App\Services\QuestionBank;

/**
 * On-demand version of the logic in database/seeders/Questions/Bank2/SpatialImageSeeder.php's
 * rotationQuestions() - same chirality-verified correctness guarantee (the
 * "mirror" distractors can never accidentally equal a valid rotation of the
 * original), but generating one fresh question per call with real
 * randomness instead of the seeder's fixed reproducible seed, for the admin
 * Pattern/Visual Question Generator (brief requirement #11.C). Only
 * shape_rotation is wired up so far - see AdminVisualQuestionController for
 * the documented scope note on the other archetypes (matrix reasoning,
 * paper folding, cube nets, counting) that SvgFigureBuilder already
 * supports for seeding but aren't yet exposed as an on-demand admin
 * generator.
 */
class VisualQuestionGeneratorService
{
    private const POLY_BASES = [
        's4' => [[0, 0], [0, 1], [1, 1], [1, 2]],
        'l4' => [[0, 0], [1, 0], [2, 0], [2, 1]],
        'p5' => [[0, 0], [0, 1], [1, 0], [1, 1], [2, 0]],
        'f5' => [[0, 1], [0, 2], [1, 0], [1, 1], [2, 1]],
        'n5' => [[0, 1], [1, 1], [2, 0], [2, 1], [3, 0]],
        'y5' => [[0, 1], [1, 0], [1, 1], [2, 1], [3, 1]],
        'l5' => [[0, 0], [1, 0], [2, 0], [3, 0], [3, 1]],
        'j5' => [[0, 0], [0, 1], [1, 1], [2, 1], [3, 1]],
    ];

    public function generateShapeRotation(int $level): array
    {
        $builder = new SvgFigureBuilder();

        $baseName = array_rand(self::POLY_BASES);
        $cells = self::POLY_BASES[$baseName];

        // Same chirality guard as the seeder: verify no rotation of the
        // mirrored shape coincides with any rotation of the original,
        // otherwise a "mirror" distractor could accidentally be a valid
        // rotation and the question would have two correct answers.
        $originals = [];
        foreach ([0, 90, 180, 270] as $r) {
            $originals[] = $builder->polySignature($builder->transformPoly($cells, false, $r));
        }
        foreach ([0, 90, 180, 270] as $r) {
            if (in_array($builder->polySignature($builder->transformPoly($cells, true, $r)), $originals, true)) {
                throw new \RuntimeException("Polyomino base '{$baseName}' is not chiral - unusable for rotation questions.");
            }
        }

        $presentRot = [0, 90, 180, 270][random_int(0, 3)];
        $answerOffset = [90, 180, 270][random_int(0, 2)];
        $answerRot = ($presentRot + $answerOffset) % 360;

        $target = ['poly' => $cells, 'rot' => $presentRot];
        $answer = ['poly' => $cells, 'rot' => $answerRot];

        $mirrorRots = [0, 90, 180, 270];
        unset($mirrorRots[array_rand($mirrorRots)]);
        $distractors = array_map(
            fn ($r) => ['poly' => $cells, 'mirror' => true, 'rot' => $r],
            array_values($mirrorRots)
        );

        $tiles = array_merge([$answer], $distractors);
        $order = range(0, 3);
        shuffle($order);
        $shuffled = array_map(fn ($i) => $tiles[$i], $order);
        $correctKey = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        $svg = $builder->compose([$target], $shuffled, 1, 130);

        $level = max(1, min(5, $level));

        return [
            'image_svg' => $svg,
            'question_text_en' => 'Which option (A–D) shows the SAME figure rotated — not its mirror image?',
            'question_text_si' => 'මෙම හැඩයේ සැබෑ භ්‍රමණය වන්නේ කුමන විකල්පයද, දර්පණ රූපය නොවේ?',
            'options' => [
                ['key' => 'A', 'text_en' => 'A', 'text_si' => 'A'],
                ['key' => 'B', 'text_en' => 'B', 'text_si' => 'B'],
                ['key' => 'C', 'text_en' => 'C', 'text_si' => 'C'],
                ['key' => 'D', 'text_en' => 'D', 'text_si' => 'D'],
            ],
            'correct_option_key' => $correctKey,
            'explanation_en' => "Option {$correctKey} is the figure rotated by {$answerOffset}°; the other options are mirror images.",
            'explanation_si' => "නිවැරදි විකල්පය {$correctKey} වේ.",
            'subcategory' => 'shape_rotation',
            'difficulty_weight' => min(3, max(1, (int) ceil($level / 2))),
            'solving_time_seconds' => 25 + $level * 10,
            'bloom_level' => 'apply',
            'cognitive_skill' => 'mental-rotation',
            'generation_rule' => 'shape_rotation_v1',
            'transformation_steps' => [
                'base_polyomino' => $baseName,
                'presented_rotation_degrees' => $presentRot,
                'answer_rotation_degrees' => $answerRot,
                'rotation_offset_degrees' => $answerOffset,
            ],
            'visual_complexity_score' => count($cells) / 5,
        ];
    }
}
