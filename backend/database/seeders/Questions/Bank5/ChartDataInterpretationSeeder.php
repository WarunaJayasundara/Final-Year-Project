<?php

namespace Database\Seeders\Questions\Bank5;

use App\Services\QuestionBank\SvgFigureBuilder;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Chart-based data interpretation - the archetype confirmed present in the
 * uploaded Environmental Officer exam guide's own "Data Interpretation"
 * chapter (bar/pie/line charts, percentage-share and comparison questions)
 * but previously unavailable as an IMAGE question in MindRise (Bank3's
 * existing DataInterpretationSeeder is text-table-only). Uses the new
 * SvgFigureBuilder chart panel type added this session. Every answer is
 * computed directly from the real generated series - never asserted.
 */
class ChartDataInterpretationSeeder extends Seeder
{
    use BuildsQuestions;

    private const CATEGORY_LABELS = ['A', 'B', 'C', 'D', 'E'];

    private SvgFigureBuilder $svg;

    private array $seen = [];

    public function run(): void
    {
        $this->svg = new SvgFigureBuilder();

        $rows = array_merge(
            $this->barChartQuestions(),
            $this->pieChartQuestions(),
            $this->lineChartQuestions(),
        );

        $this->insertRows('numerical_ability', $rows, [
            'subcategory' => 'data_interpretation',
            'exam_tags' => ['data_interpretation', 'gov_aptitude', 'chart_reading'],
            'cognitive_skill' => 'quantitative-data-interpretation',
            'source_type' => 'past_paper_inspired',
        ]);
    }

    private function barChartQuestions(): array
    {
        $rows = [];
        $seedBase = 852001;
        foreach ([3 => 20, 4 => 20, 5 => 20] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase + $level * 1000 + $i);
                $n = random_int(4, 5);
                $series = [];
                for ($k = 0; $k < $n; $k++) {
                    $series[] = random_int(10, 95);
                }
                if (count(array_unique($series)) !== $n) {
                    continue;
                }

                $signature = 'bar|'.implode(',', $series);
                if (isset($this->seen[$signature])) {
                    continue;
                }
                $this->seen[$signature] = true;

                $imagePath = $this->writeSvg('data_interpretation_bar', $signature, $this->svg->compose([['chart' => 'bar', 'series' => $series]], [], 1, 220));
                $labels = array_slice(self::CATEGORY_LABELS, 0, $n);

                $maxIdx = array_keys($series, max($series))[0];
                $minIdx = array_keys($series, min($series))[0];
                $askMax = $i % 2 === 0;
                $targetIdx = $askMax ? $maxIdx : $minIdx;

                $en = $askMax
                    ? 'The bar chart above shows values for categories '.implode(', ', $labels).'. Which category has the HIGHEST value?'
                    : 'The bar chart above shows values for categories '.implode(', ', $labels).'. Which category has the LOWEST value?';
                $si = $askMax
                    ? 'ඉහත තීරු ප්‍රස්ථාරයේ '.implode(', ', $labels).' කාණ්ඩ සඳහා අගයන් දැක්වේ. වැඩිම අගය ඇති කාණ්ඩය කුමක්ද?'
                    : 'ඉහත තීරු ප්‍රස්ථාරයේ '.implode(', ', $labels).' කාණ්ඩ සඳහා අගයන් දැක්වේ. අඩුම අගය ඇති කාණ්ඩය කුමක්ද?';

                $rows[] = $this->categoryRow($level, $en, $si, $labels, $targetIdx, $imagePath, "b5bar-{$seedBase}-{$level}-{$i}", 'bar_chart_reading');
            }
        }

        return array_filter($rows);
    }

    private function pieChartQuestions(): array
    {
        $rows = [];
        $seedBase = 852501;
        foreach ([3 => 15, 4 => 15, 5 => 15] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase + $level * 1000 + $i);
                $n = random_int(3, 4);
                $parts = [];
                $remaining = 100;
                for ($k = 0; $k < $n - 1; $k++) {
                    $max = $remaining - ($n - 1 - $k) * 5;
                    if ($max < 5) {
                        continue 2;
                    }
                    $v = random_int(5, $max);
                    $parts[] = $v;
                    $remaining -= $v;
                }
                $parts[] = $remaining;
                if ($remaining < 5 || count(array_unique($parts)) !== $n) {
                    continue;
                }

                $signature = 'pie|'.implode(',', $parts);
                if (isset($this->seen[$signature])) {
                    continue;
                }
                $this->seen[$signature] = true;

                $imagePath = $this->writeSvg('data_interpretation_pie', $signature, $this->svg->compose([['chart' => 'pie', 'series' => $parts]], [], 1, 220));
                $labels = array_slice(self::CATEGORY_LABELS, 0, $n);
                $targetIdx = array_rand($parts);
                $answerPercent = $parts[$targetIdx];

                $en = 'The pie chart above shows the percentage share of categories '.implode(', ', $labels)
                    ." (the slices sum to 100%). What percentage share does category {$labels[$targetIdx]} represent?";
                $si = 'ඉහත වට ප්‍රස්ථාරයේ '.implode(', ', $labels)." කාණ්ඩවල ප්‍රතිශත කොටස් දැක්වේ (එකතුව 100%). කාණ්ඩය {$labels[$targetIdx]} නියෝජනය කරන ප්‍රතිශතය කීයද?";

                $rows[] = $this->numericPercentRow($level, $en, $si, $answerPercent, $imagePath, "b5pie-{$seedBase}-{$level}-{$i}");
            }
        }

        return array_filter($rows);
    }

    private function lineChartQuestions(): array
    {
        $rows = [];
        $seedBase = 853001;
        foreach ([3 => 15, 4 => 15, 5 => 15] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase + $level * 1000 + $i);
                $n = random_int(5, 6);
                $series = [];
                $v = random_int(20, 60);
                for ($k = 0; $k < $n; $k++) {
                    $series[] = $v;
                    $v = max(5, min(95, $v + random_int(-25, 25)));
                }
                if (count(array_unique($series)) < $n - 1) {
                    continue;
                }

                $signature = 'line|'.implode(',', $series);
                if (isset($this->seen[$signature])) {
                    continue;
                }

                $deltas = [];
                for ($k = 1; $k < $n; $k++) {
                    $deltas[$k] = $series[$k] - $series[$k - 1];
                }
                $maxIncreaseStep = array_keys($deltas, max($deltas))[0];
                if (max($deltas) <= 0) {
                    continue;
                }
                $this->seen[$signature] = true;

                $imagePath = $this->writeSvg('data_interpretation_line', $signature, $this->svg->compose([['chart' => 'line', 'series' => $series]], [], 1, 220));

                $en = "The line chart above shows a value tracked across {$n} consecutive periods (period 1 to period {$n}). "
                    .'Between which two consecutive periods did the value increase the most?';
                $si = "ඉහත රේඛීය ප්‍රස්ථාරයේ අගයක් අවධි {$n}ක් (අවධි 1 සිට {$n} දක්වා) පුරා දැක්වේ. "
                    .'අගය වඩාත්ම වැඩි වූයේ එකිනෙකට යාබද කුමන අවධි දෙක අතරද?';

                $answerLabel = "Period {$maxIncreaseStep} to Period ".($maxIncreaseStep + 1);
                $answerLabelSi = "අවධි {$maxIncreaseStep} සිට අවධි ".($maxIncreaseStep + 1)." දක්වා";
                $distractorSteps = array_values(array_diff(range(1, $n - 1), [$maxIncreaseStep]));
                mt_srand(crc32("b5line-{$seedBase}-{$level}-{$i}"));
                shuffle($distractorSteps);
                $chosenSteps = array_slice($distractorSteps, 0, 3);
                if (count($chosenSteps) < 3) {
                    continue;
                }

                $labelsEn = [$answerLabel, ...array_map(fn ($s) => "Period {$s} to Period ".($s + 1), $chosenSteps)];
                $labelsSi = [$answerLabelSi, ...array_map(fn ($s) => "අවධි {$s} සිට අවධි ".($s + 1)." දක්වා", $chosenSteps)];
                $order = [0, 1, 2, 3];
                shuffle($order);
                $shuffledEn = array_map(fn ($i) => $labelsEn[$i], $order);
                $shuffledSi = array_map(fn ($i) => $labelsSi[$i], $order);
                $correctKey = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

                $rows[] = [
                    $level, 'mcq_image', $en, $si,
                    $this->options($shuffledEn, $shuffledSi), $correctKey,
                    "Computing the change between every consecutive pair of periods, the largest increase (+{$deltas[$maxIncreaseStep]}) happens from Period {$maxIncreaseStep} to Period ".($maxIncreaseStep + 1).'.',
                    'එකිනෙකට යාබද සෑම අවධි යුගලයක්ම සසඳා බැලීමෙන් වැඩිම වැඩිවීම හඳුනාගත හැක.',
                    null,
                    [
                        'subcategory' => 'data_interpretation', 'image_path' => $imagePath,
                        'solving_time_seconds' => 50 + $level * 14, 'bloom_level' => 'analyze',
                        'generation_rule' => 'line_chart_max_delta',
                    ],
                ];
            }
        }

        return array_filter($rows);
    }

    private function categoryRow(int $level, string $en, string $si, array $labels, int $targetIdx, string $imagePath, string $seedKey, string $rule): ?array
    {
        if (isset($this->seen[$en])) {
            return null;
        }
        $this->seen[$en] = true;

        $distractorLabels = array_values(array_diff($labels, [$labels[$targetIdx]]));
        mt_srand(crc32($seedKey));
        shuffle($distractorLabels);
        $chosen = array_slice($distractorLabels, 0, 3);
        while (count($chosen) < 3) {
            $chosen[] = $labels[array_rand($labels)];
        }

        $optionLabels = [$labels[$targetIdx], ...$chosen];
        $order = [0, 1, 2, 3];
        shuffle($order);
        $shuffled = array_map(fn ($i) => $optionLabels[$i], $order);
        $correctKey = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        return [
            $level, 'mcq_image', $en, $si,
            $this->options($shuffled, $shuffled), $correctKey,
            "Reading the bar heights directly from the chart identifies category {$labels[$targetIdx]}.",
            "ප්‍රස්ථාරයේ තීරු උස සෘජුවම කියවීමෙන් නිවැරදි කාණ්ඩය හඳුනාගත හැක.",
            null,
            [
                'subcategory' => 'data_interpretation', 'image_path' => $imagePath,
                'solving_time_seconds' => 35 + $level * 10, 'bloom_level' => 'apply',
                'generation_rule' => $rule,
            ],
        ];
    }

    private function numericPercentRow(int $level, string $en, string $si, int $answer, string $imagePath, string $seedKey): ?array
    {
        if (isset($this->seen[$en])) {
            return null;
        }
        $this->seen[$en] = true;

        mt_srand(crc32($seedKey));
        $distractors = array_values(array_unique(array_filter([
            $answer + random_int(3, 10), max(1, $answer - random_int(3, 10)), $answer + random_int(11, 20),
        ], fn ($d) => $d !== $answer && $d > 0 && $d < 100)));
        while (count($distractors) < 3) {
            $distractors[] = max(1, min(99, $answer + count($distractors) * 7 + 3));
        }
        $values = [$answer, ...array_slice($distractors, 0, 3)];
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];
        $labels = array_map(fn ($v) => "{$v}%", $values);

        return [
            $level, 'mcq_image', $en, $si,
            $this->options($labels, $labels), $key,
            "The slice's share is exactly {$answer}% of the whole pie by construction.",
            "එම කොටස මුළු වට ප්‍රස්ථාරයෙන් හරියටම {$answer}%ක් වේ.",
            null,
            [
                'subcategory' => 'data_interpretation', 'image_path' => $imagePath,
                'solving_time_seconds' => 40 + $level * 12, 'bloom_level' => 'apply',
                'generation_rule' => 'pie_chart_percentage_share',
            ],
        ];
    }

    private function writeSvg(string $subcategory, string $signature, string $svg): string
    {
        $path = 'questions/generated/'.$subcategory.'/'.md5($signature).'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
