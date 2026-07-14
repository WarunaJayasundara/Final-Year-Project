<?php

namespace Database\Seeders\Questions\Bank2;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Competitive-exam numerical reasoning bank. Every archetype reuses a
 * bilingual sentence frame already verified in the existing seeders -
 * only numbers change - and every answer is computed, never authored.
 * Parameter ranges are kept disjoint from ExamNumericalQuestionsSeeder /
 * AdvancedNumericalQuestionsSeeder (which run first), backed by a
 * run-time check against active question text for any duplicates.
 */
class NumericalBank2Seeder extends Seeder
{
    use BuildsQuestions;

    /** @var array<string,bool> question_text_en already used (this run + active bank) */
    private array $seenTexts = [];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->profitQuestions(),
            $this->averageQuestions(),
            $this->secondDifferenceSeries(),
            $this->mixedSeries(),
            $this->matrixQuestions(),
            $this->workAndTime(),
            $this->simpleInterest(),
            $this->ageProblems(),
            $this->relativeSpeed(),
            $this->percentages(),
        ));

        $this->insertRows('numerical_ability', $rows, [
            'exam_tags' => ['numerical_reasoning', 'gov_aptitude', 'banking_recruitment'],
            'cognitive_skill' => 'quantitative-reasoning',
        ]);
    }

    // ---------------------------------------------------------------
    // Archetypes
    // ---------------------------------------------------------------

    /** @return array<int,array> */
    private function profitQuestions(): array
    {
        // Costs deliberately avoid ExamNumericalQuestionsSeeder's fixed list.
        $examCosts = [240, 320, 360, 480, 520, 600, 640, 720, 750, 800, 840, 880, 900, 960, 1200, 1250, 1440, 1500, 1600, 1800, 2000, 2400];
        $combos = [];
        for ($cost = 130; $cost <= 4950; $cost += 10) {
            foreach ([5, 8, 10, 12, 15, 20, 25, 30, 35, 40, 45, 60] as $p) {
                if (($cost * $p) % 100 === 0 && ! in_array($cost, $examCosts, true)) {
                    $combos[] = [$cost, $p];
                }
            }
        }
        mt_srand(810001);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([1 => 20, 2 => 30, 3 => 40, 4 => 40, 5 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$cost, $p] = $combos[$cursor++];
                // Level scales the numbers: small clean costs early, large later.
                while (($level <= 2 && $cost > 1500) || ($level >= 4 && $cost < 800)) {
                    [$cost, $p] = $combos[$cursor++];
                }
                $profit = (int) ($cost * $p / 100);
                $sp = $cost + $profit;

                $rows[] = $this->numericRow(
                    $level,
                    "A trader buys an item for Rs. {$cost} and sells it at a {$p}% profit. What is the selling price?",
                    "වෙළෙන්දෙක් භාණ්ඩයක් රු. {$cost}කට මිලදී ගෙන {$p}% ලාභයකට විකුණයි. විකුණුම් මිල කීයද?",
                    $sp,
                    [$cost + intdiv($profit, 2), $sp + 50, $sp - 50, $cost - $profit],
                    "Profit = {$p}% of {$cost} = Rs. {$profit}, so selling price = {$cost} + {$profit} = Rs. {$sp}.",
                    "ලාභය = {$cost}හි {$p}% = රු. {$profit}, එබැවින් විකුණුම් මිල = {$cost} + {$profit} = රු. {$sp}.",
                    "b2profit-{$cost}-{$p}",
                    ['subcategory' => 'profit_loss', 'solving_time_seconds' => 30 + $level * 10, 'bloom_level' => 'apply'],
                );
            }
        }

        return $rows;
    }

    /** @return array<int,array> */
    private function averageQuestions(): array
    {
        // avg range 25..60 is disjoint from the exam seeder's 8..24.
        $combos = [];
        foreach (range(5, 12) as $n) {
            foreach (range(25, 60) as $avg) {
                foreach ([$avg - 8, $avg - 4, $avg + 4, $avg + 8, $avg + 12] as $x) {
                    if ($x > 0 && $x < $n * $avg && (($n * $avg - $x) % ($n - 1)) === 0) {
                        $newAvg = intdiv($n * $avg - $x, $n - 1);
                        if ($newAvg !== $avg && $newAvg > 1) {
                            $combos[] = [$n, $avg, $x, $newAvg];
                        }
                    }
                }
            }
        }
        mt_srand(810002);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([2 => 30, 3 => 30, 4 => 30, 5 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$n, $avg, $x, $newAvg] = $combos[$cursor++];

                $rows[] = $this->numericRow(
                    $level,
                    "The average of {$n} numbers is {$avg}. When the number {$x} is removed, what is the average of the remaining numbers?",
                    "සංඛ්‍යා {$n}ක සාමාන්‍යය {$avg}කි. {$x} සංඛ්‍යාව ඉවත් කළ විට ඉතිරි සංඛ්‍යාවල සාමාන්‍යය කීයද?",
                    $newAvg,
                    [$avg, $newAvg + 1, $newAvg - 1, $avg + 2],
                    'Total = '.($n * $avg).'; after removing '.$x.' the total is '.($n * $avg - $x).' across '.($n - 1)." numbers, so the average is {$newAvg}.",
                    'එකතුව = '.($n * $avg)."; {$x} ඉවත් කළ පසු එකතුව ".($n * $avg - $x).' වන අතර සංඛ්‍යා '.($n - 1)."කි, එබැවින් සාමාන්‍යය {$newAvg} වේ.",
                    "b2avg-{$n}-{$avg}-{$x}",
                    ['subcategory' => 'averages', 'solving_time_seconds' => 40 + $level * 10, 'bloom_level' => 'apply'],
                );
            }
        }

        return $rows;
    }

    /** Second-difference series with k 5..7 (the exam seeder owns k 1..4). @return array<int,array> */
    private function secondDifferenceSeries(): array
    {
        $combos = [];
        foreach (range(1, 20) as $s) {
            foreach (range(2, 8) as $d) {
                foreach (range(5, 7) as $k) {
                    $combos[] = [$s, $d, $k];
                }
            }
        }
        mt_srand(810003);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([2 => 30, 3 => 30, 4 => 30, 5 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
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
                    [$answer - $k, $answer + $k, $answer - $d, $terms[3] + $d],
                    "The gaps grow by {$k} each step ({$diffs}), so the next gap is ".($d + 3 * $k)." giving {$answer}.",
                    "පරතරයන් සෑම පියවරකදීම {$k}කින් වැඩිවේ ({$diffs}), එබැවින් ඊළඟ පරතරය ".($d + 3 * $k)." වන අතර පිළිතුර {$answer} වේ.",
                    "b2sds-{$s}-{$d}-{$k}",
                    ['subcategory' => 'number_series', 'solving_time_seconds' => 40 + $level * 10, 'bloom_level' => 'analyze'],
                );
            }
        }

        return $rows;
    }

    /** Squares/cubes/primes/Fibonacci-like/alternating-step/affine series. @return array<int,array> */
    private function mixedSeries(): array
    {
        $primes = [2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43, 47, 53, 59, 61, 67, 71, 73, 79, 83, 89, 97, 101, 103, 107, 109, 113];
        $pools = [
            'squares' => [],
            'cubes' => [],
            'primes' => [],
            'fib' => [],
            'alt' => [],
            'affine' => [],
        ];
        foreach (range(2, 40) as $k) {
            $pools['squares'][] = [$k];
        }
        foreach (range(1, 15) as $k) {
            $pools['cubes'][] = [$k];
        }
        foreach (range(0, count($primes) - 5) as $idx) {
            $pools['primes'][] = [$idx];
        }
        foreach (range(1, 12) as $a) {
            foreach (range(1, 12) as $b) {
                if ($a !== $b) {
                    $pools['fib'][] = [$a, $b];
                }
            }
        }
        foreach (range(2, 9) as $a) {
            foreach (range(2, 9) as $b) {
                if ($a !== $b) {
                    foreach ([1, 3, 5, 7] as $start) {
                        $pools['alt'][] = [$start, $a, $b];
                    }
                }
            }
        }
        foreach (range(1, 12) as $start) {
            foreach (range(1, 6) as $c) {
                $pools['affine'][] = [$start, $c];
            }
        }
        mt_srand(810004);
        foreach ($pools as &$pool) {
            shuffle($pool);
        }
        unset($pool);

        $kindsByLevel = [
            1 => ['squares', 'primes'],
            2 => ['squares', 'alt'],
            3 => ['alt', 'fib'],
            4 => ['fib', 'affine'],
            5 => ['affine', 'cubes'],
        ];
        $cursors = array_fill_keys(array_keys($pools), 0);

        $rows = [];
        foreach ([1 => 30, 2 => 30, 3 => 30, 4 => 15, 5 => 15] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                $kind = $kindsByLevel[$level][$i % 2];
                $params = $pools[$kind][$cursors[$kind]++];

                // English explanations state the specific rule; Sinhala
                // reuses the generic verified frame to avoid novel phrasing,
                // per the corpus-validation policy.
                [$terms, $answer, $ruleEn] = match ($kind) {
                    'squares' => (function () use ($params) {
                        $k = $params[0];
                        return [[$k ** 2, ($k + 1) ** 2, ($k + 2) ** 2, ($k + 3) ** 2], ($k + 4) ** 2,
                            'each term is a consecutive perfect square'];
                    })(),
                    'cubes' => (function () use ($params) {
                        $k = $params[0];
                        return [[$k ** 3, ($k + 1) ** 3, ($k + 2) ** 3, ($k + 3) ** 3], ($k + 4) ** 3,
                            'each term is a consecutive cube'];
                    })(),
                    'primes' => (function () use ($params, $primes) {
                        $idx = $params[0];
                        return [array_slice($primes, $idx, 4), $primes[$idx + 4],
                            'the terms are consecutive prime numbers'];
                    })(),
                    'fib' => (function () use ($params) {
                        [$a, $b] = $params;
                        $t = [$a, $b, $a + $b, $a + 2 * $b];
                        return [$t, $t[2] + $t[3],
                            'each term is the sum of the two before it'];
                    })(),
                    'alt' => (function () use ($params) {
                        [$start, $a, $b] = $params;
                        $t = [$start, $start + $a, $start + $a + $b, $start + 2 * $a + $b];
                        return [$t, $start + 2 * $a + 2 * $b,
                            "the series adds +{$a} and +{$b} alternately"];
                    })(),
                    default => (function () use ($params) {
                        [$start, $c] = $params;
                        $t = [$start];
                        for ($j = 0; $j < 3; $j++) {
                            $t[] = 2 * end($t) + $c;
                        }
                        return [$t, 2 * end($t) + $c,
                            "each term is double the previous plus {$c}"];
                    })(),
                };

                $seriesText = implode(', ', $terms);
                $spread = max(3, intdiv($answer, 8) + 1);

                $rows[] = $this->numericRow(
                    $level,
                    "What is the next number in the series: {$seriesText}, ... ?",
                    "ශ්‍රේණියේ ඊළඟ සංඛ්‍යාව කුමක්ද: {$seriesText}, ... ?",
                    $answer,
                    [$answer + $spread, $answer - $spread, $answer + 2 * $spread, end($terms) + 2],
                    "Here {$ruleEn}, so the next number is {$answer}.",
                    "රටාවට අනුව ඊළඟ අංකය {$answer} වේ.",
                    "b2mix-{$kind}-".implode('-', $params),
                    ['subcategory' => 'number_series', 'solving_time_seconds' => 30 + $level * 10,
                        'bloom_level' => $level >= 4 ? 'analyze' : 'apply'],
                );
            }
        }

        return $rows;
    }

    /** Missing-number groups with subtraction / large-addition rules (exam seeder owns small-add and a<b mult). @return array<int,array> */
    private function matrixQuestions(): array
    {
        $subPairs = [];
        foreach (range(15, 60) as $a) {
            foreach (range(2, 12) as $b) {
                if ($a - $b > 0) {
                    $subPairs[] = [$a, $b];
                }
            }
        }
        $addPairs = [];
        foreach (range(31, 60) as $a) {
            foreach (range(2, 9) as $b) {
                $addPairs[] = [$a, $b];
            }
        }
        mt_srand(810005);
        shuffle($subPairs);
        shuffle($addPairs);

        $rows = [];
        $build = function (array $pairs, string $op, array $perLevel) use (&$rows) {
            $cursor = 0;
            foreach ($perLevel as $level => $count) {
                for ($i = 0; $i < $count && $cursor + 2 < count($pairs); $i++) {
                    $r1 = $pairs[$cursor++];
                    $r2 = $pairs[$cursor++];
                    $r3 = $pairs[$cursor++];
                    $f = $op === '-' ? fn ($p) => $p[0] - $p[1] : fn ($p) => $p[0] + $p[1];
                    $grid = "({$r1[0]}, {$r1[1]}, {$f($r1)})   ({$r2[0]}, {$r2[1]}, {$f($r2)})   ({$r3[0]}, {$r3[1]}, ?)";
                    $answer = $f($r3);
                    $ruleEn = $op === '-' ? 'first number - second number' : 'first number + second number';
                    $ruleSi = $op === '-' ? 'පළමු සංඛ්‍යාව - දෙවන සංඛ්‍යාව' : 'පළමු සංඛ්‍යාව + දෙවන සංඛ්‍යාව';

                    $rows[] = $this->numericRow(
                        $level,
                        "In each group the third number follows the same rule. Find the missing number: {$grid}",
                        "සෑම කණ්ඩායමකම තුන්වන සංඛ්‍යාව එකම රීතියක් අනුගමනය කරයි. නැතිවූ සංඛ්‍යාව සොයන්න: {$grid}",
                        $answer,
                        [$answer + 2, $answer - 2, $r3[0] + $r3[1] + 1, abs($r3[0] - $r3[1]) + 1],
                        "The rule is {$ruleEn}, so the missing number is {$r3[0]} {$op} {$r3[1]} = {$answer}.",
                        "රීතිය {$ruleSi} වේ, එබැවින් නැතිවූ සංඛ්‍යාව {$r3[0]} {$op} {$r3[1]} = {$answer}.",
                        "b2matrix-{$op}-{$r1[0]}.{$r1[1]}-{$r2[0]}.{$r2[1]}-{$r3[0]}.{$r3[1]}",
                        ['subcategory' => 'number_matrix', 'solving_time_seconds' => 45 + $level * 10, 'bloom_level' => 'analyze'],
                    );
                }
            }
        };

        $build($subPairs, '-', [3 => 20, 4 => 10]);
        $build($addPairs, '+', [4 => 20, 5 => 30]);

        return $rows;
    }

    /** @return array<int,array> */
    private function workAndTime(): array
    {
        // Generates (a, b) pairs with an integer combined time t, using
        // 1/a + 1/b = 1/t <=> a = t + d, b = t + t²/d for any divisor d of
        // t², which always gives a clean whole-number answer.
        $combos = [];
        for ($t = 4; $t <= 48; $t++) {
            for ($d = 1; $d < $t; $d++) {
                if (($t * $t) % $d === 0) {
                    $a = $t + $d;
                    $b = $t + intdiv($t * $t, $d);
                    if ($b <= 200 && $a !== $b) {
                        $combos[] = [$a, $b, $t];
                    }
                }
            }
        }
        mt_srand(810006);
        shuffle($combos);
        if (count($combos) < 100) {
            throw new \RuntimeException('Work-and-time combo pool too small: '.count($combos));
        }

        $rows = [];
        $cursor = 0;
        foreach ([2 => 25, 3 => 25, 4 => 25, 5 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$a, $b, $combined] = $combos[$cursor++];
                $isCarpenter = $i % 2 === 0;

                if ($isCarpenter) {
                    $en = "Carpenter A can finish a piece of furniture alone in {$a} days and carpenter B can finish the same job alone in {$b} days. Working together, how many days will they take to finish it?";
                    $si = "වඩුකාර A හට තනිව දින {$a}කින් වැඩක් නිම කළ හැකි අතර වඩුකාර B හට එම වැඩම දින {$b}කින් නිම කළ හැක. දෙදෙනා එකට වැඩ කළහොත් එය නිම කිරීමට ගතවන දින ගණන කීයද?";
                } else {
                    $en = "Pipe A can fill a tank alone in {$a} hours and pipe B can fill the same tank alone in {$b} hours. If both pipes are opened together, how many hours will it take to fill the tank?";
                    $si = "නළය A හට තනිව පැය {$a}කින් ටැංකියක් පිරවිය හැකි අතර නළය B හට එම ටැංකිය තනිව පැය {$b}කින් පිරවිය හැක. දෙකම එකවර විවෘත කළහොත් ටැංකිය පිරවීමට ගතවන පැය ගණන කීයද?";
                }

                $rows[] = $this->numericRow(
                    $level, $en, $si, $combined,
                    [$combined + 1, max(1, $combined - 1), intdiv($a + $b, 2), $combined + 2],
                    "Combined rate = 1/{$a} + 1/{$b} per unit time, so together they take {$combined} units of time.",
                    "ඒකාබද්ධ වේගය = 1/{$a} + 1/{$b} වේ, එබැවින් එකට වැඩ කිරීමේදී ගතවන කාලය {$combined} වේ.",
                    "b2work-{$a}-{$b}",
                    ['subcategory' => 'work_time', 'solving_time_seconds' => 45 + $level * 12, 'bloom_level' => 'analyze'],
                );
            }
        }

        return $rows;
    }

    /** @return array<int,array> */
    private function simpleInterest(): array
    {
        $combos = [];
        foreach (range(11, 95) as $pHundreds) {
            foreach ([2, 3, 4, 5, 6, 8, 10, 12] as $rate) {
                foreach (range(2, 8) as $time) {
                    $principal = $pHundreds * 100;
                    $combos[] = [$principal, $rate, $time, intdiv($principal * $rate * $time, 100)];
                }
            }
        }
        mt_srand(810007);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([2 => 25, 3 => 25, 4 => 25, 5 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$principal, $rate, $time, $interest] = $combos[$cursor++];
                $ask = $i % 3;

                if ($ask === 0) {
                    $en = "If Rs. {$principal} is deposited in a savings account at {$rate}% per annum simple interest, how many years will it take to yield Rs. {$interest} as interest?";
                    $si = "රු. {$principal}ක් වාර්ෂික සරල පොලී අනුපාතය {$rate}% ක ඉතිරි කිරීමේ ගිණුමක තැන්පත් කළහොත්, පොලිය රු. {$interest} ලබා ගැනීමට කොපමණ අවුරුදු ගණනක් ගතවේද?";
                    $answer = $time;
                    $explEn = "Time = Interest x 100 / (Principal x Rate) = {$interest} x 100 / ({$principal} x {$rate}) = {$time} years.";
                    $explSi = "කාලය = පොලිය x 100 / (මුලික මුදල x අනුපාතය) = {$time} වර්ෂ වේ.";
                } elseif ($ask === 1) {
                    $en = "Rs. {$principal} deposited for {$time} years yields Rs. {$interest} as simple interest. What is the annual interest rate?";
                    $si = "රු. {$principal}ක් වර්ෂ {$time}ක් සඳහා තැන්පත් කිරීමෙන් රු. {$interest}ක සරල පොලියක් ලැබේ නම්, වාර්ෂික පොලී අනුපාතය කුමක්ද?";
                    $answer = $rate;
                    $explEn = "Rate = Interest x 100 / (Principal x Time) = {$rate}%.";
                    $explSi = "අනුපාතය = පොලිය x 100 / (මුලික මුදල x කාලය) = {$rate}% වේ.";
                } else {
                    $en = "A sum of money deposited at {$rate}% per annum simple interest for {$time} years yields Rs. {$interest} as interest. What was the sum deposited?";
                    $si = "වාර්ෂික සරල පොලී අනුපාතය {$rate}% කින් වර්ෂ {$time}ක් සඳහා තැන්පත් කළ මුදලකින් රු. {$interest}ක පොලියක් ලැබේ නම්, තැන්පත් කළ මුදල කීයද?";
                    $answer = $principal;
                    $explEn = "Principal = Interest x 100 / (Rate x Time) = Rs. {$principal}.";
                    $explSi = "මුලික මුදල = පොලිය x 100 / (අනුපාතය x කාලය) = රු. {$principal} වේ.";
                }

                $spread = $ask === 2 ? max(100, intdiv($principal, 5)) : max(2, intdiv($answer, 4) + 1);

                $rows[] = $this->numericRow(
                    $level, $en, $si, $answer,
                    [$answer + $spread, max(1, $answer - $spread), $answer + 2 * $spread, max(1, $answer - 2 * $spread)],
                    $explEn, $explSi,
                    "b2int-{$principal}-{$rate}-{$time}-{$ask}",
                    ['subcategory' => 'simple_interest', 'solving_time_seconds' => 45 + $level * 12, 'bloom_level' => 'apply'],
                );
            }
        }

        return $rows;
    }

    /** @return array<int,array> */
    private function ageProblems(): array
    {
        $names = [['Kanchana', 'her', 'කාංචනා', 'ඇගේ'], ['Nimal', 'his', 'නිමල්', 'ඔහුගේ'], ['Priya', 'her', 'ප්‍රියා', 'ඇගේ']];
        $combos = [];
        foreach (range(26, 48) as $fatherAge) {
            foreach (range(22, 42) as $motherAge) {
                foreach (range(2, 7) as $gap) {
                    $combos[] = [$fatherAge, $motherAge, $gap];
                }
            }
        }
        mt_srand(810008);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([3 => 20, 4 => 20, 5 => 20] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$fatherAgeAtBirth, $motherAgeAtSiblingBirth, $siblingYoungerBy] = $combos[$cursor++];
                $diff = abs($fatherAgeAtBirth - $motherAgeAtSiblingBirth + $siblingYoungerBy);
                if ($diff === 0) {
                    $i--;
                    continue;
                }
                [$name, $poss, $nameSi, $possSi] = $names[$i % 3];

                $en = "{$name}'s father was {$fatherAgeAtBirth} years old when {$name} was born. {$name}'s mother was {$motherAgeAtSiblingBirth} years old when {$poss} sibling, {$siblingYoungerBy} years younger, was born. What is the age difference between {$poss} parents?";
                $si = "{$nameSi}ගේ පියා උපන් විට {$nameSi}ගේ පියාට වයස අවුරුදු {$fatherAgeAtBirth} විය. {$nameSi}ට වඩා අවුරුදු {$siblingYoungerBy}කින් බාල සොයුරා/සොයුරිය උපන් විට {$nameSi}ගේ මව්කගේ වයස අවුරුදු {$motherAgeAtSiblingBirth} විය. {$possSi} දෙමාපියන්ගේ වයස් වෙනස කීයද?";

                $rows[] = $this->numericRow(
                    $level, $en, $si, $diff,
                    [$diff + 1, max(1, $diff - 1), $diff + 2, abs($fatherAgeAtBirth - $motherAgeAtSiblingBirth)],
                    "Father's age gap to {$name} is {$fatherAgeAtBirth} years; mother's age gap is {$motherAgeAtSiblingBirth} - {$siblingYoungerBy} = ".($motherAgeAtSiblingBirth - $siblingYoungerBy)." years. Difference = {$diff} years.",
                    "පියාගේ වයස් පරතරය අවුරුදු {$fatherAgeAtBirth}ක් වන අතර මවගේ වයස් පරතරය අවුරුදු ".($motherAgeAtSiblingBirth - $siblingYoungerBy)."ක් වේ. වෙනස අවුරුදු {$diff}ක් වේ.",
                    "b2age-{$fatherAgeAtBirth}-{$motherAgeAtSiblingBirth}-{$siblingYoungerBy}",
                    ['subcategory' => 'age_problems', 'solving_time_seconds' => 50 + $level * 12, 'bloom_level' => 'analyze'],
                );
            }
        }

        return $rows;
    }

    /** @return array<int,array> */
    private function relativeSpeed(): array
    {
        $combos = [];
        foreach ([30, 40, 50, 60, 70, 80, 90] as $speedA) {
            foreach ([30, 40, 50, 60, 70, 80, 90] as $speedB) {
                if ($speedA === $speedB) {
                    continue; // catch-up variant needs distinct speeds
                }
                foreach (range(2, 6) as $time) {
                    $combos[] = [$speedA, $speedB, $time];
                }
            }
        }
        mt_srand(810009);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([2 => 20, 3 => 20, 4 => 20, 5 => 20] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$speedA, $speedB, $time] = $combos[$cursor++];
                $isMeeting = $i % 2 === 0;

                if ($isMeeting) {
                    $distance = ($speedA + $speedB) * $time;
                    $en = "Two trains start at the same time from stations A and B, {$distance} km apart, travelling towards each other at {$speedA} km/h and {$speedB} km/h respectively. After how many hours will they meet?";
                    $si = "දුම්රිය දෙකක් A සහ B දුම්රියපොළවලින් කි.මී. {$distance}ක් දුරින් සිට, එකවර පිටත් වී පැයට කි.මී. {$speedA} සහ {$speedB} වේගයෙන් එකිනෙකා දෙසට ධාවනය වේ. ඒවා හමුවීමට කොපමණ පැය ගණනක් ගතවේද?";
                    $explEn = "Combined speed = {$speedA} + {$speedB} = ".($speedA + $speedB)." km/h. Time = Distance / Combined speed = {$time} hours.";
                    $explSi = "ඒකාබද්ධ වේගය = ".($speedA + $speedB)." කි.මී./පැය. කාලය = දුර / ඒකාබද්ධ වේගය = පැය {$time}ක්.";
                } else {
                    if ($speedA === $speedB) {
                        continue;
                    }
                    $fast = max($speedA, $speedB);
                    $slow = min($speedA, $speedB);
                    $lead = ($fast - $slow) * $time;
                    $en = "Vehicle X travels at {$fast} km/h and vehicle Y travels at {$slow} km/h in the same direction. If Y has a {$lead} km head start, how many hours will it take X to catch up with Y?";
                    $si = "වාහනය X පැයට කි.මී. {$fast} වේගයෙන් සහ වාහනය Y පැයට කි.මී. {$slow} වේගයෙන් එකම දිශාවට ධාවනය වේ. Y හට කි.මී. {$lead}ක ඉදිරි ආරම්භයක් ඇත්නම්, X හට Y ලඟා වීමට ගතවන පැය ගණන කීයද?";
                    $explEn = "Relative speed = {$fast} - {$slow} = ".($fast - $slow)." km/h. Time to catch up = Lead distance / Relative speed = {$time} hours.";
                    $explSi = "සාපේක්ෂ වේගය = ".($fast - $slow)." කි.මී./පැය. ලඟා වීමට ගතවන කාලය = ඉදිරි දුර / සාපේක්ෂ වේගය = පැය {$time}ක්.";
                }

                $rows[] = $this->numericRow(
                    $level, $en, $si, $time,
                    [$time + 1, max(1, $time - 1), $time + 2, $time + 3],
                    $explEn, $explSi,
                    "b2speed-{$speedA}-{$speedB}-{$time}-".($isMeeting ? 'm' : 'c'),
                    ['subcategory' => 'speed_distance', 'solving_time_seconds' => 45 + $level * 12, 'bloom_level' => 'apply'],
                );
            }
        }

        return $rows;
    }

    /** Fractional percentages of clean bases - harder mental arithmetic than the retired base bank. @return array<int,array> */
    private function percentages(): array
    {
        $combos = [];
        foreach ([12.5, 37.5, 62.5, 87.5] as $percent) {
            foreach (range(2, 60) as $mult) {
                $combos[] = [$percent, $mult * 8];
            }
        }
        foreach ([15, 35, 45, 65, 85] as $percent) {
            foreach (range(2, 45) as $mult) {
                $combos[] = [$percent, $mult * 20];
            }
        }
        mt_srand(810010);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([1 => 40, 2 => 30, 3 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$percent, $base] = $combos[$cursor++];
                $answer = (int) round($base * $percent / 100);
                $percentLabel = rtrim(rtrim(number_format($percent, 1), '0'), '.');

                $rows[] = $this->numericRow(
                    $level,
                    "What is {$percentLabel}% of {$base}?",
                    "{$base} හි {$percentLabel}% කීයද?",
                    $answer,
                    [$answer + intdiv($base, 20), $answer - intdiv($base, 20), $answer + intdiv($base, 10), intdiv($base, 2)],
                    "{$percentLabel}% of {$base} is {$answer}.",
                    "{$base} හි {$percentLabel}% ලෙස ලැබෙන්නේ {$answer} ය.",
                    "b2pct-{$percentLabel}-{$base}",
                    ['subcategory' => 'percentages', 'solving_time_seconds' => 25 + $level * 8, 'bloom_level' => 'apply'],
                );
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------
    // Row builder (numeric options, cross-seeder duplicate guard)
    // ---------------------------------------------------------------

    private function numericRow(int $level, string $textEn, string $textSi, int $answer, array $distractorCandidates, string $explEn, string $explSi, string $seedKey, array $meta): ?array
    {
        // Cross-seeder duplicate (e.g. the Advanced seeders drew the same
        // parameters): skip this row rather than seed a duplicate question.
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

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

        return [$level, 'mcq_text', $textEn, $textSi, $this->options($labels, $labels), $key, $explEn, $explSi,
            min(3, max(1, (int) ceil($level / 2))), $meta];
    }
}
