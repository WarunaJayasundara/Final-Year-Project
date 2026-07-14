<?php

namespace Database\Seeders\Questions\Bank4;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Passage-based weaken/strengthen critical reasoning. Built as a fixed
 * causal-argument template (extra exam practice sessions correlating
 * with higher marks), with only the numbers and weaken/strengthen mode
 * varying per row - not freeform generated prose. This keeps correctness
 * guaranteed by construction: a genuine confound always weakens a
 * correlation-implies-causation claim, and ruling it out always
 * strengthens it, so the correct option's logical role is fixed rather
 * than judged case by case.
 */
class CriticalReasoningPassageSeeder extends Seeder
{
    use BuildsQuestions;

    private array $seenTexts = [];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter($this->puzzles());

        $this->insertRows('logical_reasoning', $rows, [
            'subcategory' => 'critical_reasoning_passage',
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'critical_reasoning'],
            'cognitive_skill' => 'argument-evaluation',
            'source_type' => 'past_paper_inspired',
        ]);
    }

    private function puzzles(): array
    {
        $rows = [];
        $seedBase = 847001;
        foreach ([3 => 24, 4 => 24, 5 => 24] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase + $level * 1000 + $i);
                $m1 = random_int(40, 60);
                $diff = random_int(8, 22);
                $m2 = $m1 + $diff;
                $weakenMode = $i % 2 === 0;

                $rows[] = $this->row($level, $m1, $m2, $weakenMode, "b4crit-{$seedBase}-{$level}-{$i}");
            }
        }

        return array_filter($rows);
    }

    private function row(int $level, int $m1, int $m2, bool $weakenMode, string $seedKey): ?array
    {
        $passageEn = "A group of students who attended extra practice sessions before an exam scored {$m2} marks on average, "
            ."compared to {$m1} marks on average for a group that did not attend such sessions. "
            .'A researcher concludes: extra practice sessions before an exam increase exam marks.';
        $passageSi = "විභාගයකට පෙර අමතර පුහුණු පන්ති සහභාගී වූ සිසුන් කණ්ඩායමක සාමාන්‍ය ලකුණු {$m2}ක් වූ අතර, "
            ."එවැනි පන්ති සහභාගී නොවූ කණ්ඩායමක සාමාන්‍ය ලකුණු {$m1}ක් විය. "
            .'පර්යේෂකයෙකුගේ නිගමනය: විභාගයකට පෙර අමතර පුහුණු පන්ති, විභාග ලකුණු වැඩි කරයි.';

        $questionEn = $weakenMode
            ? 'Which of the following, if true, would most WEAKEN this conclusion?'
            : 'Which of the following, if true, would most STRENGTHEN this conclusion?';
        $questionSi = $weakenMode
            ? 'පහත සඳහන් දෑ අතුරින් සත්‍ය නම්, මෙම නිගමනය වඩාත්ම දුර්වල කරන්නේ කුමක්ද?'
            : 'පහත සඳහන් දෑ අතුරින් සත්‍ය නම්, මෙම නිගමනය වඩාත්ම ශක්තිමත් කරන්නේ කුමක්ද?';

        $en = "{$passageEn} {$questionEn}";
        $si = "{$passageSi} {$questionSi}";

        if (isset($this->seenTexts[$en])) {
            return null;
        }
        $this->seenTexts[$en] = true;

        $confoundEn = 'Before the extra sessions began, the students who later attended them already scored higher on average than the other group.';
        $confoundSi = 'අමතර පන්ති ආරම්භ වීමට පෙර, පසුව ඒවාට සහභාගී වූ සිසුන් දැනටමත් අනෙක් කණ්ඩායමට වඩා සාමාන්‍යයෙන් වැඩි ලකුණු ලබා තිබුණි.';
        $confoundRuledOutEn = 'Students were assigned to the two groups randomly, and both groups had an equal average mark before the sessions began.';
        $confoundRuledOutSi = 'සිසුන් කණ්ඩායම් දෙකට අහඹු ලෙස පත් කරන ලද අතර, පන්ති ආරම්භ වීමට පෙර දෙකෙහිම සාමාන්‍ය ලකුණු සමාන විය.';
        $irrelevantEn = 'The exam was held in the same examination hall for both groups.';
        $irrelevantSi = 'විභාගය කණ්ඩායම් දෙකටම එකම විභාග ශාලාවේදී පවත්වන ලදී.';
        $restatesEn = 'The group that attended extra practice sessions scored higher marks than the group that did not.';
        $restatesSi = 'අමතර පන්ති සහභාගී වූ කණ්ඩායම, නොවූ කණ්ඩායමට වඩා වැඩි ලකුණු ලබා ගත්තේය.';

        if ($weakenMode) {
            $correctEn = $confoundEn;
            $correctSi = $confoundSi;
            $poolEn = [$confoundRuledOutEn, $irrelevantEn, $restatesEn];
            $poolSi = [$confoundRuledOutSi, $irrelevantSi, $restatesSi];
        } else {
            $correctEn = $confoundRuledOutEn;
            $correctSi = $confoundRuledOutSi;
            $poolEn = [$confoundEn, $irrelevantEn, $restatesEn];
            $poolSi = [$confoundSi, $irrelevantSi, $restatesSi];
        }

        $labelsEn = [$correctEn, ...$poolEn];
        $labelsSi = [$correctSi, ...$poolSi];
        mt_srand(crc32($seedKey));
        $order = [0, 1, 2, 3];
        shuffle($order);
        $shuffledEn = array_map(fn ($i) => $labelsEn[$i], $order);
        $shuffledSi = array_map(fn ($i) => $labelsSi[$i], $order);
        $key = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        $explanationEn = $weakenMode
            ? 'A pre-existing difference between the two groups (a confound) undermines the claim that the sessions themselves caused the improvement.'
            : 'Random assignment with equal starting marks rules out the main confound, supporting the causal claim.';
        $explanationSi = $weakenMode
            ? 'කණ්ඩායම් දෙක අතර පෙර පැවති වෙනසක් (confound) මගින්, පන්ති නිසාම වැඩිදියුණුවක් සිදුවූ බවට ඇති තර්කය දුර්වල කරයි.'
            : 'අහඹු ලෙස කණ්ඩායම් වෙන් කිරීම හා ආරම්භයේ සමාන ලකුණු තිබීම, ප්‍රධාන confound සාධකය ඉවත් කර නිගමනයට සහාය වේ.';

        return [
            $level, 'mcq_text', $en, $si,
            $this->options($shuffledEn, $shuffledSi), $key,
            $explanationEn,
            $explanationSi,
            null,
            ['subcategory' => 'critical_reasoning_passage', 'solving_time_seconds' => 50 + $level * 14, 'bloom_level' => 'evaluate'],
        ];
    }
}
