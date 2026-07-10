<?php

namespace Database\Seeders\Questions;

use Illuminate\Database\Seeder;

/**
 * Second wave of exam-authentic numerical questions modelled on Sri Lankan
 * aptitude papers (SLAS / A-L Common General style): profit-and-loss selling
 * prices, averages after removing a value, second-difference number series,
 * and missing-number matrices. Each archetype draws from a single shuffled
 * combo pool consumed by a cursor so no two questions share parameters.
 */
class ExamNumericalQuestionsSeeder extends Seeder
{
    use BuildsQuestions;

    public function run(): void
    {
        $rows = array_merge(
            $this->profitQuestions(),
            $this->averageQuestions(),
            $this->secondDifferenceSeries(),
            $this->matrixQuestions(),
        );

        $this->insertRows('numerical_ability', $rows, [
            'exam_tags' => ['numerical_reasoning', 'gov_aptitude', 'slas_style'],
            'cognitive_skill' => 'quantitative-reasoning',
            'bloom_level' => 'apply',
        ]);
    }

    /** @return array<int,array> */
    private function profitQuestions(): array
    {
        $combos = [];
        foreach ([240, 320, 360, 480, 520, 600, 640, 720, 750, 800, 840, 880, 900, 960, 1200, 1250, 1440, 1500, 1600, 1800, 2000, 2400] as $cost) {
            foreach ([5, 10, 12, 15, 20, 25, 30, 40] as $p) {
                if (($cost * $p) % 100 === 0) {
                    $combos[] = [$cost, $p];
                }
            }
        }
        mt_srand(31001);
        shuffle($combos);

        $perLevel = [2 => 30, 3 => 30, 4 => 30, 5 => 30];
        $rows = [];
        $cursor = 0;

        foreach ($perLevel as $level => $count) {
            for ($i = 0; $i < $count && $cursor < count($combos); $i++) {
                [$cost, $p] = $combos[$cursor++];
                $sp = $cost + (int) ($cost * $p / 100);

                $rows[] = $this->numericRow(
                    $level,
                    "A trader buys an item for Rs. {$cost} and sells it at a {$p}% profit. What is the selling price?",
                    "වෙළෙන්දෙක් භාණ්ඩයක් රු. {$cost}කට මිලදී ගෙන {$p}% ලාභයකට විකුණයි. විකුණුම් මිල කීයද?",
                    $sp,
                    [$cost + (int) ($cost * $p / 100 / 2), $sp + 20, $sp - 40, $cost - (int) ($cost * $p / 100)],
                    "Profit = {$p}% of {$cost} = Rs. ".(int) ($cost * $p / 100).", so selling price = {$cost} + ".(int) ($cost * $p / 100)." = Rs. {$sp}.",
                    "ලාභය = {$cost}හි {$p}% = රු. ".(int) ($cost * $p / 100).", එබැවින් විකුණුම් මිල = {$cost} + ".(int) ($cost * $p / 100)." = රු. {$sp}.",
                    "profit-{$cost}-{$p}"
                );
            }
        }

        return $rows;
    }

    /** @return array<int,array> */
    private function averageQuestions(): array
    {
        $combos = [];
        foreach (range(4, 9) as $n) {
            foreach (range(8, 24) as $avg) {
                foreach ([$avg - 6, $avg - 3, $avg + 3, $avg + 6, $avg + 9] as $x) {
                    if ($x > 0 && $x < $n * $avg && (($n * $avg - $x) % ($n - 1)) === 0) {
                        $newAvg = intdiv($n * $avg - $x, $n - 1);
                        if ($newAvg !== $avg && $newAvg > 1) {
                            $combos[] = [$n, $avg, $x, $newAvg];
                        }
                    }
                }
            }
        }
        mt_srand(31002);
        shuffle($combos);

        $perLevel = [3 => 30, 4 => 25, 5 => 25];
        $rows = [];
        $cursor = 0;

        foreach ($perLevel as $level => $count) {
            for ($i = 0; $i < $count && $cursor < count($combos); $i++) {
                [$n, $avg, $x, $newAvg] = $combos[$cursor++];

                $rows[] = $this->numericRow(
                    $level,
                    "The average of {$n} numbers is {$avg}. When the number {$x} is removed, what is the average of the remaining numbers?",
                    "සංඛ්‍යා {$n}ක සාමාන්‍යය {$avg}කි. {$x} සංඛ්‍යාව ඉවත් කළ විට ඉතිරි සංඛ්‍යාවල සාමාන්‍යය කීයද?",
                    $newAvg,
                    [$avg, $newAvg + 1, $newAvg - 1, $avg + 2],
                    'Total = '.($n * $avg).'; after removing '.$x.' the total is '.($n * $avg - $x)." across ".($n - 1)." numbers, so the average is {$newAvg}.",
                    'එකතුව = '.($n * $avg)."; {$x} ඉවත් කළ පසු එකතුව ".($n * $avg - $x).' වන අතර සංඛ්‍යා '.($n - 1)."කි, එබැවින් සාමාන්‍යය {$newAvg} වේ.",
                    "avg-{$n}-{$avg}-{$x}"
                );
            }
        }

        return $rows;
    }

    /** @return array<int,array> */
    private function secondDifferenceSeries(): array
    {
        $combos = [];
        foreach (range(1, 15) as $s) {
            foreach (range(2, 6) as $d) {
                foreach (range(1, 4) as $k) {
                    $combos[] = [$s, $d, $k];
                }
            }
        }
        mt_srand(31003);
        shuffle($combos);

        $perLevel = [3 => 20, 4 => 20, 5 => 20];
        $rows = [];
        $cursor = 0;

        foreach ($perLevel as $level => $count) {
            for ($i = 0; $i < $count && $cursor < count($combos); $i++) {
                [$s, $d, $k] = $combos[$cursor++];
                $terms = [$s, $s + $d, $s + 2 * $d + $k, $s + 3 * $d + 3 * $k];
                $answer = $s + 4 * $d + 6 * $k;
                $seriesText = implode(', ', $terms);
                $diffs = "{$d}, ".($d + $k).', '.($d + 2 * $k);

                $rows[] = $this->numericRow(
                    $level,
                    "What is the next number in the series: {$seriesText}, ... ?",
                    "ශ්‍රේණියේ ඊළඟ සංඛ්‍යාව කුමක්ද: {$seriesText}, ... ?",
                    $answer,
                    [$answer - $k, $answer + $k, $answer - $d, end($terms) + $d],
                    "The gaps grow by {$k} each step ({$diffs}), so the next gap is ".($d + 3 * $k)." giving {$answer}.",
                    "පරතරයන් සෑම පියවරකදීම {$k}කින් වැඩිවේ ({$diffs}), එබැවින් ඊළඟ පරතරය ".($d + 3 * $k)." වන අතර පිළිතුර {$answer} වේ.",
                    "sds-{$s}-{$d}-{$k}"
                );
            }
        }

        return $rows;
    }

    /** @return array<int,array> */
    private function matrixQuestions(): array
    {
        $multPairs = [];
        foreach (range(2, 9) as $a) {
            foreach (range(2, 9) as $b) {
                if ($a < $b) {
                    $multPairs[] = [$a, $b];
                }
            }
        }
        $addPairs = [];
        foreach (range(11, 30) as $a) {
            foreach (range(2, 9) as $b) {
                $addPairs[] = [$a, $b];
            }
        }
        mt_srand(31004);
        shuffle($multPairs);
        shuffle($addPairs);

        $rows = [];
        $build = function (array $pairs, string $op, array $perLevel, int &$cursor) use (&$rows) {
            foreach ($perLevel as $level => $count) {
                for ($i = 0; $i < $count && $cursor + 2 < count($pairs); $i++) {
                    $r1 = $pairs[$cursor++];
                    $r2 = $pairs[$cursor++];
                    $r3 = $pairs[$cursor++];
                    $f = $op === 'x' ? fn ($p) => $p[0] * $p[1] : fn ($p) => $p[0] + $p[1];
                    $grid = "({$r1[0]}, {$r1[1]}, {$f($r1)})   ({$r2[0]}, {$r2[1]}, {$f($r2)})   ({$r3[0]}, {$r3[1]}, ?)";
                    $answer = $f($r3);
                    $ruleEn = $op === 'x' ? 'first number × second number' : 'first number + second number';
                    $ruleSi = $op === 'x' ? 'පළමු සංඛ්‍යාව × දෙවන සංඛ්‍යාව' : 'පළමු සංඛ්‍යාව + දෙවන සංඛ්‍යාව';

                    $rows[] = $this->numericRow(
                        $level,
                        "In each group the third number follows the same rule. Find the missing number: {$grid}",
                        "සෑම කණ්ඩායමකම තුන්වන සංඛ්‍යාව එකම රීතියක් අනුගමනය කරයි. නැතිවූ සංඛ්‍යාව සොයන්න: {$grid}",
                        $answer,
                        [$answer + 2, $answer - 2, $r3[0] + $r3[1] + 1, $answer + $r3[1]],
                        "The rule is {$ruleEn}, so the missing number is {$r3[0]} ".($op === 'x' ? '×' : '+')." {$r3[1]} = {$answer}.",
                        "රීතිය {$ruleSi} වේ, එබැවින් නැතිවූ සංඛ්‍යාව {$r3[0]} ".($op === 'x' ? '×' : '+')." {$r3[1]} = {$answer}.",
                        "matrix-{$op}-{$r1[0]}.{$r1[1]}-{$r2[0]}.{$r2[1]}-{$r3[0]}.{$r3[1]}"
                    );
                }
            }
        };

        $cursorMult = 0;
        $build($multPairs, 'x', [4 => 9], $cursorMult);
        $cursorAdd = 0;
        $build($addPairs, '+', [4 => 11, 5 => 20], $cursorAdd);

        return $rows;
    }

    /** Builds one mcq_text row with unique numeric options shuffled deterministically. */
    private function numericRow(int $level, string $textEn, string $textSi, int $answer, array $distractorCandidates, string $explEn, string $explSi, string $seedKey): array
    {
        $distractors = [];
        foreach ($distractorCandidates as $candidate) {
            if ($candidate > 0 && $candidate !== $answer && ! in_array($candidate, $distractors, true)) {
                $distractors[] = $candidate;
            }
            if (count($distractors) === 3) {
                break;
            }
        }
        $bump = 1;
        while (count($distractors) < 3) {
            $candidate = $answer + $bump * 3;
            if ($candidate !== $answer && ! in_array($candidate, $distractors, true)) {
                $distractors[] = $candidate;
            }
            $bump++;
        }

        $values = array_merge([$answer], $distractors);
        mt_srand(crc32($seedKey));
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];
        $labels = array_map('strval', $values);

        return [$level, 'mcq_text', $textEn, $textSi, $this->options($labels, $labels), $key, $explEn, $explSi];
    }
}
