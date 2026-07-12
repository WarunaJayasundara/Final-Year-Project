<?php

namespace Database\Seeders\Questions\Bank3;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Blood-relations/kinship reasoning - an archetype confirmed missing from
 * MindRise's existing 6 categories by the Phase-1 PDF analysis (present in
 * nearly every Sri Lankan government-exam-prep source reviewed - see
 * docs/ML_RESEARCH_METHODOLOGY.md's source-document inventory). Subjects are
 * letter labels (P, Q, R, ...), never named individuals, so no Sinhala
 * gender-agreement grammar is needed beyond an explicit "is male" /
 * "is not male" clause. Every template's correct answer is fixed by the
 * template's own logic (verified once, reused across many letter-label
 * instances) - never asserted per-row. Deliberately restricted to direct
 * nuclear-family terms already verified elsewhere in this project's Sinhala
 * corpus (father/mother/brother/sister/grandmother/uncle/aunt/child) - see
 * backend/tools/validate_sinhala.py's review log for why paternal/maternal-
 * specific aunt/uncle distinctions were deliberately not attempted.
 */
class BloodRelationsSeeder extends Seeder
{
    use BuildsQuestions;

    private array $seenTexts = [];

    /** EN => [SI, distractor pool key] for every possible answer this archetype produces. */
    private const KIN_SI = [
        'Father' => 'පියා', 'Mother' => 'මව', 'Brother' => 'සොයුරා', 'Sister' => 'සොයුරිය',
        'Grandmother' => 'ආච්චි', 'Uncle' => 'මාමා', 'Aunt' => 'නැන්දා',
    ];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter(array_merge(
            $this->oneHopRelations(),
            $this->twoHopRelations(),
            $this->siblingRelations(),
        ));

        $this->insertRows('logical_reasoning', $rows, [
            'subcategory' => 'blood_relations',
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'blood_relations'],
            'cognitive_skill' => 'relational-reasoning',
            'source_type' => 'past_paper_inspired',
        ]);
    }

    private function letterPairs(int $arity): array
    {
        $letters = ['P', 'Q', 'R', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $combos = [];
        $this->permute($letters, $arity, [], $combos);

        return $combos;
    }

    private function permute(array $pool, int $arity, array $acc, array &$out): void
    {
        if (count($acc) === $arity) {
            $out[] = $acc;

            return;
        }
        foreach ($pool as $letter) {
            if (! in_array($letter, $acc, true)) {
                $this->permute($pool, $arity, [...$acc, $letter], $out);
            }
        }
    }

    /** Level 1-2: single relationship hop (P is Q's parent). @return array<int,array> */
    private function oneHopRelations(): array
    {
        $combos = $this->letterPairs(2);
        mt_srand(830001);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([1 => 30, 2 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$a, $b] = $combos[$cursor++];
                $isFather = $i % 2 === 0;
                $ansEn = $isFather ? 'Father' : 'Mother';
                $genderClauseEn = $isFather ? 'is male' : 'is not male';
                $genderClauseSi = $isFather ? 'පිරිමි වේ' : 'පිරිමි නොවේ';

                $en = "{$b} is the child of {$a}. {$a} {$genderClauseEn}. What is {$a} to {$b}?";
                $si = "{$b}, {$a} ගේ දරුවා වේ. {$a} {$genderClauseSi}. {$a}, {$b} ට කුමක්ද?";

                $rows[] = $this->kinshipRow($level, $en, $si, $ansEn, "b3br1-{$a}-{$b}-{$i}");
            }
        }

        return $rows;
    }

    /** Level 3-5: two-hop relationship (grandmother / uncle / aunt). @return array<int,array> */
    private function twoHopRelations(): array
    {
        $combos = $this->letterPairs(3);
        mt_srand(830002);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([3 => 30, 4 => 30, 5 => 30] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$p, $q, $r] = $combos[$cursor++];
                $kind = $i % 3;

                if ($kind === 0) {
                    // Grandmother: R is Q's child, Q is P's child, P is not male.
                    $en = "{$r} is the child of {$q}. {$q} is the child of {$p}. {$p} is not male. What is {$p} to {$r}?";
                    $si = "{$r}, {$q} ගේ දරුවා වේ. {$q}, {$p} ගේ දරුවා වේ. {$p} පිරිමි නොවේ. {$p}, {$r} ට කුමක්ද?";
                    $ansEn = 'Grandmother';
                } elseif ($kind === 1) {
                    // Uncle: Q's mother is R, P is R's brother.
                    $en = "{$q}'s mother is {$r}. {$p} is the brother of {$r}. What is {$p} to {$q}?";
                    $si = "{$q} ගේ මව {$r} වේ. {$p}, {$r} ගේ සොයුරා වේ. {$p}, {$q} ට කුමක්ද?";
                    $ansEn = 'Uncle';
                } else {
                    // Aunt: Q's father is R, P is R's sister.
                    $en = "{$q}'s father is {$r}. {$p} is the sister of {$r}. What is {$p} to {$q}?";
                    $si = "{$q} ගේ පියා {$r} වේ. {$p}, {$r} ගේ සොයුරිය වේ. {$p}, {$q} ට කුමක්ද?";
                    $ansEn = 'Aunt';
                }

                $rows[] = $this->kinshipRow($level, $en, $si, $ansEn, "b3br2-{$p}-{$q}-{$r}-{$kind}");
            }
        }

        return $rows;
    }

    /** Level 2-3: shared-parent sibling relation. @return array<int,array> */
    private function siblingRelations(): array
    {
        $combos = $this->letterPairs(3);
        mt_srand(830003);
        shuffle($combos);

        $rows = [];
        $cursor = 0;
        foreach ([2 => 25, 3 => 25] as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$p, $q, $r] = $combos[$cursor++];
                $isBrother = $i % 2 === 0;
                $ansEn = $isBrother ? 'Brother' : 'Sister';
                $genderClauseEn = $isBrother ? 'is male' : 'is not male';
                $genderClauseSi = $isBrother ? 'පිරිමි වේ' : 'පිරිමි නොවේ';

                $en = "{$p} is the child of {$r}. {$q} is also the child of {$r}. {$p} is not {$q}. {$p} {$genderClauseEn}. What is {$p} to {$q}?";
                $si = "{$p}, {$r} ගේ දරුවා වේ. {$q} ද, {$r} ගේ දරුවා වේ. {$p}, {$q} නොවේ. {$p} {$genderClauseSi}. {$p}, {$q} ට කුමක්ද?";

                $rows[] = $this->kinshipRow($level, $en, $si, $ansEn, "b3br3-{$p}-{$q}-{$r}-{$i}");
            }
        }

        return $rows;
    }

    private function kinshipRow(int $level, string $textEn, string $textSi, string $answerEn, string $seedKey): ?array
    {
        if (isset($this->seenTexts[$textEn])) {
            return null;
        }
        $this->seenTexts[$textEn] = true;

        $answerSi = self::KIN_SI[$answerEn];
        $distractorPool = array_keys(array_diff(self::KIN_SI, [$answerEn => $answerSi]));
        mt_srand(crc32($seedKey));
        shuffle($distractorPool);
        $distractorsEn = array_slice($distractorPool, 0, 3);

        $labelsEn = [$answerEn, ...$distractorsEn];
        $labelsSi = array_map(fn ($en) => self::KIN_SI[$en], $labelsEn);
        $order = [0, 1, 2, 3];
        shuffle($order);
        $shuffledEn = array_map(fn ($i) => $labelsEn[$i], $order);
        $shuffledSi = array_map(fn ($i) => $labelsSi[$i], $order);
        $correctKey = ['A', 'B', 'C', 'D'][array_search(0, $order, true)];

        return [
            $level, 'mcq_text', $textEn, $textSi,
            $this->options($shuffledEn, $shuffledSi), $correctKey,
            "The correct relationship is: {$answerEn}.",
            "නිවැරදි සම්බන්ධතාවය: {$answerSi}.",
            min(3, max(1, (int) ceil($level / 2))),
            ['subcategory' => 'blood_relations', 'solving_time_seconds' => 30 + $level * 12, 'bloom_level' => $level >= 4 ? 'analyze' : 'apply'],
        ];
    }
}
