<?php

namespace App\Services\QuestionBank;

/**
 * Deterministic SVG renderer for image-based reasoning questions (matrix
 * reasoning, figure series, rotation, mirror images, paper folding, cube
 * nets, grid counting). Produces one self-contained "exam paper" style
 * composite image: the question figure on top and four labelled answer
 * tiles (A-D) below, so the existing mcq option UI (plain A-D buttons)
 * works unchanged - the visual answer candidates live inside the image.
 *
 * Panel spec formats:
 *   null                                             -> "?" placeholder
 *   ['shape' => name, 'rot' => deg, 'fill' => bool,
 *    'count' => 1..4, 'size' => float]               -> geometric shape(s)
 *   ['poly' => [[r,c],...], 'mirror' => bool,
 *    'rot' => 0|90|180|270]                          -> 4x4 polyomino
 *   ['text' => str, 'mode' => plain|mirrorH|mirrorV|rot180]
 *   ['grid' => n]                                    -> n x n counting grid
 *   ['sheet' => 'full'|'half-v'|'half-h'|'quarter',
 *    'holes' => [[fx,fy],...], 'foldLines' => ['v','h']]
 *   ['net' => [[gx,gy,shapeSpec],...]]               -> cube net (cross)
 *
 * All rendering is pure geometry - correctness of an answer tile is
 * guaranteed by construction in the seeder (e.g. the "mirror image" option
 * IS the mirror transform of the target), never by eyeballing.
 */
class SvgFigureBuilder
{
    private const STROKE = '#334155';
    private const FILL = '#60a5fa';
    private const PANEL_BORDER = '#cbd5e1';
    private const LABELS = ['A', 'B', 'C', 'D'];

    /** @var array<string, array<int, array{0: float, 1: float}>> unit polygons centred at origin */
    private const SHAPES = [
        'triangle' => [[0.0, -1.0], [0.87, 0.5], [-0.87, 0.5]],
        'square' => [[-0.8, -0.8], [0.8, -0.8], [0.8, 0.8], [-0.8, 0.8]],
        'diamond' => [[0.0, -1.0], [1.0, 0.0], [0.0, 1.0], [-1.0, 0.0]],
        'pentagon' => [[0.0, -1.0], [0.95, -0.31], [0.59, 0.81], [-0.59, 0.81], [-0.95, -0.31]],
        'hexagon' => [[0.5, -0.87], [1.0, 0.0], [0.5, 0.87], [-0.5, 0.87], [-1.0, 0.0], [-0.5, -0.87]],
        'star' => [[0.0, -1.0], [0.22, -0.31], [0.95, -0.31], [0.36, 0.12], [0.59, 0.81], [0.0, 0.38], [-0.59, 0.81], [-0.36, 0.12], [-0.95, -0.31], [-0.22, -0.31]],
        'cross' => [[-0.33, -1.0], [0.33, -1.0], [0.33, -0.33], [1.0, -0.33], [1.0, 0.33], [0.33, 0.33], [0.33, 1.0], [-0.33, 1.0], [-0.33, 0.33], [-1.0, 0.33], [-1.0, -0.33], [-0.33, -0.33]],
        'arrow' => [[0.0, -1.0], [0.6, -0.2], [0.25, -0.2], [0.25, 1.0], [-0.25, 1.0], [-0.25, -0.2], [-0.6, -0.2]],
        'flag' => [[0.0, -1.0], [0.7, -0.6], [0.0, -0.2], [0.0, 1.0], [-0.15, 1.0], [-0.15, -1.0]],
        'lshape' => [[-0.6, -1.0], [0.1, -1.0], [0.1, 0.3], [0.6, 0.3], [0.6, 1.0], [-0.6, 1.0]],
    ];

    /**
     * @param  array<int, array|null>  $questionPanels
     * @param  array<int, array|null>  $optionPanels  exactly 4
     * @param  int  $questionCols  layout columns for the question panels
     * @param  int  $questionPanelSize  px per question panel
     */
    public function compose(array $questionPanels, array $optionPanels, int $questionCols, int $questionPanelSize = 110): string
    {
        $optionSize = 100;
        $gap = 10;
        $labelHeight = 22;

        $questionRows = (int) ceil(count($questionPanels) / $questionCols);
        $questionWidth = $questionCols * $questionPanelSize + ($questionCols - 1) * $gap;
        $optionsWidth = count($optionPanels) > 0 ? 4 * $optionSize + 3 * $gap : 0;
        $width = max($questionWidth, $optionsWidth) + 2 * $gap;
        $questionHeight = $questionRows * $questionPanelSize + ($questionRows - 1) * $gap;
        $separatorY = $gap + $questionHeight + $gap;
        $height = count($optionPanels) > 0 ? $separatorY + $labelHeight + $optionSize + $gap : $separatorY;

        $qOffsetX = (int) (($width - $questionWidth) / 2);
        $oOffsetX = (int) (($width - $optionsWidth) / 2);

        $svg = [];
        $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" font-family="Arial, sans-serif">';
        $svg[] = '<rect width="'.$width.'" height="'.$height.'" fill="#ffffff" rx="8"/>';

        foreach ($questionPanels as $i => $panel) {
            $x = $qOffsetX + ($i % $questionCols) * ($questionPanelSize + $gap);
            $y = $gap + intdiv($i, $questionCols) * ($questionPanelSize + $gap);
            $svg[] = $this->renderPanel($panel, $x, $y, $questionPanelSize);
        }

        if (count($optionPanels) > 0) {
            $svg[] = '<line x1="'.$gap.'" y1="'.$separatorY.'" x2="'.($width - $gap).'" y2="'.$separatorY.'" stroke="'.self::PANEL_BORDER.'" stroke-width="1"/>';

            foreach ($optionPanels as $i => $panel) {
                $x = $oOffsetX + $i * ($optionSize + $gap);
                $labelY = $separatorY + $labelHeight - 6;
                $svg[] = '<text x="'.($x + $optionSize / 2).'" y="'.$labelY.'" text-anchor="middle" font-size="15" font-weight="bold" fill="'.self::STROKE.'">'.self::LABELS[$i].'</text>';
                $svg[] = $this->renderPanel($panel, $x, $separatorY + $labelHeight, $optionSize);
            }
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    private function renderPanel(?array $spec, float $x, float $y, float $size): string
    {
        $out = '<g>';
        $out .= '<rect x="'.$x.'" y="'.$y.'" width="'.$size.'" height="'.$size.'" fill="none" stroke="'.self::PANEL_BORDER.'" stroke-width="1.5" rx="6"/>';

        if ($spec === null) {
            $out .= '<text x="'.($x + $size / 2).'" y="'.($y + $size / 2 + 10).'" text-anchor="middle" font-size="'.($size * 0.4).'" fill="'.self::STROKE.'">?</text>';

            return $out.'</g>';
        }

        if (isset($spec['shape'])) {
            $out .= $this->renderShapes($spec, $x, $y, $size);
        } elseif (isset($spec['poly'])) {
            $out .= $this->renderPolyomino($spec, $x, $y, $size);
        } elseif (isset($spec['text'])) {
            $out .= $this->renderText($spec, $x, $y, $size);
        } elseif (isset($spec['grid'])) {
            $out .= $this->renderGrid($spec['grid'], $x, $y, $size);
        } elseif (isset($spec['sheet'])) {
            $out .= $this->renderSheet($spec, $x, $y, $size);
        } elseif (isset($spec['net'])) {
            $out .= $this->renderNet($spec['net'], $x, $y, $size);
        }

        return $out.'</g>';
    }

    private function renderShapes(array $spec, float $x, float $y, float $size): string
    {
        $count = $spec['count'] ?? 1;
        $scale = ($spec['size'] ?? 1.0) * ($count === 1 ? 0.33 : ($count <= 4 ? 0.19 : 0.13)) * $size;
        $rot = $spec['rot'] ?? 0;
        $filled = $spec['fill'] ?? true;

        $positions = match (true) {
            $count === 1 => [[0.5, 0.5]],
            $count === 2 => [[0.32, 0.32], [0.68, 0.68]],
            $count === 3 => [[0.5, 0.28], [0.3, 0.7], [0.7, 0.7]],
            $count === 4 => [[0.3, 0.3], [0.7, 0.3], [0.3, 0.7], [0.7, 0.7]],
            default => array_map(
                fn ($i) => [0.25 + ($i % 3) * 0.25, 0.25 + intdiv($i, 3) * 0.25],
                range(0, min($count, 9) - 1)
            ),
        };

        $out = '';
        foreach ($positions as [$fx, $fy]) {
            $cx = $x + $fx * $size;
            $cy = $y + $fy * $size;
            $out .= $this->shapeElement($spec['shape'], $cx, $cy, $scale, $rot, $filled);
        }

        return $out;
    }

    private function shapeElement(string $shape, float $cx, float $cy, float $scale, float $rot, bool $filled): string
    {
        $fill = $filled ? self::FILL : 'none';

        if ($shape === 'circle') {
            return '<circle cx="'.round($cx, 1).'" cy="'.round($cy, 1).'" r="'.round($scale, 1).'" fill="'.$fill.'" stroke="'.self::STROKE.'" stroke-width="2"/>';
        }

        if ($shape === 'semicircle') {
            $r = round($scale, 1);
            $path = "M ".round($cx - $r, 1).' '.round($cy, 1)." A {$r} {$r} 0 0 1 ".round($cx + $r, 1).' '.round($cy, 1).' Z';

            return '<path d="'.$path.'" fill="'.$fill.'" stroke="'.self::STROKE.'" stroke-width="2" transform="rotate('.$rot.' '.round($cx, 1).' '.round($cy, 1).')"/>';
        }

        $points = self::SHAPES[$shape] ?? self::SHAPES['square'];
        $rad = deg2rad($rot);
        $cos = cos($rad);
        $sin = sin($rad);
        $coords = [];
        foreach ($points as [$px, $py]) {
            $rx = $px * $cos - $py * $sin;
            $ry = $px * $sin + $py * $cos;
            $coords[] = round($cx + $rx * $scale, 1).','.round($cy + $ry * $scale, 1);
        }

        return '<polygon points="'.implode(' ', $coords).'" fill="'.$fill.'" stroke="'.self::STROKE.'" stroke-width="2"/>';
    }

    /**
     * Applies mirror (horizontal flip) first, then k*90-degree rotations, on
     * a 4x4 cell grid - the same transform order the Mental Rotation game
     * uses, so seeded answers are provably true rotations vs. mirrored
     * distractors. Cells are normalized to the top-left afterwards so tiles
     * are compared by shape, not by position in the grid.
     */
    public function transformPoly(array $cells, bool $mirror, int $rotDeg): array
    {
        $n = 4;
        $out = [];
        foreach ($cells as [$r, $c]) {
            if ($mirror) {
                $c = $n - 1 - $c;
            }
            for ($k = 0; $k < intdiv(($rotDeg % 360 + 360) % 360, 90); $k++) {
                [$r, $c] = [$c, $n - 1 - $r];
            }
            $out[] = [$r, $c];
        }

        $minR = min(array_column($out, 0));
        $minC = min(array_column($out, 1));

        $normalized = array_map(fn ($cell) => [$cell[0] - $minR, $cell[1] - $minC], $out);
        usort($normalized, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return $normalized;
    }

    /** Canonical string signature of a polyomino cell set (for distractor uniqueness checks). */
    public function polySignature(array $cells): string
    {
        return implode(';', array_map(fn ($c) => $c[0].','.$c[1], $cells));
    }

    private function renderPolyomino(array $spec, float $x, float $y, float $size): string
    {
        $cells = $this->transformPoly($spec['poly'], $spec['mirror'] ?? false, $spec['rot'] ?? 0);
        $pad = $size * 0.12;
        $cellSize = ($size - 2 * $pad) / 4;

        $out = '';
        for ($r = 0; $r < 4; $r++) {
            for ($c = 0; $c < 4; $c++) {
                $filled = in_array([$r, $c], $cells, true);
                $out .= '<rect x="'.round($x + $pad + $c * $cellSize, 1).'" y="'.round($y + $pad + $r * $cellSize, 1)
                    .'" width="'.round($cellSize, 1).'" height="'.round($cellSize, 1)
                    .'" fill="'.($filled ? self::FILL : 'none').'" stroke="'.self::PANEL_BORDER.'" stroke-width="1"/>';
            }
        }

        return $out;
    }

    private function renderText(array $spec, float $x, float $y, float $size): string
    {
        $cx = $x + $size / 2;
        $cy = $y + $size / 2;
        $transform = match ($spec['mode'] ?? 'plain') {
            'mirrorH' => 'translate('.round(2 * $cx, 1).' 0) scale(-1 1)',
            'mirrorV' => 'translate(0 '.round(2 * $cy, 1).') scale(1 -1)',
            'rot180' => 'rotate(180 '.round($cx, 1).' '.round($cy, 1).')',
            default => '',
        };
        $attr = $transform !== '' ? ' transform="'.$transform.'"' : '';
        $fontSize = $size * 0.34;

        return '<text x="'.round($cx, 1).'" y="'.round($cy + $fontSize * 0.35, 1).'" text-anchor="middle" font-size="'.round($fontSize, 1)
            .'" font-weight="bold" letter-spacing="2" fill="'.self::STROKE.'"'.$attr.'>'.htmlspecialchars($spec['text'], ENT_XML1).'</text>';
    }

    /** @param int|array{0:int,1:int} $n side count, or [rows, cols] for a rectangular grid */
    private function renderGrid($n, float $x, float $y, float $size): string
    {
        [$rows, $cols] = is_array($n) ? $n : [$n, $n];
        $pad = $size * 0.12;
        $inner = $size - 2 * $pad;
        $cellW = $inner / $cols;
        $cellH = $inner / $rows;
        $gridW = $cellW * $cols;
        $gridH = $cellH * $rows;

        $out = '';
        for ($i = 0; $i <= $rows; $i++) {
            $py = round($y + $pad + $i * $cellH, 1);
            $out .= '<line x1="'.round($x + $pad, 1).'" y1="'.$py.'" x2="'.round($x + $pad + $gridW, 1).'" y2="'.$py.'" stroke="'.self::STROKE.'" stroke-width="1.5"/>';
        }
        for ($i = 0; $i <= $cols; $i++) {
            $px = round($x + $pad + $i * $cellW, 1);
            $out .= '<line x1="'.$px.'" y1="'.round($y + $pad, 1).'" x2="'.$px.'" y2="'.round($y + $pad + $gridH, 1).'" stroke="'.self::STROKE.'" stroke-width="1.5"/>';
        }

        return $out;
    }

    /**
     * Paper-folding panel. Hole coordinates are fractions of the FULL
     * unfolded sheet; 'sheet' selects how much of the sheet is visible
     * (folded states show the reduced sheet with dashed edges on the fold).
     */
    private function renderSheet(array $spec, float $x, float $y, float $size): string
    {
        $pad = $size * 0.14;
        $inner = $size - 2 * $pad;
        $sheet = $spec['sheet'];

        $w = in_array($sheet, ['half-v', 'quarter'], true) ? $inner / 2 : $inner;
        $h = in_array($sheet, ['half-h', 'quarter'], true) ? $inner / 2 : $inner;

        $out = '<rect x="'.round($x + $pad, 1).'" y="'.round($y + $pad, 1).'" width="'.round($w, 1).'" height="'.round($h, 1)
            .'" fill="#f8fafc" stroke="'.self::STROKE.'" stroke-width="2"/>';

        // On a full sheet the dashed line marks WHERE the fold will happen
        // (the centre); on an already-folded sheet it would sit on the folded
        // edge, which the plain border already shows.
        foreach ($spec['foldLines'] ?? [] as $line) {
            if ($line === 'v') {
                $lx = round($x + $pad + ($sheet === 'full' ? $w / 2 : $w), 1);
                $out .= '<line x1="'.$lx.'" y1="'.round($y + $pad, 1).'" x2="'.$lx.'" y2="'.round($y + $pad + $h, 1).'" stroke="'.self::STROKE.'" stroke-width="2" stroke-dasharray="5,4"/>';
            } else {
                $ly = round($y + $pad + ($sheet === 'full' ? $h / 2 : $h), 1);
                $out .= '<line x1="'.round($x + $pad, 1).'" y1="'.$ly.'" x2="'.round($x + $pad + $w, 1).'" y2="'.$ly.'" stroke="'.self::STROKE.'" stroke-width="2" stroke-dasharray="5,4"/>';
            }
        }

        foreach ($spec['holes'] ?? [] as [$fx, $fy]) {
            $out .= '<circle cx="'.round($x + $pad + $fx * $inner, 1).'" cy="'.round($y + $pad + $fy * $inner, 1).'" r="'.round($size * 0.045, 1).'" fill="'.self::STROKE.'"/>';
        }

        return $out;
    }

    /** @param array<int, array> $cells [gridX, gridY, shapeSpec, highlight?] on a 4x4 layout grid */
    private function renderNet(array $cells, float $x, float $y, float $size): string
    {
        $pad = $size * 0.06;
        $cellSize = ($size - 2 * $pad) / 4;

        $out = '';
        foreach ($cells as $cell) {
            [$gx, $gy, $shapeSpec] = $cell;
            $highlight = $cell[3] ?? false;
            $cx = $x + $pad + $gx * $cellSize;
            $cy = $y + $pad + $gy * $cellSize;
            $out .= '<rect x="'.round($cx, 1).'" y="'.round($cy, 1).'" width="'.round($cellSize, 1).'" height="'.round($cellSize, 1)
                .'" fill="'.($highlight ? '#fef3c7' : '#f8fafc').'" stroke="'.self::STROKE.'" stroke-width="'.($highlight ? 3 : 1.5).'"/>';
            $out .= $this->shapeElement($shapeSpec['shape'], $cx + $cellSize / 2, $cy + $cellSize / 2, $cellSize * 0.3, $shapeSpec['rot'] ?? 0, $shapeSpec['fill'] ?? true);
        }

        return $out;
    }
}
