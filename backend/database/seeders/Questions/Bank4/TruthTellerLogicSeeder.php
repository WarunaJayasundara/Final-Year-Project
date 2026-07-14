<?php

namespace Database\Seeders\Questions\Bank4;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Multi-statement truth-count reasoning: "how many of these statements
 * are true" rather than StatementSufficiencyReasoningSeeder's "do these
 * statements pin down a single value" question. Every instance is
 * evaluated by direct integer comparison against the generated X, never
 * asserted. Level 3 reveals X directly (single-step evaluation of 3
 * claims); Level 4-5 derives X from one linear clue first.
 */
class TruthTellerLogicSeeder extends Seeder
{
    use BuildsQuestions;

    private array $seenTexts = [];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->directTruthCount(),
            $this->derivedTruthCount(),
        ));

        $this->insertRows('logical_reasoning', $rows, [
            'subcategory' => 'truth_teller_logic',
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'truth_teller_logic', 'critical_reasoning'],
            'cognitive_skill' => 'critical-evaluative-reasoning',
            'source_type' => 'past_paper_inspired',
        ]);
    }

    /** Level 3: X given directly, evaluate 3 threshold claims. @return array<int,array> */
    private function directTruthCount(): array
    {
        $rows = [];
        $seedBase = 841001;
        for ($i = 0; $i < 60; $i++) {
            mt_srand($seedBase + $i);
            $x = random_int(30, 400);
            [$claims, $trueCount] = $this->buildClaims($x, $seedBase + $i);

            $en = "X is a certain number. Statement I: X is at least {$claims[0]['bound']}. "
                ."Statement II: X is more than {$claims[1]['bound']}. "
                ."Statement III: X is less than {$claims[2]['bound']}. "
                ."If X = {$x}, how many of these three statements are true?";
            $si = "X යනු සංඛ්‍යාවකි. ප්‍රකාශ I: X අවම වශයෙන් {$claims[0]['bound']} වේ. "
                ."ප්‍රකාශ II: X {$claims[1]['bound']}ට වඩා වැඩිය. "
                ."ප්‍රකාශ III: X {$claims[2]['bound']}ට වඩා අඩුය. "
                ."X = {$x} නම්, මෙම ප්‍රකාශ තුනෙන් සත්‍ය වන්නේ කීයද?";

            $rows[] = $this->truthRow(3, $en, $si, $trueCount, "b4tt1-{$seedBase}-{$i}");
        }

        return $rows;
    }

    /** Level 4-5: X derived from one linear clue, then evaluate 3 claims. @return array<int,array> */
    private function derivedTruthCount(): array
    {
        $rows = [];
        $seedBase = 841501;
        foreach ([4 => 40, 5 => 40] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                mt_srand($seedBase + $level * 1000 + $i);
                $x = random_int(30, 400);
                $add = random_int(5, 60);
                $base = $x - $add;
                $useSubtract = $i % 2 === 0;
                $clueEn = $useSubtract
                    ? "X plus {$add} equals ".($x + $add).'.'
                    : "X minus {$add} equals {$base}.";
                $clueSi = $useSubtract
                    ? "X ට {$add} එකතු කළ විට ".($x + $add)." ලැබේ."
                    : "X ඉන් {$add} අඩු කළ විට {$base} ලැබේ.";

                [$claims, $trueCount] = $this->buildClaims($x, $seedBase + $level * 1000 + $i);

                $en = "{$clueEn} Statement I: X is at least {$claims[0]['bound']}. "
                    ."Statement II: X is more than {$claims[1]['bound']}. "
                    ."Statement III: X is less than {$claims[2]['bound']}. "
                    .'How many of these three statements are true?';
                $si = "{$clueSi} ප්‍රකාශ I: X අවම වශයෙන් {$claims[0]['bound']} වේ. "
                    ."ප්‍රකාශ II: X {$claims[1]['bound']}ට වඩා වැඩිය. "
                    ."ප්‍රකාශ III: X {$claims[2]['bound']}ට වඩා අඩුය. "
                    .'මෙම ප්‍රකාශ තුනෙන් සත්‍ය වන්නේ කීයද?';

                $rows[] = $this->truthRow($level, $en, $si, $trueCount, "b4tt2-{$seedBase}-{$level}-{$i}");
            }
        }

        return $rows;
    }

    /**
     * Builds 3 independent threshold claims about $x (at_least / more_than /
     * less_than, each with a randomly offset bound) and returns both the
     * claim bounds and the real count of true claims - computed by direct
     * comparison, never asserted.
     *
     * @return array{0: array<int,array{bound:int}>, 1: int}
     */
    private function buildClaims(int $x, int $seed): array
    {
        mt_srand($seed * 7 + 3);
        $offsets = [random_int(-20, 20), random_int(-20, 20), random_int(-20, 20)];
        // No offset can be 0, or a claim's boundary would equal X, which
        // this claim set can't phrase unambiguously.
        foreach ($offsets as &$o) {
            if ($o === 0) {
                $o = 5;
            }
        }
        unset($o);

        $boundAtLeast = $x - $offsets[0];
        $boundMoreThan = $x - $offsets[1];
        $boundLessThan = $x - $offsets[2];

        $trueCount = 0;
        $trueCount += ($x >= $boundAtLeast) ? 1 : 0;
        $trueCount += ($x > $boundMoreThan) ? 1 : 0;
        $trueCount += ($x < $boundLessThan) ? 1 : 0;

        return [[
            ['bound' => $boundAtLeast],
            ['bound' => $boundMoreThan],
            ['bound' => $boundLessThan],
        ], $trueCount];
    }

    private function truthRow(int $level, string $textEn, string $textSi, int $answer, string $seedKey): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $distractors = array_values(array_unique(array_filter([0, 1, 2, 3], fn ($n) => $n !== $answer)));
        mt_srand(crc32($seedKey));
        shuffle($distractors);
        $values = [$answer, ...array_slice($distractors, 0, 3)];
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];
        $labels = array_map('strval', $values);

        return [
            $level, 'mcq_text', $textEn, $textSi,
            $this->options($labels, $labels), $key,
            "Evaluating each threshold statement against the actual value of X gives exactly {$answer} true statement(s).",
            "සෑම ප්‍රකාශයක්ම X හි සැබෑ අගය සමඟ සසඳා බැලීමෙන් සත්‍ය ප්‍රකාශ {$answer}ක් ඇති බව තහවුරු වේ.",
            null,
            ['subcategory' => 'truth_teller_logic', 'solving_time_seconds' => 40 + $level * 14, 'bloom_level' => 'evaluate'],
        ];
    }
}
