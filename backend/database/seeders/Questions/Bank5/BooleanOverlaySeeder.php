<?php

namespace Database\Seeders\Questions\Bank5;

use App\Services\QuestionBank\SvgFigureBuilder;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Boolean visual-overlay reasoning (AND/OR/XOR of two overlapping shapes) -
 * one of the visual archetypes confirmed missing from the bank by the
 * adult-content audit. Uses the new SvgFigureBuilder::combineCells() panel
 * type added this session. The correct answer tile and all 3 distractor
 * tiles are each an ACTUAL boolean combination (AND, OR, XOR, and a
 * decoy "union minus a corner" tile) of the same two source cell sets -
 * never hand-drawn - so correctness is guaranteed by construction and a
 * signature-uniqueness check rejects any instance where two of the 4
 * resulting tiles would look identical.
 */
class BooleanOverlaySeeder extends Seeder
{
    use BuildsQuestions;

    /** 4x4-grid cell-set pairs with genuine partial overlap. */
    private const SHAPE_PAIRS = [
        [[[0, 0], [0, 1], [1, 0], [1, 1], [2, 1]], [[1, 1], [1, 2], [2, 1], [2, 2], [3, 2]]],
        [[[0, 1], [1, 0], [1, 1], [1, 2], [2, 1]], [[1, 1], [2, 0], [2, 1], [2, 2], [3, 1]]],
        [[[0, 0], [0, 1], [0, 2], [1, 1]], [[1, 1], [2, 0], [2, 1], [2, 2]]],
        [[[0, 2], [1, 1], [1, 2], [1, 3], [2, 2]], [[1, 0], [1, 1], [2, 0], [2, 1], [3, 1]]],
    ];

    private SvgFigureBuilder $svg;

    private array $seen = [];

    public function run(): void
    {
        $this->svg = new SvgFigureBuilder();

        $rows = $this->overlayQuestions();

        $this->insertRows('spatial_pattern', $rows, [
            'subcategory' => 'boolean_overlay',
            'exam_tags' => ['spatial_intelligence', 'gov_aptitude', 'boolean_overlay'],
            'cognitive_skill' => 'set-operation-visualization',
        ]);
    }

    private function overlayQuestions(): array
    {
        $rows = [];
        $ops = ['and', 'or', 'xor'];
        $opLabelEn = ['and' => 'BOTH shapes overlap (AND)', 'or' => 'EITHER shape covers (OR)', 'xor' => 'EXACTLY ONE shape covers (XOR)'];
        $opLabelSi = ['and' => 'හැඩ දෙකම (AND)', 'or' => 'ඕනෑම එකක් (OR)', 'xor' => 'හරියටම එකක් (XOR)'];

        $combos = [];
        foreach (array_keys(self::SHAPE_PAIRS) as $pairIdx) {
            foreach ($ops as $op) {
                foreach ([0, 90, 180, 270] as $rotB) {
                    $combos[] = [$pairIdx, $op, $rotB];
                }
            }
        }
        mt_srand(851001);
        shuffle($combos);

        $perLevel = [3 => 20, 4 => 20, 5 => 20];
        $cursor = 0;

        foreach ($perLevel as $level => $count) {
            $made = 0;
            while ($made < $count && $cursor < count($combos)) {
                [$pairIdx, $op, $rotB] = $combos[$cursor++];
                [$cellsA, $cellsBRaw] = self::SHAPE_PAIRS[$pairIdx];
                $cellsB = $this->svg->transformPoly($cellsBRaw, false, $rotB);

                $answerCells = $this->svg->combineCells($cellsA, $cellsB, $op);
                if (empty($answerCells)) {
                    continue;
                }

                $otherOps = array_values(array_diff($ops, [$op]));
                $distractor1 = $this->svg->combineCells($cellsA, $cellsB, $otherOps[0]);
                $distractor2 = $this->svg->combineCells($cellsA, $cellsB, $otherOps[1]);
                // Third distractor: cellsA alone (a plausible-looking but
                // wrong "forgot to combine" tile).
                $distractor3 = $cellsA;

                $sig = fn ($cells) => $this->svg->polySignature($cells);
                $signatures = array_map($sig, [$answerCells, $distractor1, $distractor2, $distractor3]);
                if (count(array_unique($signatures)) !== 4) {
                    continue;
                }

                $signature = "bool|{$pairIdx}|{$op}|{$rotB}";
                if (isset($this->seen[$signature])) {
                    continue;
                }
                $this->seen[$signature] = true;
                $made++;

                // Rendered via the plain 'poly' spec (displays an arbitrary
                // precomputed cell set as-is) - NOT the 'bool' spec, which
                // would recompute a combination from cellsA/cellsB rather
                // than just drawing the already-combined cells.
                $tiles = [
                    ['poly' => $answerCells],
                    ['poly' => $distractor1],
                    ['poly' => $distractor2],
                    ['poly' => $distractor3],
                ];
                $order = [0, 1, 2, 3];
                mt_srand(crc32($signature));
                shuffle($order);
                $options = array_map(fn ($i) => $tiles[$i], $order);
                $correctKey = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

                $questionPanels = [
                    ['poly' => $cellsA],
                    ['poly' => $cellsB],
                ];
                $imagePath = $this->writeSvg('boolean_overlay', $signature, $this->svg->compose($questionPanels, $options, 2, 130));

                $rows[] = [$level, 'mcq_image',
                    "Two shapes (Figure 1 and Figure 2) are shown above. Which option (A-D) shows the result when {$opLabelEn[$op]}?",
                    "රූප දෙකක් ({$opLabelSi[$op]}) ඉහත දැක්වේ. ඒවා ඒකාබද්ධ කළ විට ලැබෙන ප්‍රතිඵලය පෙන්වන්නේ කුමන විකල්පයද?",
                    $this->letterOptions(), $correctKey,
                    "Option {$correctKey} is the exact cell-by-cell {$op} of Figure 1 and Figure 2; the other options are the other boolean operations or an incomplete combination.",
                    "නිවැරදි විකල්පය {$correctKey} වේ.",
                    null,
                    [
                        'subcategory' => 'boolean_overlay', 'image_path' => $imagePath,
                        'solving_time_seconds' => 45 + $level * 14, 'bloom_level' => 'analyze',
                        'generation_rule' => "boolean_{$op}",
                        'transformation_steps' => ['rotate_b_by_'.$rotB, "combine_{$op}"],
                        'visual_complexity_score' => round((2 + ($rotB > 0 ? 1 : 0)) * 0.3, 2),
                    ],
                ];
            }
        }

        return $rows;
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
