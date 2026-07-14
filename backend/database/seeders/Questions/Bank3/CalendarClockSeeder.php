<?php

namespace Database\Seeders\Questions\Bank3;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Calendar (day-of-week arithmetic) and clock-angle reasoning, an
 * archetype missing from the question bank. Day-of-week answers are
 * computed with modular arithmetic on a 7-day cycle; clock-angle answers
 * use the real formula |30H - 5.5M| (reduced to 0-180), restricted to
 * even minute values so every answer is a clean integer degree count.
 */
class CalendarClockSeeder extends Seeder
{
    use BuildsQuestions;

    private array $seenTexts = [];

    private const DAYS_EN = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    private const DAYS_SI = ['ඉරිදා', 'සඳුදා', 'අඟහරුවාදා', 'බදාදා', 'බ්‍රහස්පතින්දා', 'සිකුරාදා', 'සෙනසුරාදා'];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->dayOfWeek(),
            $this->clockAngle(),
        ));

        $this->insertRows('logical_reasoning', $rows, [
            'subcategory' => 'calendar_clock',
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'calendar_clock'],
            'cognitive_skill' => 'temporal-modular-reasoning',
            'source_type' => 'past_paper_inspired',
        ]);
    }

    /** Level 1-3: day-of-week modular arithmetic. @return array<int,array> */
    private function dayOfWeek(): array
    {
        $combos = [];
        foreach (range(0, 6) as $startDay) {
            foreach (range(1, 60) as $offset) {
                $combos[] = [$startDay, $offset, true];
                $combos[] = [$startDay, $offset, false];
            }
        }
        mt_srand(833001);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([1 => 30, 2 => 30, 3 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$startDay, $offset, $isFuture] = $combos[$cursor++];
                $targetDay = $isFuture
                    ? ($startDay + $offset) % 7
                    : (($startDay - $offset) % 7 + 7) % 7;

                $startEn = self::DAYS_EN[$startDay];
                $startSi = self::DAYS_SI[$startDay];
                $answerEn = self::DAYS_EN[$targetDay];
                $answerSi = self::DAYS_SI[$targetDay];
                $direction = $isFuture ? 'after' : 'before';
                $directionSi = $isFuture ? 'පසුව' : 'පෙර';

                $en = "If today is {$startEn}, what day of the week will it be {$offset} day(s) {$direction} today?";
                $si = "අද {$startSi} වේ. දින {$offset}කට {$directionSi} සතියේ දිනය කුමක්ද?";

                $rows[] = $this->weekdayRow($level, $en, $si, $answerEn, $answerSi, "b3cal-{$startDay}-{$offset}-{$isFuture}");
            }
        }

        return $rows;
    }

    /** Level 3-5: clock hand angle (even minutes only, guarantees an integer degree answer). @return array<int,array> */
    private function clockAngle(): array
    {
        $combos = [];
        foreach (range(1, 12) as $hour) {
            foreach (range(0, 58, 2) as $minute) {
                $combos[] = [$hour, $minute];
            }
        }
        mt_srand(833002);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([3 => 30, 4 => 30, 5 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$hour, $minute] = $combos[$cursor++];
                $raw = abs(30 * $hour - 5.5 * $minute);
                $raw = $raw > 360 ? fmod($raw, 360) : $raw;
                $angle = (int) round(min($raw, 360 - $raw));
                $timeLabel = sprintf('%d:%02d', $hour, $minute);

                $en = "What is the angle (in degrees) between the hour and minute hands of a clock at {$timeLabel}?";
                $si = "ඔරලෝසුව {$timeLabel} වේ. කෝණය කීයද?";

                $rows[] = $this->angleRow($level, $en, $si, $angle, "b3clock-{$hour}-{$minute}");
            }
        }

        return $rows;
    }

    private function weekdayRow(int $level, string $textEn, string $textSi, string $answerEn, string $answerSi, string $seedKey): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $distractorIndices = array_values(array_diff(array_keys(self::DAYS_EN), [array_search($answerEn, self::DAYS_EN, true)]));
        mt_srand(crc32($seedKey));
        shuffle($distractorIndices);
        $distractorIndices = array_slice($distractorIndices, 0, 3);

        $labelsEn = [$answerEn, ...array_map(fn ($i) => self::DAYS_EN[$i], $distractorIndices)];
        $labelsSi = [$answerSi, ...array_map(fn ($i) => self::DAYS_SI[$i], $distractorIndices)];
        $order = [0, 1, 2, 3];
        shuffle($order);
        $shuffledEn = array_map(fn ($i) => $labelsEn[$i], $order);
        $shuffledSi = array_map(fn ($i) => $labelsSi[$i], $order);
        $correctKey = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        return [
            $level, 'mcq_text', $textEn, $textSi,
            $this->options($shuffledEn, $shuffledSi), $correctKey,
            "Counting forward/backward on a 7-day cycle gives {$answerEn}.",
            "පිළිතුර {$answerSi} වේ.",
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'calendar_clock', 'solving_time_seconds' => 30 + $level * 10, 'bloom_level' => 'apply'],
        ];
    }

    private function angleRow(int $level, string $textEn, string $textSi, int $answer, string $seedKey): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $distractors = array_values(array_unique(array_filter([
            min(360, $answer + 15), max(0, $answer - 15), min(360, $answer + 30),
        ], fn ($d) => $d !== $answer)));
        while (count($distractors) < 3) {
            $distractors[] = min(360, $answer + 45 + count($distractors) * 10);
        }
        $distractors = array_slice($distractors, 0, 3);

        $values = [$answer, ...$distractors];
        mt_srand(crc32($seedKey));
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];
        $labels = array_map('strval', $values);

        return [
            $level, 'mcq_text', $textEn, $textSi,
            $this->options($labels, $labels), $key,
            "Using |30xHour - 5.5xMinute| reduced to the 0-180 range gives {$answer} degrees.",
            "පිළිතුර {$answer} වේ.",
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'calendar_clock', 'solving_time_seconds' => 40 + $level * 12, 'bloom_level' => 'analyze'],
        ];
    }
}
