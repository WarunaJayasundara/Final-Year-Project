<?php

namespace Database\Seeders\Questions;

use Illuminate\Database\Seeder;

class NumericalAbilityQuestionsSeeder extends Seeder
{
    use BuildsQuestions;

    private const PER_LEVEL_WORD_PROBLEM = 27;
    private const PER_LEVEL_SERIES = 27;
    private const PER_LEVEL_PERCENTAGE = 26;

    public function run(): void
    {
        $rows = [];

        foreach (range(1, 5) as $level) {
            for ($i = 0; $i < self::PER_LEVEL_WORD_PROBLEM; $i++) {
                $rows[] = $this->buildWordProblem($level, $i);
            }
            for ($i = 0; $i < self::PER_LEVEL_SERIES; $i++) {
                $rows[] = $this->buildSeries($level, $i);
            }
            for ($i = 0; $i < self::PER_LEVEL_PERCENTAGE; $i++) {
                $rows[] = $this->buildPercentage($level, $i);
            }
        }

        $this->insertRows('numerical_ability', $rows);
    }

    private function distractorsAround(int $answer, int $count, int $spread): array
    {
        $set = [$answer];
        while (count($set) < $count + 1) {
            $delta = mt_rand(1, $spread);
            $candidate = mt_rand(0, 1) ? $answer + $delta : max(0, $answer - $delta);
            if (! in_array($candidate, $set, true)) {
                $set[] = $candidate;
            }
        }
        shuffle($set);

        return $set;
    }

    private function optionsFromValues(array $values, int $answer): array
    {
        $labels = array_map('strval', $values);
        $options = $this->options($labels, $labels);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];

        return [$options, $key];
    }

    private function buildWordProblem(int $level, int $variant): array
    {
        mt_srand($level * 300000 + $variant * 97 + 13);
        $scale = $level * 10;
        $a = mt_rand(3 * $scale, 9 * $scale);
        // Keep b comfortably below a so the "how many are left" subtraction
        // template never goes negative (can't sell more books than you had).
        $b = mt_rand($scale, max($scale, intdiv($a, 2)));
        $groups = mt_rand(2, 4 + $level);
        // Round a down so it always divides evenly by groups - keeps the
        // "split evenly into classes" word problem factually consistent.
        $a = max($groups, $a - ($a % $groups));

        $templates = [
            fn () => [
                "A shop had {$a} books. It sold {$b} of them. How many books are left?",
                "වෙළඳසැලක පොත් {$a}ක් තිබුණි. ඉන් {$b}ක් විකුණන ලදී. ඉතිරිව ඇත්තේ පොත් කීයද?",
                $a - $b,
            ],
            fn () => [
                "A bus route has {$a} passengers in the morning and {$b} more board in the afternoon. How many passengers travelled in total?",
                "බස් මාර්ගයක උදෑසන මගීන් {$a}ක් සිටී. සවස තවත් {$b}ක් නැඟුනි. මුළු මගී ගණන කීයද?",
                $a + $b,
            ],
            fn () => [
                "A farmer has {$groups} baskets with {$b} mangoes in each basket. How many mangoes does the farmer have in total?",
                "ගොවියෙකුට කූඩ {$groups}ක් ඇති අතර සෑම කූඩයකම අඹ {$b}ක් ඇත. ගොවියාට ඇති මුළු අඹ ගණන කීයද?",
                $groups * $b,
            ],
            fn () => [
                "A school has {$a} students split evenly into {$groups} classes. How many students are in each class?",
                "පාසලක සිසුන් {$a}ක් ඇති අතර ඔවුන් සමානව පන්ති {$groups}කට බෙදා ඇත. එක් පන්තියක සිසුන් කීදෙනෙක් සිටිත්ද?",
                intdiv($a - ($a % $groups), $groups),
            ],
        ];

        [$en, $si, $answer] = $templates[$variant % count($templates)]();
        $distractors = $this->distractorsAround($answer, 3, max(3, intdiv(max($answer, 1), 5) + 1));
        [$options, $key] = $this->optionsFromValues($distractors, $answer);

        return [$level, 'mcq_text', $en, $si, $options, $key,
            "The correct calculation gives {$answer}.",
            "නිවැරදි ගණනය කිරීමෙන් ලැබෙන්නේ {$answer} ය.", ];
    }

    private function buildSeries(int $level, int $variant): array
    {
        mt_srand($level * 400000 + $variant * 83 + 17);
        $isArithmetic = $variant % 2 === 0;
        $start = mt_rand(1, 12 + $level * 4);

        if ($isArithmetic) {
            $step = mt_rand(2, 6 + $level * 2);
            $seq = [$start, $start + $step, $start + 2 * $step, $start + 3 * $step];
            $answer = $start + 4 * $step;
        } else {
            $ratio = $level >= 4 ? 3 : 2;
            $seq = [$start, $start * $ratio, $start * $ratio ** 2, $start * $ratio ** 3];
            $answer = $start * $ratio ** 4;
        }

        $seqStr = implode(', ', $seq);
        $distractors = $this->distractorsAround($answer, 3, max(3, intdiv($answer, 8) + 1));
        [$options, $key] = $this->optionsFromValues($distractors, $answer);

        return [$level, 'mcq_text',
            "What is the next number in the series: {$seqStr}, ... ?",
            "මෙම ශ්‍රේණියේ ඊළඟ අංකය කුමක්ද: {$seqStr}, ... ?",
            $options, $key,
            "Following the pattern, the next number is {$answer}.",
            "රටාවට අනුව ඊළඟ අංකය {$answer} වේ.", ];
    }

    private function buildPercentage(int $level, int $variant): array
    {
        mt_srand($level * 500000 + $variant * 71 + 19);
        $base = mt_rand(2, 60) * 10 * max(1, $level - 1) + 100;
        $percent = [10, 20, 25, 50, 5, 75][$variant % 6];
        $answer = (int) ($base * $percent / 100);

        $distractors = $this->distractorsAround($answer, 3, max(3, intdiv($answer, 6) + 1));
        [$options, $key] = $this->optionsFromValues($distractors, $answer);

        return [$level, 'mcq_text',
            "What is {$percent}% of {$base}?",
            "{$base} හි {$percent}% කීයද?",
            $options, $key,
            "{$percent}% of {$base} is {$answer}.",
            "{$base} හි {$percent}% ලෙස ලැබෙන්නේ {$answer} ය.", ];
    }
}
