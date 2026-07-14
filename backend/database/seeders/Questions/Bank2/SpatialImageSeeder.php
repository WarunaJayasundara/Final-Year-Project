<?php

namespace Database\Seeders\Questions\Bank2;

use App\Services\QuestionBank\SvgFigureBuilder;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Spatial-intelligence image bank: polyomino shape rotation (true rotation
 * vs mirrored distractors), mirror images of glyph strings, paper folding
 * with punched holes, cube nets (opposite faces), and grid counting. Every
 * answer is exact by construction - rotation uses a chirality check so a
 * mirrored shape can never pass as a rotation, folding computes the
 * unfolded holes as reflections, cube nets use hand-verified fold
 * mappings, and counting uses closed-form formulas.
 */
class SpatialImageSeeder extends Seeder
{
    use BuildsQuestions;

    /** Chiral polyomino bases on the 4x4 grid (mirrors added programmatically). */
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

    /** Glyphs with no reflective/rotational self-symmetry in a bold sans face. */
    private const MIRROR_GLYPHS = ['F', 'G', 'J', 'L', 'P', 'Q', 'R', '2', '4', '5', '7'];

    /** Net layouts: cell coordinates + opposite-face index pairs (hand-verified fold mappings). */
    private const NETS = [
        'cross' => [
            'cells' => [[1, 0], [0, 1], [1, 1], [2, 1], [1, 2], [1, 3]],
            'opposites' => [[0, 4], [1, 3], [2, 5]],
        ],
        'tstrip' => [
            'cells' => [[0, 1], [1, 1], [2, 1], [3, 1], [1, 0], [1, 2]],
            'opposites' => [[0, 2], [1, 3], [4, 5]],
        ],
    ];

    private const NET_SHAPES = ['triangle', 'square', 'circle', 'diamond', 'star', 'cross', 'pentagon', 'hexagon', 'arrow'];

    private SvgFigureBuilder $svg;

    private array $seen = [];

    public function run(): void
    {
        $this->svg = new SvgFigureBuilder();

        $rows = array_merge(
            $this->rotationQuestions(),
            $this->mirrorQuestions(),
            $this->foldingQuestions(),
            $this->cubeNetQuestions(),
            $this->countingQuestions(),
        );

        $this->insertRows('spatial_pattern', $rows, [
            'exam_tags' => ['spatial_intelligence', 'gov_aptitude', 'armed_forces_aptitude'],
            'cognitive_skill' => 'spatial-visualization',
        ]);
    }

    // ---------------------------------------------------------------
    // Shape rotation (true rotation vs mirror distractors)
    // ---------------------------------------------------------------

    /** @return array<int,array> */
    private function rotationQuestions(): array
    {
        $builder = new SvgFigureBuilder();

        // Check each base is chiral: no rotation of the mirror should equal
        // a rotation of the original, or a "mirrored" distractor could
        // accidentally be a valid rotation.
        $bases = [];
        foreach (self::POLY_BASES as $name => $cells) {
            $originals = [];
            foreach ([0, 90, 180, 270] as $r) {
                $originals[] = $builder->polySignature($builder->transformPoly($cells, false, $r));
            }
            $chiral = true;
            foreach ([0, 90, 180, 270] as $r) {
                if (in_array($builder->polySignature($builder->transformPoly($cells, true, $r)), $originals, true)) {
                    $chiral = false;
                }
            }
            if (! $chiral) {
                throw new \RuntimeException("Polyomino base '{$name}' is not chiral - unusable for rotation questions.");
            }
            $bases[$name] = $cells;
            $bases[$name.'m'] = array_map(fn ($c) => [$c[0], 3 - $c[1]], $cells); // mirrored twin, used as an extra base
        }

        $combos = [];
        foreach (array_keys($bases) as $name) {
            foreach ([0, 90, 180, 270] as $presentRot) {
                foreach ([90, 180, 270] as $answerRot) {
                    foreach ([0, 1] as $variant) {
                        $combos[] = [$name, $presentRot, $answerRot, $variant];
                    }
                }
            }
        }
        mt_srand(730001);
        shuffle($combos);

        $perLevel = [2 => 40, 3 => 50, 4 => 55, 5 => 55];
        if (array_sum($perLevel) > count($combos)) {
            throw new \RuntimeException('Rotation combo pool smaller than requested volume.');
        }
        $rows = [];
        $cursor = 0;

        foreach ($perLevel as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$name, $presentRot, $answerRot, $variant] = $combos[$cursor++];
                $cells = $bases[$name];

                $target = ['poly' => $cells, 'rot' => $presentRot];
                $answer = ['poly' => $cells, 'rot' => ($presentRot + $answerRot) % 360];

                // Distractors: mirrored versions at three of the four possible
                // rotations - the variant picks which rotation is left out.
                $mirrorRots = [0, 90, 180, 270];
                unset($mirrorRots[(intdiv($presentRot, 90) + $variant * 2 + 1) % 4]);
                $distractors = array_map(
                    fn ($r) => ['poly' => $cells, 'mirror' => true, 'rot' => $r],
                    array_values($mirrorRots)
                );

                $signature = "rot|{$name}|{$presentRot}|{$answerRot}|".implode(',', array_map(fn ($d) => $d['rot'], $distractors));
                [$options, $correctKey] = $this->shuffleTiles($answer, $distractors, $signature);
                $imagePath = $this->writeSvg('shape_rotation', $signature, $this->svg->compose([$target], $options, 1, 130));

                $rows[] = [$level, 'mcq_image',
                    'Which option (A–D) shows the SAME figure rotated — not its mirror image?',
                    'මෙම හැඩයේ සැබෑ භ්‍රමණය වන්නේ කුමන විකල්පයද, දර්පණ රූපය නොවේ?',
                    $this->letterOptions(), $correctKey,
                    "Option {$correctKey} is the figure rotated by {$answerRot}°; the other options are mirror images.",
                    "නිවැරදි විකල්පය {$correctKey} වේ.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'shape_rotation', 'image_path' => $imagePath,
                        'solving_time_seconds' => 25 + $level * 10, 'bloom_level' => 'apply',
                        'cognitive_skill' => 'mental-rotation'],
                ];
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Mirror images of glyph strings
    // ---------------------------------------------------------------

    /** @return array<int,array> */
    private function mirrorQuestions(): array
    {
        $perLevel = [1 => 50, 2 => 50, 3 => 50, 4 => 50];
        $lengthByLevel = [1 => 3, 2 => 4, 3 => 4, 4 => 5];
        $rows = [];
        mt_srand(740001);

        foreach ($perLevel as $level => $count) {
            $made = 0;
            $guard = 0;
            while ($made < $count && $guard < $count * 50) {
                $guard++;
                $glyphs = self::MIRROR_GLYPHS;
                shuffle($glyphs);
                $text = implode('', array_slice($glyphs, 0, $lengthByLevel[$level]));

                $signature = "mir|{$text}";
                if (isset($this->seen[$signature])) {
                    continue;
                }
                $this->seen[$signature] = true;
                $made++;

                $answer = ['text' => $text, 'mode' => 'mirrorH'];
                $distractors = [
                    ['text' => $text, 'mode' => 'plain'],
                    ['text' => $text, 'mode' => 'rot180'],
                    ['text' => $text, 'mode' => 'mirrorV'],
                ];

                [$options, $correctKey] = $this->shuffleTiles($answer, $distractors, $signature);
                $imagePath = $this->writeSvg('mirror_image', $signature, $this->svg->compose([['text' => $text, 'mode' => 'plain']], $options, 1, 130));

                $rows[] = [$level, 'mcq_image',
                    'A vertical mirror is placed beside the figure. Which option (A–D) shows its exact mirror image?',
                    'දර්පණ රූපය තෝරන්න.',
                    $this->letterOptions(), $correctKey,
                    "A vertical mirror flips the figure left-to-right, giving option {$correctKey}; the others are the original, an upside-down rotation, and a vertical flip.",
                    "නිවැරදි විකල්පය {$correctKey} වේ.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'mirror_image', 'image_path' => $imagePath,
                        'solving_time_seconds' => 20 + $level * 8, 'bloom_level' => 'apply',
                        'cognitive_skill' => 'visual-discrimination'],
                ];
            }
            if ($made < $count) {
                throw new \RuntimeException("Mirror L{$level}: only {$made}/{$count} generated.");
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Paper folding
    // ---------------------------------------------------------------

    /** @return array<int,array> */
    private function foldingQuestions(): array
    {
        $rows = [];
        mt_srand(750001);

        $configs = [
            2 => ['folds' => 1, 'holes' => 1, 'count' => 45],
            3 => ['folds' => 1, 'holes' => 2, 'count' => 45],
            4 => ['folds' => 2, 'holes' => 1, 'count' => 40],
            5 => ['folds' => 2, 'holes' => 2, 'count' => 35],
        ];

        foreach ($configs as $level => $cfg) {
            $made = 0;
            $guard = 0;
            while ($made < $cfg['count'] && $guard < $cfg['count'] * 80) {
                $guard++;
                $vertical = $cfg['folds'] === 1 ? mt_rand(0, 1) === 0 : true;

                // Punch positions inside the folded (visible) region - the
                // two-fold variants use a finer grid since the visible
                // quarter is small.
                $positions = $cfg['folds'] === 2
                    ? [0.05, 0.11, 0.17, 0.23, 0.29, 0.35, 0.41]
                    : [0.09, 0.17, 0.25, 0.33, 0.41];
                $holes = [];
                while (count($holes) < $cfg['holes']) {
                    $fx = $positions[mt_rand(0, count($positions) - 1)];
                    $fy = $positions[mt_rand(0, count($positions) - 1)];
                    if ($cfg['folds'] === 1 && ! $vertical) {
                        [$fx, $fy] = [$fy + mt_rand(0, 1) * 0.48, $fx]; // horizontal fold: x free, y in top half
                    } elseif ($cfg['folds'] === 1) {
                        $fy = $fy + mt_rand(0, 1) * 0.48; // vertical fold: y free
                    }
                    $hole = [round($fx, 2), round($fy, 2)];
                    if (! in_array($hole, $holes, true)) {
                        $holes[] = $hole;
                    }
                }
                sort($holes);

                $signature = 'fold|'.$cfg['folds'].'|'.($vertical ? 'v' : 'h').'|'.json_encode($holes);
                if (isset($this->seen[$signature])) {
                    continue;
                }

                $unfolded = $this->unfoldHoles($holes, $cfg['folds'], $vertical);
                $distractors = $this->foldingDistractors($holes, $unfolded, $cfg['folds'], $vertical);
                if ($distractors === null) {
                    continue;
                }
                $this->seen[$signature] = true;
                $made++;

                $foldLines = $cfg['folds'] === 2 ? ['v', 'h'] : [$vertical ? 'v' : 'h'];
                $sheetState = $cfg['folds'] === 2 ? 'quarter' : ($vertical ? 'half-v' : 'half-h');
                $questionPanels = [
                    ['sheet' => 'full', 'holes' => [], 'foldLines' => $foldLines],
                    ['sheet' => $sheetState, 'holes' => $holes, 'foldLines' => []],
                ];

                $options = [];
                $optionSpecs = array_merge([$unfolded], $distractors);
                $order = range(0, 3);
                mt_srand(crc32($signature));
                shuffle($order);
                foreach ($order as $idx) {
                    $options[] = ['sheet' => 'full', 'holes' => $optionSpecs[$idx], 'foldLines' => []];
                }
                $correctKey = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

                $imagePath = $this->writeSvg('paper_folding', $signature, $this->svg->compose($questionPanels, $options, 2, 120));

                $foldDesc = $cfg['folds'] === 2 ? 'in half twice (right onto left, then bottom onto top)' : ($vertical ? 'in half (right onto left)' : 'in half (bottom onto top)');

                $rows[] = [$level, 'mcq_image',
                    "A square sheet is folded {$foldDesc} along the dashed line(s), and hole(s) are punched as shown. Which option (A–D) shows the sheet after unfolding?",
                    'රූපය බලන්න. නිවැරදි විකල්පය තෝරන්න.',
                    $this->letterOptions(), $correctKey,
                    'Each fold mirrors the punched holes across the fold line when unfolded, giving option '.$correctKey.'.',
                    "නිවැරදි විකල්පය {$correctKey} වේ.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'paper_folding', 'image_path' => $imagePath,
                        'solving_time_seconds' => 35 + $level * 12, 'bloom_level' => 'analyze'],
                ];
            }
            if ($made < $cfg['count']) {
                throw new \RuntimeException("Folding L{$level}: only {$made}/{$cfg['count']} generated.");
            }
        }

        return $rows;
    }

    /** @return array<int,array{0:float,1:float}> */
    private function unfoldHoles(array $holes, int $folds, bool $vertical): array
    {
        $result = $holes;
        if ($folds >= 1) {
            foreach ($holes as [$x, $y]) {
                $result[] = $vertical || $folds === 2 ? [round(1 - $x, 2), $y] : [$x, round(1 - $y, 2)];
            }
        }
        if ($folds === 2) {
            foreach ($result as [$x, $y]) {
                $result[] = [$x, round(1 - $y, 2)];
            }
        }
        $result = array_values(array_unique($result, SORT_REGULAR));
        sort($result);

        return $result;
    }

    /** Three wrong unfolded layouts, all distinct from the answer and each other. */
    private function foldingDistractors(array $holes, array $unfolded, int $folds, bool $vertical): ?array
    {
        $candidates = [];

        // Forgot to mirror at all.
        $plain = $holes;
        sort($plain);
        $candidates[] = $plain;

        // Mirrored across the wrong axis.
        $wrongAxis = $holes;
        foreach ($holes as [$x, $y]) {
            $wrongAxis[] = $vertical ? [$x, round(1 - $y, 2)] : [round(1 - $x, 2), $y];
        }
        $wrongAxis = array_values(array_unique($wrongAxis, SORT_REGULAR));
        sort($wrongAxis);
        $candidates[] = $wrongAxis;

        // Only one fold applied (for 2-fold questions) or an extra spurious hole.
        if ($folds === 2) {
            $partial = $holes;
            foreach ($holes as [$x, $y]) {
                $partial[] = [round(1 - $x, 2), $y];
            }
            $partial = array_values(array_unique($partial, SORT_REGULAR));
            sort($partial);
            $candidates[] = $partial;
        }
        $extra = $unfolded;
        $extra[] = [0.6, 0.6];
        $extra = array_values(array_unique($extra, SORT_REGULAR));
        sort($extra);
        $candidates[] = $extra;

        $answerSig = json_encode($unfolded);
        $chosen = [];
        $sigs = [$answerSig];
        foreach ($candidates as $candidate) {
            if (count($chosen) === 3) {
                break;
            }
            $sig = json_encode($candidate);
            if (! in_array($sig, $sigs, true)) {
                $chosen[] = $candidate;
                $sigs[] = $sig;
            }
        }

        return count($chosen) === 3 ? $chosen : null;
    }

    // ---------------------------------------------------------------
    // Cube nets (opposite faces)
    // ---------------------------------------------------------------

    /** @return array<int,array> */
    private function cubeNetQuestions(): array
    {
        $rows = [];
        mt_srand(760001);

        $perLevel = [3 => ['layout' => 'cross', 'count' => 38], 4 => ['layout' => 'tstrip', 'count' => 38], 5 => ['layout' => null, 'count' => 39]];

        foreach ($perLevel as $level => $cfg) {
            $made = 0;
            $guard = 0;
            while ($made < $cfg['count'] && $guard < $cfg['count'] * 60) {
                $guard++;
                $layoutName = $cfg['layout'] ?? (mt_rand(0, 1) === 0 ? 'cross' : 'tstrip');
                $net = self::NETS[$layoutName];

                if ($level >= 5) {
                    // Expert variant: opposite faces can share a shape and
                    // differ only by fill.
                    $shapePool = self::NET_SHAPES;
                    shuffle($shapePool);
                    $three = array_slice($shapePool, 0, 3);
                    $faces = [];
                    foreach ($three as $s) {
                        $faces[] = ['shape' => $s, 'fill' => true];
                        $faces[] = ['shape' => $s, 'fill' => false];
                    }
                    shuffle($faces);
                } else {
                    $shapePool = self::NET_SHAPES;
                    shuffle($shapePool);
                    $faces = array_map(fn ($s) => ['shape' => $s, 'fill' => true], array_slice($shapePool, 0, 6));
                }

                $targetIdx = mt_rand(0, 5);
                $oppositeIdx = null;
                foreach ($net['opposites'] as [$a, $b]) {
                    if ($a === $targetIdx) {
                        $oppositeIdx = $b;
                    }
                    if ($b === $targetIdx) {
                        $oppositeIdx = $a;
                    }
                }

                $signature = "net|{$layoutName}|{$targetIdx}|".json_encode($faces);
                if (isset($this->seen[$signature])) {
                    continue;
                }

                $answerFace = $faces[$oppositeIdx];
                $otherIdx = array_values(array_diff(range(0, 5), [$targetIdx, $oppositeIdx]));
                shuffle($otherIdx);
                $distractorFaces = array_map(fn ($i) => $faces[$i], array_slice($otherIdx, 0, 3));

                // All four option faces must be visually distinct.
                $optionSigs = array_map(fn ($f) => $f['shape'].(int) $f['fill'], array_merge([$answerFace], $distractorFaces));
                if (count(array_unique($optionSigs)) !== 4) {
                    continue;
                }
                $this->seen[$signature] = true;
                $made++;

                $netCells = [];
                foreach ($net['cells'] as $i => [$gx, $gy]) {
                    $netCells[] = [$gx, $gy, $faces[$i], $i === $targetIdx];
                }

                [$options, $correctKey] = $this->shuffleTiles($answerFace, $distractorFaces, $signature);
                $imagePath = $this->writeSvg('cube_net', $signature, $this->svg->compose([['net' => $netCells]], $options, 1, 220));

                $rows[] = [$level, 'mcq_image',
                    'The net shown is folded into a cube. Which symbol (A–D) appears on the face OPPOSITE the highlighted face?',
                    'රූපය බලන්න. නිවැරදි විකල්පය තෝරන්න.',
                    $this->letterOptions(), $correctKey,
                    "Folding the net, the highlighted face and option {$correctKey}'s symbol end up on opposite sides of the cube.",
                    "නිවැරදි විකල්පය {$correctKey} වේ.",
                    min(3, max(1, (int) ceil($level / 2))),
                    ['subcategory' => 'cube_net', 'image_path' => $imagePath,
                        'solving_time_seconds' => 40 + $level * 12, 'bloom_level' => 'analyze'],
                ];
            }
            if ($made < $cfg['count']) {
                throw new \RuntimeException("Cube net L{$level}: only {$made}/{$cfg['count']} generated.");
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Grid counting (squares / rectangles of all sizes)
    // ---------------------------------------------------------------

    /** @return array<int,array> */
    private function countingQuestions(): array
    {
        $rows = [];

        $variants = [
            // [level, rows, cols, kind]
            [2, 3, 3, 'squares'], [2, 3, 4, 'squares'], [2, 2, 4, 'rectangles'], [2, 2, 3, 'rectangles'], [2, 4, 4, 'squares'],
            [3, 4, 4, 'rectangles'], [3, 3, 4, 'rectangles'], [3, 4, 5, 'squares'], [3, 3, 5, 'squares'], [3, 2, 5, 'rectangles'],
            [4, 5, 5, 'squares'], [4, 3, 5, 'rectangles'], [4, 4, 5, 'rectangles'], [4, 4, 6, 'squares'], [4, 5, 6, 'squares'],
            [5, 6, 6, 'squares'], [5, 5, 5, 'rectangles'], [5, 4, 6, 'rectangles'], [5, 5, 6, 'rectangles'], [5, 6, 7, 'squares'],
        ];

        foreach ($variants as [$level, $r, $c, $kind]) {
            $answer = $kind === 'squares' ? $this->countSquares($r, $c) : $this->countRectangles($r, $c);

            $signature = "cnt|{$r}x{$c}|{$kind}";
            $imagePath = $this->writeSvg('grid_counting', $signature, $this->svg->compose([['grid' => [$r, $c]]], [], 1, 170));

            $wrongs = array_values(array_unique(array_filter([
                $kind === 'squares' ? $this->countRectangles($r, $c) : $this->countSquares($r, $c),
                $answer + $r,
                $answer - $c,
                $answer + $r * $c,
                (int) round($answer * 1.5),
            ], fn ($w) => $w > 0 && $w !== $answer)));
            mt_srand(crc32($signature));
            shuffle($wrongs);
            $values = array_merge([$answer], array_slice($wrongs, 0, 3));
            shuffle($values);
            $labels = array_map('strval', $values);
            $correctKey = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];

            $kindEn = $kind === 'squares' ? 'squares' : 'rectangles';
            $formula = $kind === 'squares'
                ? 'counting k×k squares for every size k'
                : 'C('.($r + 1).',2) × C('.($c + 1).',2) ways to pick two horizontal and two vertical lines';

            $rows[] = [$level, 'mcq_image',
                "How many {$kindEn} of ALL sizes does this {$r}×{$c} grid contain?",
                'රූපය බලන්න. නිවැරදි විකල්පය තෝරන්න.',
                $this->options($labels, $labels), $correctKey,
                "Counting all sizes ({$formula}) gives {$answer}.",
                "නිවැරදි විකල්පය {$correctKey} වේ.",
                min(3, max(1, (int) ceil($level / 2))),
                ['subcategory' => 'embedded_counting', 'image_path' => $imagePath,
                    'solving_time_seconds' => 45 + $level * 10, 'bloom_level' => 'analyze',
                    'cognitive_skill' => 'systematic-enumeration'],
            ];
        }

        return $rows;
    }

    private function countSquares(int $rows, int $cols): int
    {
        $total = 0;
        for ($k = 1; $k <= min($rows, $cols); $k++) {
            $total += ($rows - $k + 1) * ($cols - $k + 1);
        }

        return $total;
    }

    private function countRectangles(int $rows, int $cols): int
    {
        return (int) (($rows + 1) * $rows / 2 * (($cols + 1) * $cols / 2));
    }

    // ---------------------------------------------------------------
    // Shared helpers
    // ---------------------------------------------------------------

    /** @return array{0: array<int, array>, 1: string} */
    private function shuffleTiles(array $answer, array $distractors, string $seedKey): array
    {
        $tiles = array_merge([$answer], $distractors);
        $order = range(0, 3);
        mt_srand(crc32($seedKey));
        shuffle($order);
        $shuffled = array_map(fn ($i) => $tiles[$i], $order);
        $correctKey = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        return [$shuffled, $correctKey];
    }

    private function letterOptions(): array
    {
        return $this->options(['A', 'B', 'C', 'D'], ['A', 'B', 'C', 'D']);
    }

    private function writeSvg(string $subcategory, string $signature, string $svg): string
    {
        $path = 'questions/generated/'.$subcategory.'/'.md5($signature).'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
