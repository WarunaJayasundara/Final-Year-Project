<?php

namespace Database\Seeders\Questions\Bank4;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Multi-step (2-3 chained operations) adult numeric word problems: ages,
 * weighted-ratio profit-sharing, chained work-and-time, and relative-speed
 * problems. Fills a gap in the existing numerical seeders, which don't go
 * past a single chained operation at Level 4-5. Every row is generated
 * forward, never asserted: the scenario's hidden target value is picked
 * first, then the visible clues are derived from it, so the printed
 * answer is always the exact value used to build the question.
 */
class AdultWordProblemSeeder extends Seeder
{
    use BuildsQuestions;

    private array $seenTexts = [];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->agesWithFutureRatio(),
            $this->weightedPartnershipProfit(),
            $this->chainedWorkAndTime(),
            $this->trainCrossingSpeed(),
        ));

        $this->insertRows('numerical_ability', $rows, [
            'exam_tags' => ['numerical_ability', 'gov_aptitude', 'multi_step'],
            'source_type' => 'past_paper_inspired',
        ]);
    }

    /** B's current age, from an age-difference + a future-ratio clue. @return array<int,array> */
    private function agesWithFutureRatio(): array
    {
        $rows = [];
        $seedBase = 845001;
        foreach ([3 => 20, 4 => 25, 5 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase + $level * 1000 + $i);
                $b = random_int(4, 40);
                $x = random_int(18, 32);
                $a = $b + $x;
                $n = random_int(2, 15);
                $futureA = $a + $n;
                $futureB = $b + $n;
                $g = $this->gcd($futureA, $futureB);
                $p = intdiv($futureA, $g);
                $q = intdiv($futureB, $g);
                if ($p === $q || $p > 30 || $q > 30) {
                    continue;
                }

                $en = "A is {$x} years older than B. In {$n} years, the ratio of A's age to B's age will be {$p}:{$q}. What is B's current age?";
                $si = "A, B ට වඩා අවුරුදු {$x}කින් වැඩිමහල්ය. අවුරුදු {$n}කට පසු A ගේ වයස සහ B ගේ වයස අතර අනුපාතය {$p}:{$q} වේ. B ගේ වර්තමාන වයස කීයද?";

                $rows[] = $this->numericRow($level, $en, $si, $b, "b4age-{$seedBase}-{$level}-{$i}", 'years', 40 + $level * 12);
            }
        }

        return $rows;
    }

    /** Profit share from capital x time weighted partnership. @return array<int,array> */
    private function weightedPartnershipProfit(): array
    {
        $rows = [];
        $seedBase = 845501;
        foreach ([3 => 20, 4 => 25, 5 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase + $level * 1000 + $i);
                $capitalA = random_int(2, 12) * 10000;
                $capitalB = random_int(2, 12) * 10000;
                $totalMonths = random_int(8, 12);
                $joinMonth = random_int(2, $totalMonths - 2);
                $monthsB = $totalMonths - $joinMonth;

                $unitsA = intdiv($capitalA, 10000) * $totalMonths;
                $unitsB = intdiv($capitalB, 10000) * $monthsB;
                $g = $this->gcd($unitsA, $unitsB);
                $unitsA /= $g;
                $unitsB /= $g;

                $unitValue = random_int(500, 4000);
                $totalProfit = $unitValue * ($unitsA + $unitsB);
                $shareB = $unitValue * $unitsB;

                $en = "A invests Rs. {$capitalA} for the full {$totalMonths} months of a business venture. "
                    ."B invests Rs. {$capitalB}, joining {$joinMonth} months after the venture started. "
                    ."At the end of {$totalMonths} months the venture makes a total profit of Rs. {$totalProfit}, "
                    .'shared in proportion to (capital x months invested). What is B\'s share of the profit, in rupees?';
                $si = "A රු. {$capitalA}ක් මාස {$totalMonths} සම්පූර්ණයෙන්ම ආයෝජනය කරයි. "
                    ."B ව්‍යාපාරය ආරම්භ වී මාස {$joinMonth}කට පසු රු. {$capitalB}ක් ආයෝජනය කරයි. "
                    ."මාස {$totalMonths} අවසානයේ මුළු ලාභය රු. {$totalProfit}ක් වන අතර, එය (ප්‍රාග්ධනය x මාස ගණන) අනුපාතයට බෙදේ. "
                    .'B ට හිමි ලාභ කොටස රුපියල් වලින් කීයද?';

                $rows[] = $this->numericRow($level, $en, $si, $shareB, "b4prof-{$seedBase}-{$level}-{$i}", 'rupees', 55 + $level * 15);
            }
        }

        return $rows;
    }

    /** Chained work-and-time: two work together for K days, one leaves, how many more days for the other. @return array<int,array> */
    private function chainedWorkAndTime(): array
    {
        $rows = [];
        $seedBase = 846001;
        foreach ([3 => 20, 4 => 25, 5 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase + $level * 1000 + $i);
                $a = random_int(2, 6);
                $b = random_int(2, 6);
                $l = random_int(2, 5);
                $d1 = $a * $l;
                $d2 = $b * $l;
                $maxK = max(1, intdiv($b * $l, $a + $b) - 1);
                $k = random_int(1, $maxK);
                $daysTogether = $a * $k;
                $additional = $b * $l - $k * ($a + $b);

                if ($additional <= 0 || $daysTogether <= 0 || $d1 === $d2) {
                    continue;
                }

                $en = "X can complete a task alone in {$d1} days and Y can complete the same task alone in {$d2} days. "
                    ."They work together for {$daysTogether} days, after which X stops working. "
                    .'How many more days does Y alone need to finish the remaining work?';
                $si = "X ට තනිව කාර්යයක් නිම කිරීමට දින {$d1}ක්ද, Y ට එම කාර්යයම තනිව නිම කිරීමට දින {$d2}ක්ද ගත වේ. "
                    ."ඔවුන් දෙදෙනා එක්ව දින {$daysTogether}ක් වැඩ කරන අතර, ඉන් පසු X නවතී. "
                    .'ඉතිරි වැඩ කොටස නිම කිරීමට Y ට තව දින කීයක් ගතවේද?';

                $rows[] = $this->numericRow($level, $en, $si, $additional, "b4work-{$seedBase}-{$level}-{$i}", 'days', 50 + $level * 14);
            }
        }

        return $rows;
    }

    /** Train crossing a stationary pole: length + speed -> time (real km/h to m/s conversion). @return array<int,array> */
    private function trainCrossingSpeed(): array
    {
        $rows = [];
        $seedBase = 846501;
        foreach ([3 => 20, 4 => 20, 5 => 20] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase + $level * 1000 + $i);
                $speedKmh = random_int(4, 20) * 9; // multiples of 9 keep the m/s conversion clean
                $speedMs = $speedKmh * 5 / 18;
                $lengthM = random_int(5, 40) * 10;
                $timeSec = round($lengthM / $speedMs, 1);

                $en = "A train {$lengthM} metres long is running at a speed of {$speedKmh} km/h. "
                    .'How many seconds does it take to completely cross a stationary signal pole?';
                $si = "දුම්රියක් මීටර් {$lengthM}ක් දිගය. එය පැයට කිලෝමීටර් {$speedKmh}ක වේගයෙන් ධාවනය වේ. "
                    .'එය ස්ථාවර සංඥා කණුවක් සම්පූර්ණයෙන් තරණය කිරීමට තත්පර කීයක් ගතවේද?';

                $rows[] = $this->numericRow($level, $en, $si, $timeSec, "b4train-{$seedBase}-{$level}-{$i}", 'seconds', 35 + $level * 10, true);
            }
        }

        return $rows;
    }

    private function numericRow(int $level, string $textEn, string $textSi, float|int $answer, string $seedKey, string $unit, int $solvingTime, bool $decimal = false): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        mt_srand(crc32($seedKey));
        $distractors = [];
        while (count($distractors) < 3) {
            $delta = $decimal
                ? round((random_int(-30, 30) / 10) * max(1, $answer * 0.15), 1)
                : max(1, (int) round($answer * 0.1)) * random_int(1, 3) * (random_int(0, 1) === 0 ? 1 : -1);
            $candidate = $decimal ? round($answer + $delta, 1) : (int) $answer + $delta;
            if ($candidate > 0 && $candidate !== $answer && ! in_array($candidate, $distractors, true)) {
                $distractors[] = $candidate;
            }
        }

        $values = [$answer, ...$distractors];
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];
        $labels = array_map(fn ($v) => $decimal ? number_format($v, 1) : (string) $v, $values);

        $answerLabel = $decimal ? number_format($answer, 1) : (string) $answer;

        return [
            $level, 'mcq_text', $textEn, $textSi,
            $this->options($labels, $labels), $key,
            "Working through the chained calculation step by step gives {$answerLabel} {$unit}.",
            "පියවරෙන් පියවර ගණනය කිරීමෙන් පිළිතුර {$answerLabel} බව තහවුරු වේ.",
            null,
            ['solving_time_seconds' => $solvingTime, 'bloom_level' => $level >= 4 ? 'analyze' : 'apply'],
        ];
    }

    private function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, $a);
    }
}
