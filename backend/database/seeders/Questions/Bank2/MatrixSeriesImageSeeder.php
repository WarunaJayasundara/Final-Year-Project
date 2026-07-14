<?php

namespace Database\Seeders\Questions\Bank2;

use App\Services\QuestionBank\SvgFigureBuilder;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Abstract-reasoning image bank: Raven-style 3x3 matrix reasoning and
 * figure-series completion, rendered as composite SVGs (question figure +
 * labelled A-D answer tiles). Each answer comes from the generating rule,
 * and each distractor is a single-attribute mutation checked for visual
 * distinctness (including shape rotational symmetry) before being
 * accepted - a bad item throws instead of seeding silently.
 */
class MatrixSeriesImageSeeder extends Seeder
{
    use BuildsQuestions;

    private const ALL_SHAPES = ['triangle', 'square', 'circle', 'diamond', 'pentagon', 'hexagon', 'star', 'cross', 'arrow', 'flag'];

    /** Shapes safe for rotation-based rules, with their rotational symmetry order (degrees). */
    private const ROT_SHAPES = ['arrow' => 360, 'flag' => 360, 'lshape' => 360, 'semicircle' => 360, 'triangle' => 120, 'pentagon' => 72];

    private const MATRIX_PER_LEVEL = [1 => 60, 2 => 70, 3 => 80, 4 => 80, 5 => 60];
    private const SERIES_PER_LEVEL = [1 => 60, 2 => 70, 3 => 80, 4 => 80, 5 => 60];

    private SvgFigureBuilder $svg;

    /** @var array<string,bool> global signature registry to prevent duplicate items */
    private array $seen = [];

    public function run(): void
    {
        $this->svg = new SvgFigureBuilder();
        $rows = [];

        foreach (self::MATRIX_PER_LEVEL as $level => $count) {
            $made = 0;
            $attempt = 0;
            mt_srand(710000 + $level * 1000);
            while ($made < $count && $attempt < $count * 60) {
                $attempt++;
                $row = $this->buildMatrixQuestion($level, $attempt);
                if ($row !== null) {
                    $rows[] = $row;
                    $made++;
                }
            }
            if ($made < $count) {
                throw new \RuntimeException("Matrix L{$level}: only {$made}/{$count} unique questions generated.");
            }
        }

        foreach (self::SERIES_PER_LEVEL as $level => $count) {
            $made = 0;
            $attempt = 0;
            mt_srand(720000 + $level * 1000);
            while ($made < $count && $attempt < $count * 60) {
                $attempt++;
                $row = $this->buildSeriesQuestion($level, $attempt);
                if ($row !== null) {
                    $rows[] = $row;
                    $made++;
                }
            }
            if ($made < $count) {
                throw new \RuntimeException("Series L{$level}: only {$made}/{$count} unique questions generated.");
            }
        }

        $this->insertRows('spatial_pattern', $rows, [
            'exam_tags' => ['abstract_reasoning', 'gov_aptitude', 'graduate_recruitment'],
            'cognitive_skill' => 'abstract-visual-reasoning',
        ]);
    }

    // ---------------------------------------------------------------
    // Matrix reasoning
    // ---------------------------------------------------------------

    /** Cell spec for (row, col) under the given rule configuration. */
    private function matrixCell(array $cfg, int $row, int $col): array
    {
        $shape = in_array('shapeCycle', $cfg['rules'], true)
            ? $cfg['shapes'][($row + $col) % 3]
            : $cfg['shapes'][$row % count($cfg['shapes'])];

        return [
            'shape' => $shape,
            'count' => in_array('count', $cfg['rules'], true) ? $cfg['counts'][$col] : 1,
            'rot' => in_array('rot', $cfg['rules'], true) ? $col * $cfg['rotStep'] : 0,
            'size' => in_array('size', $cfg['rules'], true) ? [0.65, 0.85, 1.05][$col] : 0.95,
            'fill' => in_array('fill', $cfg['rules'], true) ? ($row + $col) % 2 === 0 : true,
        ];
    }

    private function buildMatrixQuestion(int $level, int $attempt): ?array
    {
        $rules = match ($level) {
            1 => $attempt % 2 === 0 ? ['shapeCycle'] : ['count'],
            2 => $attempt % 2 === 0 ? ['rot'] : ['size'],
            3 => ['shapeCycle', 'count'],
            4 => ['shapeCycle', 'rot'],
            default => $attempt % 2 === 0 ? ['shapeCycle', 'rot', 'fill'] : ['shapeCycle', 'count', 'size'],
        };

        $usesRot = in_array('rot', $rules, true);
        $pool = $usesRot ? array_keys(self::ROT_SHAPES) : self::ALL_SHAPES;
        shuffle($pool);
        $shapes = array_slice($pool, 0, 3);

        $cfg = [
            'rules' => $rules,
            'shapes' => $shapes,
            'counts' => mt_rand(0, 1) ? [1, 2, 3] : [3, 2, 1],
            'rotStep' => $level >= 4 ? 45 : 90,
        ];

        $signature = 'mx|'.$level.'|'.implode(',', $rules).'|'.implode(',', $shapes).'|'.implode(',', $cfg['counts']).'|'.$cfg['rotStep'];
        if (isset($this->seen[$signature])) {
            return null;
        }

        $answer = $this->matrixCell($cfg, 2, 2);
        $distractors = $this->mutateSpec($answer, $cfg, 3);
        if ($distractors === null) {
            return null;
        }
        $this->seen[$signature] = true;

        $panels = [];
        for ($r = 0; $r < 3; $r++) {
            for ($c = 0; $c < 3; $c++) {
                $panels[] = ($r === 2 && $c === 2) ? null : $this->matrixCell($cfg, $r, $c);
            }
        }

        [$options, $correctKey] = $this->shuffledOptionTiles($answer, $distractors, $signature);
        $imagePath = $this->writeSvg('matrix_reasoning', $signature, $this->svg->compose($panels, $options, 3));

        $ruleDesc = $this->describeRules($rules, $cfg);

        return [$level, 'mcq_image',
            'Study the 3×3 matrix. Which option (A–D) replaces the question mark to complete the pattern?',
            'නැතිවූ රූපය සොයන්න. නිවැරදි විකල්පය තෝරන්න.',
            $this->letterOptions(), $correctKey,
            "In each row the same rule applies across the columns ({$ruleDesc}); applying it to the third row gives option {$correctKey}.",
            "රටාවට අනුව නිවැරදි විකල්පය {$correctKey} වේ.",
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'matrix_reasoning', 'image_path' => $imagePath,
                'solving_time_seconds' => 30 + $level * 12, 'bloom_level' => $level >= 4 ? 'analyze' : 'apply'],
        ];
    }

    // ---------------------------------------------------------------
    // Figure series
    // ---------------------------------------------------------------

    /** Spec for position $i (0-4) in the series under the config. */
    private function seriesCell(array $cfg, int $i): array
    {
        $rot = match ($cfg['kind']) {
            'rot' => $cfg['start'] + $i * $cfg['step'],
            'rot_fill' => $cfg['start'] + $i * $cfg['step'],
            'two_shape_rot' => $cfg['start'] + $i * 90,
            'accel_rot' => $cfg['start'] + [0, 45, 135, 270, 450][$i],
            default => 0,
        };

        return [
            'shape' => $cfg['kind'] === 'two_shape_rot' ? $cfg['shapes'][$i % 2] : $cfg['shapes'][0],
            'count' => match ($cfg['kind']) {
                'count_cycle' => [1, 2, 3][($i + $cfg['phase']) % 3],
                'count_steps' => $cfg['phase'] % 2 === 0 ? [1, 2, 4, 5, 7][$i] : [2, 3, 5, 6, 8][$i],
                default => 1,
            },
            'rot' => $rot % 360,
            'size' => $cfg['kind'] === 'size' ? [0.55, 0.7, 0.85, 1.0, 1.15][$i] : 0.95,
            'fill' => $cfg['kind'] === 'rot_fill' ? $i % 2 === 0 : $cfg['baseFill'],
        ];
    }

    private function buildSeriesQuestion(int $level, int $attempt): ?array
    {
        $kind = match ($level) {
            1 => 'rot',
            2 => $attempt % 3 === 0 ? 'size' : 'count_cycle',
            3 => 'rot_fill',
            4 => 'two_shape_rot',
            default => $attempt % 2 === 0 ? 'accel_rot' : 'count_steps',
        };

        $needsRotShape = in_array($kind, ['rot', 'rot_fill', 'two_shape_rot', 'accel_rot'], true);
        $pool = $needsRotShape ? array_keys(self::ROT_SHAPES) : self::ALL_SHAPES;
        shuffle($pool);

        $cfg = [
            'kind' => $kind,
            'shapes' => array_slice($pool, 0, 2),
            'start' => mt_rand(0, 7) * 45,
            'step' => mt_rand(0, 1) === 0 ? 45 : 90,
            'phase' => mt_rand(0, 2),
            'baseFill' => mt_rand(0, 1) === 1,
        ];

        // Signature covers only the attributes the kind actually renders, so
        // two configs that would produce identical images share a signature.
        $usedShapes = $kind === 'two_shape_rot' ? $cfg['shapes'] : [$cfg['shapes'][0]];
        $usedStart = $needsRotShape ? $cfg['start'] : 0;
        $usedStep = in_array($kind, ['rot', 'rot_fill'], true) ? $cfg['step'] : 0;
        $usedPhase = in_array($kind, ['count_cycle', 'count_steps'], true) ? $cfg['phase'] : 0;
        $usedFill = $kind === 'rot_fill' ? 0 : (int) $cfg['baseFill'];
        $signature = 'sr|'.$level.'|'.$kind.'|'.implode(',', $usedShapes)."|{$usedStart}|{$usedStep}|{$usedPhase}|{$usedFill}";
        if (isset($this->seen[$signature])) {
            return null;
        }

        $answer = $this->seriesCell($cfg, 4);
        $distractors = $this->mutateSpec($answer, ['rules' => ['rot', 'count'], 'rotStep' => 45, 'shapes' => $cfg['shapes'], 'counts' => [1, 2, 3]], 3);
        if ($distractors === null) {
            return null;
        }
        $this->seen[$signature] = true;

        $panels = [];
        foreach (range(0, 3) as $i) {
            $panels[] = $this->seriesCell($cfg, $i);
        }
        $panels[] = null;

        [$options, $correctKey] = $this->shuffledOptionTiles($answer, $distractors, $signature);
        $imagePath = $this->writeSvg('figure_series', $signature, $this->svg->compose($panels, $options, 5));

        return [$level, 'mcq_image',
            'The figures follow a logical sequence. Which option (A–D) comes next?',
            'මෙම රටාවේ ඊළඟට එන්නේ කුමක්ද? නිවැරදි විකල්පය තෝරන්න.',
            $this->letterOptions(), $correctKey,
            "Continuing the sequence rule ({$this->describeSeriesKind($kind)}) gives option {$correctKey}.",
            "රටාවට අනුව නිවැරදි විකල්පය {$correctKey} වේ.",
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'figure_series', 'image_path' => $imagePath,
                'solving_time_seconds' => 25 + $level * 10, 'bloom_level' => $level >= 4 ? 'analyze' : 'apply'],
        ];
    }

    // ---------------------------------------------------------------
    // Shared helpers
    // ---------------------------------------------------------------

    /**
     * Builds $n distractor specs, each differing from the answer by exactly
     * one attribute, all pairwise visually distinct (rotation compared
     * modulo the shape's rotational symmetry). Returns null when a clean
     * set can't be formed for this config.
     */
    private function mutateSpec(array $answer, array $cfg, int $n): ?array
    {
        $candidates = [];

        $otherShapes = array_values(array_diff($cfg['shapes'], [$answer['shape']]));
        foreach ($otherShapes as $shape) {
            $candidates[] = array_merge($answer, ['shape' => $shape]);
        }
        foreach ([$cfg['rotStep'], -$cfg['rotStep'], 2 * $cfg['rotStep']] as $delta) {
            $candidates[] = array_merge($answer, ['rot' => ($answer['rot'] + $delta + 360) % 360]);
        }
        foreach ([1, -1, 2] as $delta) {
            $count = $answer['count'] + $delta;
            if ($count >= 1 && $count <= 9) {
                $candidates[] = array_merge($answer, ['count' => $count]);
            }
        }
        $candidates[] = array_merge($answer, ['fill' => ! $answer['fill']]);

        $chosen = [];
        foreach ($candidates as $candidate) {
            if (count($chosen) === $n) {
                break;
            }
            $distinct = $this->visuallyDistinct($candidate, $answer);
            foreach ($chosen as $existing) {
                $distinct = $distinct && $this->visuallyDistinct($candidate, $existing);
            }
            if ($distinct) {
                $chosen[] = $candidate;
            }
        }

        return count($chosen) === $n ? $chosen : null;
    }

    private function visuallyDistinct(array $a, array $b): bool
    {
        if ($a['shape'] !== $b['shape'] || $a['count'] !== $b['count'] || $a['fill'] !== $b['fill']) {
            return true;
        }
        if (abs($a['size'] - $b['size']) > 0.01) {
            return true;
        }
        $sym = self::ROT_SHAPES[$a['shape']] ?? $this->fallbackSymmetry($a['shape']);

        return ($a['rot'] % $sym + $sym) % $sym !== ($b['rot'] % $sym + $sym) % $sym;
    }

    private function fallbackSymmetry(string $shape): int
    {
        return match ($shape) {
            'circle' => 1,
            'square', 'diamond', 'cross' => 90,
            'hexagon' => 60,
            'star' => 36,
            default => 360,
        };
    }

    /** @return array{0: array<int, array>, 1: string} [4 option tile specs in display order, correct key] */
    private function shuffledOptionTiles(array $answer, array $distractors, string $seedKey): array
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

    private function describeRules(array $rules, array $cfg): string
    {
        $parts = [];
        foreach ($rules as $rule) {
            $parts[] = match ($rule) {
                'shapeCycle' => 'the three shapes cycle position by position',
                'count' => 'the number of shapes changes by one each column',
                'rot' => "the rotation advances by {$cfg['rotStep']}\u{00b0} each column",
                'size' => 'the size grows each column',
                'fill' => 'filled and outlined shapes alternate',
                default => $rule,
            };
        }

        return implode('; ', $parts);
    }

    private function describeSeriesKind(string $kind): string
    {
        return match ($kind) {
            'rot' => 'a fixed rotation each step',
            'size' => 'the figure grows each step',
            'count_cycle' => 'the count cycles 1-2-3',
            'rot_fill' => 'rotation advances while fill alternates',
            'two_shape_rot' => 'two shapes alternate while rotating',
            'accel_rot' => 'the rotation step itself increases each time',
            'count_steps' => 'the count increases by +1, +2 alternately',
            default => $kind,
        };
    }
}
