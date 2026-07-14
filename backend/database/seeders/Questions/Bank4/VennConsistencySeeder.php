<?php

namespace Database\Seeders\Questions\Bank4;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Venn-diagram consistency reasoning: "which relationship description is
 * NOT contradicted by the given premises." Built via concrete set
 * construction rather than general categorical-syllogism inference rules,
 * which have edge cases that are easy to get subtly wrong: three
 * categories are modelled as real PHP sets over a small universe, A and C
 * are each built to satisfy a chosen relation (subset/disjoint/overlap)
 * against the shared middle category B, and the true relation between A
 * and C is computed directly from set operations, never asserted. The 4
 * answer options are mutually exclusive and jointly exhaustive, so
 * exactly one is correct for the constructed sets.
 */
class VennConsistencySeeder extends Seeder
{
    private const UNIVERSE_SIZE = 12;

    use BuildsQuestions;

    private array $seenTexts = [];

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter($this->puzzles());

        $this->insertRows('logical_reasoning', $rows, [
            'subcategory' => 'venn_consistency',
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'venn_consistency'],
            'cognitive_skill' => 'set-relational-reasoning',
            'source_type' => 'original',
        ]);
    }

    private const RELATION_KINDS = ['subset', 'disjoint', 'overlap'];

    /** category label pairs (EN, SI) - generic nouns, no risk of ambiguous real-world overlap claims. */
    private const CATEGORY_TRIPLES = [
        ['X', 'Y', 'Z'],
        ['P', 'Q', 'R'],
        ['A', 'B', 'C'],
        ['M', 'N', 'O'],
    ];

    private function puzzles(): array
    {
        $rows = [];
        $seedBase = 844001;
        $accepted = 0;
        $attempt = 0;

        while ($accepted < 100 && $attempt < 12000) {
            $attempt++;
            mt_srand($seedBase + $attempt);
            $triple = self::CATEGORY_TRIPLES[array_rand(self::CATEGORY_TRIPLES)];
            $kind1 = self::RELATION_KINDS[array_rand(self::RELATION_KINDS)];
            $kind2 = self::RELATION_KINDS[array_rand(self::RELATION_KINDS)];

            $b = $this->randomSubset(range(1, self::UNIVERSE_SIZE), $seedBase + $attempt);
            $a = $this->buildRelated($b, $kind1, $seedBase + $attempt + 101);
            $c = $this->buildRelated($b, $kind2, $seedBase + $attempt + 202);
            if ($a === null || $c === null || empty($a) || empty($c)) {
                continue;
            }

            $level = $accepted < 34 ? 3 : ($accepted < 67 ? 4 : 5);
            $row = $this->toRow($level, $triple, $kind1, $kind2, $a, $c, $seedBase + $attempt);
            if ($row !== null) {
                $rows[] = $row;
                $accepted++;
            }
        }

        return $rows;
    }

    private function randomSubset(array $universe, int $seed): array
    {
        mt_srand($seed);
        $size = random_int((int) (count($universe) * 0.35), (int) (count($universe) * 0.6));
        $copy = $universe;
        shuffle($copy);

        return array_slice($copy, 0, max(2, $size));
    }

    /** Builds a set related to $b by $kind (subset of b / disjoint from b / partial overlap with b). */
    private function buildRelated(array $b, string $kind, int $seed): ?array
    {
        mt_srand($seed);
        $universe = range(1, self::UNIVERSE_SIZE);
        $outsideB = array_values(array_diff($universe, $b));

        return match ($kind) {
            'subset' => $this->randomSubset($b, $seed),
            'disjoint' => empty($outsideB) ? null : $this->randomSubset($outsideB, $seed),
            default => $this->buildOverlap($b, $outsideB, $seed),
        };
    }

    private function buildOverlap(array $b, array $outsideB, int $seed): ?array
    {
        if (count($b) < 2 || empty($outsideB)) {
            return null;
        }
        mt_srand($seed);
        $bCopy = $b;
        shuffle($bCopy);
        $insidePart = array_slice($bCopy, 0, max(1, intdiv(count($bCopy), 2)));
        $outCopy = $outsideB;
        shuffle($outCopy);
        $outsidePart = array_slice($outCopy, 0, max(1, intdiv(count($outCopy), 2)));

        $result = array_merge($insidePart, $outsidePart);

        // Reject if it's not a genuine partial overlap with b.
        if (empty(array_diff($result, $b)) || empty(array_intersect($result, $b))) {
            return null;
        }

        return $result;
    }

    private function toRow(int $level, array $triple, string $kind1, string $kind2, array $a, array $c, int $seed): ?array
    {
        [$catA, $catB, $catC] = $triple;

        $p1 = $this->describeRelation($kind1, $catA, $catB);
        $p2 = $this->describeRelation($kind2, $catB, $catC);

        [$correctDesc, $optionsPool] = $this->relationOptions($a, $c, $catA, $catC);

        $en = "All members of {$catA}, {$catB} and {$catC} come from the same overall group. {$p1['en']} {$p2['en']} "
            ."Which of the following about {$catA} and {$catC} is NOT contradicted by the above?";
        $si = "{$catA}, {$catB} සහ {$catC} යන සියල්ලෝම එකම සමස්ත කාණ්ඩයෙන් වේ. {$p1['si']} {$p2['si']} "
            ."ඉහත කරුණු අනුව {$catA} සහ {$catC} පිළිබඳ පහත සඳහන් ඒවායින් නොගැලපෙන්නේ නැත්තේ (contradict නොවන්නේ) කුමන ප්‍රකාශයද?";

        if (isset($this->seenTexts[$en])) {
            return null;
        }
        $this->seenTexts[$en] = true;

        mt_srand($seed * 31 + 41);
        $distractors = array_values(array_filter($optionsPool, fn ($o) => $o['key'] !== $correctDesc['key']));
        shuffle($distractors);
        $chosen = [$correctDesc, ...array_slice($distractors, 0, 3)];
        shuffle($chosen);
        $key = ['A', 'B', 'C', 'D'][array_search($correctDesc['key'], array_column($chosen, 'key'), true)];
        $labelsEn = array_column($chosen, 'en');
        $labelsSi = array_column($chosen, 'si');

        return [
            $level, 'mcq_text', $en, $si,
            $this->options($labelsEn, $labelsSi), $key,
            "Modelling {$catA}, {$catB} and {$catC} as concrete groups satisfying both given relationships and directly checking the overlap between {$catA} and {$catC} confirms: {$correctDesc['en']}",
            "{$catA}, {$catB}, {$catC} යන කාණ්ඩ ලබා දී ඇති සම්බන්ධතා දෙකම සපුරාලන පරිදි සකසා {$catA} සහ {$catC} අතර සම්බන්ධතාව සෘජුවම පරීක්ෂා කිරීමෙන් තහවුරු වන්නේ: {$correctDesc['si']}",
            null,
            ['subcategory' => 'venn_consistency', 'solving_time_seconds' => 55 + $level * 15, 'bloom_level' => 'analyze'],
        ];
    }

    private function describeRelation(string $kind, string $x, string $y): array
    {
        return match ($kind) {
            'subset' => [
                'en' => "All {$x} are {$y}.",
                'si' => "සියලුම {$x}, {$y} වේ.",
            ],
            'disjoint' => [
                'en' => "No {$x} are {$y}.",
                'si' => "එකදු {$x}ක්වත් {$y} නොවේ.",
            ],
            default => [
                'en' => "Some {$x} are {$y}.",
                'si' => "සමහර {$x}, {$y} වේ.",
            ],
        };
    }

    /** @return array{0: array{key:string,en:string,si:string}, 1: array<int,array{key:string,en:string,si:string}>} */
    private function relationOptions(array $a, array $c, string $catA, string $catC): array
    {
        $pool = [
            ['key' => 'disjoint', 'en' => "No {$catA} are {$catC}.", 'si' => "එකදු {$catA}ක්වත් {$catC} නොවේ."],
            ['key' => 'equal', 'en' => "All {$catA} are {$catC}, and all {$catC} are {$catA}.", 'si' => "සියලුම {$catA}, {$catC} වන අතර, සියලුම {$catC}ද {$catA} වේ."],
            ['key' => 'a_subset_c', 'en' => "All {$catA} are {$catC}, but not all {$catC} are {$catA}.", 'si' => "සියලුම {$catA}, {$catC} වේ, නමුත් සියලුම {$catC} {$catA} නොවේ."],
            ['key' => 'c_subset_a', 'en' => "All {$catC} are {$catA}, but not all {$catA} are {$catC}.", 'si' => "සියලුම {$catC}, {$catA} වේ, නමුත් සියලුම {$catA} {$catC} නොවේ."],
            ['key' => 'overlap', 'en' => "Some {$catA} are {$catC}, but some {$catA} are not {$catC} and some {$catC} are not {$catA}.", 'si' => "සමහර {$catA}, {$catC} වේ, නමුත් සමහර {$catA}, {$catC} නොවේ. සමහර {$catC}ද {$catA} නොවේ."],
        ];

        $intersect = array_intersect($a, $c);
        $aInC = empty(array_diff($a, $c));
        $cInA = empty(array_diff($c, $a));

        $correctKey = match (true) {
            $aInC && $cInA => 'equal',
            $aInC => 'a_subset_c',
            $cInA => 'c_subset_a',
            empty($intersect) => 'disjoint',
            default => 'overlap',
        };

        $correct = current(array_filter($pool, fn ($o) => $o['key'] === $correctKey));

        return [$correct, $pool];
    }
}
