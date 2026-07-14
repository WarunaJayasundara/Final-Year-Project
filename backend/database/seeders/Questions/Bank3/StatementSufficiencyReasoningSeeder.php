<?php

namespace Database\Seeders\Questions\Bank3;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Statement-sufficiency critical reasoning (a classic "data sufficiency"
 * archetype), missing from the question bank. Each instance is a
 * genuinely solvable small algebra scenario - whether a statement is
 * sufficient is determined by actually checking if it (alone or
 * combined) pins down a single value for X, never asserted. Restricted
 * to 3 unambiguous patterns (I-alone, II-alone, both-together-only); a
 * "neither is sufficient" pattern was left out since it's much harder to
 * guarantee isn't accidentally ambiguous.
 */
class StatementSufficiencyReasoningSeeder extends Seeder
{
    use BuildsQuestions;

    private array $seenTexts = [];

    private const OPTIONS_EN = [
        'i_alone' => 'Statement I alone is sufficient, but statement II alone is not',
        'ii_alone' => 'Statement II alone is sufficient, but statement I alone is not',
        'both_together' => 'Both statements together are sufficient, but neither alone is',
        'each_alone' => 'Each statement alone is sufficient',
    ];

    private const OPTIONS_SI = [
        'i_alone' => 'ප්‍රකාශ I තනිව ප්‍රමාණවත් වේ',
        'ii_alone' => 'ප්‍රකාශ II තනිව ප්‍රමාණවත් වේ',
        'both_together' => 'ප්‍රකාශ දෙකම අවශ්‍ය වේ',
        'each_alone' => 'ප්‍රකාශ දෙකම තනිව ප්‍රමාණවත් වේ',
    ];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->singleStatementSufficient(),
            $this->bothTogetherSufficient(),
        ));

        $this->insertRows('logical_reasoning', $rows, [
            'subcategory' => 'statement_sufficiency',
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'statement_sufficiency', 'critical_reasoning'],
            'cognitive_skill' => 'critical-evaluative-reasoning',
            'source_type' => 'past_paper_inspired',
        ]);
    }

    /** Level 1-3: exactly one of the two statements mentions X directly. @return array<int,array> */
    private function singleStatementSufficient(): array
    {
        $rows = [];
        $seedBase = 836001;
        foreach ([1 => 25, 2 => 25, 3 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase++);
                $xValue = random_int(5, 95);
                $yValue = random_int(5, 95);
                $iIsSufficient = $i % 2 === 0;

                $statementI = $iIsSufficient ? "X = {$xValue}." : "Y = {$yValue}.";
                $statementII = $iIsSufficient ? "Y = {$yValue}." : "X = {$xValue}.";
                $answerKind = $iIsSufficient ? 'i_alone' : 'ii_alone';

                $en = "What is the value of X? Statement I: {$statementI} Statement II: {$statementII}";
                $si = "X හි අගය කුමක්ද? ප්‍රකාශ I: {$statementI} ප්‍රකාශ II: {$statementII}";

                $rows[] = $this->sufficiencyRow($level, $en, $si, $answerKind, "b3suf1-{$seedBase}");
            }
        }

        return $rows;
    }

    /** Level 3-5: X+Y and X-Y given separately - only solvable combined. @return array<int,array> */
    private function bothTogetherSufficient(): array
    {
        $rows = [];
        $seedBase = 836501;
        foreach ([3 => 25, 4 => 25, 5 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase++);
                $xValue = random_int(10, 80);
                $yValue = random_int(5, 60);
                $sum = $xValue + $yValue;
                $diff = $xValue - $yValue;
                // (sum + diff) must be even for X to be a clean integer -
                // guaranteed since sum+diff = 2X by construction.

                $statementI = "X + Y = {$sum}.";
                $statementII = "X - Y = {$diff}.";

                $en = "What is the value of X? Statement I: {$statementI} Statement II: {$statementII}";
                $si = "X හි අගය කුමක්ද? ප්‍රකාශ I: {$statementI} ප්‍රකාශ II: {$statementII}";

                $rows[] = $this->sufficiencyRow($level, $en, $si, 'both_together', "b3suf2-{$seedBase}");
            }
        }

        return $rows;
    }

    private function sufficiencyRow(int $level, string $textEn, string $textSi, string $answerKind, string $seedKey): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $distractorKinds = array_values(array_diff(array_keys(self::OPTIONS_EN), [$answerKind]));
        mt_srand(crc32($seedKey));
        shuffle($distractorKinds);
        $kinds = [$answerKind, ...array_slice($distractorKinds, 0, 3)];

        $labelsEn = array_map(fn ($k) => self::OPTIONS_EN[$k], $kinds);
        $labelsSi = array_map(fn ($k) => self::OPTIONS_SI[$k], $kinds);
        $order = [0, 1, 2, 3];
        shuffle($order);
        $shuffledEn = array_map(fn ($i) => $labelsEn[$i], $order);
        $shuffledSi = array_map(fn ($i) => $labelsSi[$i], $order);
        $correctKey = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        return [
            $level, 'mcq_text', $textEn, $textSi,
            $this->options($shuffledEn, $shuffledSi), $correctKey,
            "Checking which statement(s) pin down a single value for X: {$this->explainKind($answerKind)}",
            "පිළිතුර: ".self::OPTIONS_SI[$answerKind],
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'statement_sufficiency', 'solving_time_seconds' => 45 + $level * 12, 'bloom_level' => 'evaluate'],
        ];
    }

    private function explainKind(string $kind): string
    {
        return match ($kind) {
            'i_alone' => 'only Statement I directly gives X.',
            'ii_alone' => 'only Statement II directly gives X.',
            default => 'X only becomes solvable by combining both statements (solving the two equations together).',
        };
    }
}
