<?php

namespace Database\Seeders\Questions\Bank4;

use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Illuminate\Database\Seeder;

/**
 * Multi-constraint reasoning combining two independent orderings (height
 * rank + age rank across 4 people) - the kind of puzzle Bank3's
 * SeatingArrangementSeeder deliberately avoided due to ambiguity risk.
 * This seeder solves that risk directly: a ground-truth (height, age)
 * permutation pair is generated first, true clues are revealed one at a
 * time, and after each addition all 576 possible permutation-pairs are
 * brute-force checked. A puzzle is only kept once its clue set is
 * satisfied by exactly one pair - non-ambiguous by exhaustive search,
 * not by hand-checking.
 */
class MultiConstraintSeatingSeeder extends Seeder
{
    use BuildsQuestions;

    private const NAMES = ['P', 'Q', 'R', 'S'];

    private array $seenTexts = [];

    /** @var array<int, array<int,int>>|null cached 24 permutations of [0,1,2,3] */
    private ?array $allPerms = null;

    public function run(): void
    {
        $this->seenTexts = Question::where('is_active', true)
            ->pluck('question_text_en')->flip()->map(fn () => true)->all();

        $rows = array_filter($this->puzzles());

        $this->insertRows('logical_reasoning', $rows, [
            'subcategory' => 'multi_constraint_seating',
            'exam_tags' => ['logical_reasoning', 'gov_aptitude', 'multi_constraint_seating'],
            'cognitive_skill' => 'multi-constraint-reasoning',
            'source_type' => 'original',
        ]);
    }

    private function permutationsOf4(): array
    {
        if ($this->allPerms !== null) {
            return $this->allPerms;
        }

        $result = [];
        foreach ($this->permute([0, 1, 2, 3]) as $p) {
            $result[] = $p;
        }

        return $this->allPerms = $result;
    }

    /** @return \Generator<array<int,int>> */
    private function permute(array $items): \Generator
    {
        if (count($items) <= 1) {
            yield $items;

            return;
        }
        foreach ($items as $i => $item) {
            $rest = $items;
            unset($rest[$i]);
            foreach ($this->permute(array_values($rest)) as $p) {
                yield array_merge([$item], $p);
            }
        }
    }

    /** rank index (0=tallest/oldest) of a person in a given permutation. */
    private function rankOf(array $perm, int $person): int
    {
        return array_search($person, $perm, true);
    }

    private function puzzles(): array
    {
        $allPerms = $this->permutationsOf4();
        $rows = [];
        $seedBase = 843001;
        $accepted = 0;
        $attempt = 0;

        while ($accepted < 90 && $attempt < 30000) {
            $attempt++;
            mt_srand($seedBase + $attempt);
            $heightPerm = $allPerms[array_rand($allPerms)];
            $agePerm = $allPerms[array_rand($allPerms)];

            $built = $this->buildUniquePuzzle($heightPerm, $agePerm, $allPerms, $seedBase + $attempt);
            if ($built === null) {
                continue;
            }

            $level = $accepted < 30 ? 3 : ($accepted < 60 ? 4 : 5);
            $row = $this->toRow($level, $built, $seedBase + $attempt);
            if ($row !== null) {
                $rows[] = $row;
                $accepted++;
            }
        }

        return $rows;
    }

    /**
     * Greedily reveals true clues about (heightPerm, agePerm) until exactly
     * one permutation-pair in the full 576-pair space satisfies all of them,
     * then asks about one further true fact NOT among the revealed clues.
     *
     * @return array{clues_en: string[], clues_si: string[], question_en: string, question_si: string, answer: string}|null
     */
    private function buildUniquePuzzle(array $heightPerm, array $agePerm, array $allPerms, int $seed): ?array
    {
        $candidates = $this->candidateClues($heightPerm, $agePerm, $seed);
        if (count($candidates) < 4) {
            return null;
        }
        mt_srand($seed * 11 + 5);
        shuffle($candidates);

        $selected = [];
        $usedFacts = [];

        foreach ($candidates as $clue) {
            $selected[] = $clue;
            $usedFacts[$clue['fact_key']] = true;

            $matches = 0;
            foreach ($allPerms as $hp) {
                foreach ($allPerms as $ap) {
                    if ($this->allCluesHold($selected, $hp, $ap)) {
                        $matches++;
                        if ($matches > 1) {
                            break 2;
                        }
                    }
                }
            }

            if ($matches === 1) {
                // Clue set now uniquely determines the answer - pick a
                // target fact that wasn't already revealed as the question.
                $target = $this->pickUnrevealedTarget($heightPerm, $agePerm, $usedFacts, $seed);
                if ($target === null) {
                    return null;
                }

                return [
                    'clues_en' => array_map(fn ($c) => $c['en'], $selected),
                    'clues_si' => array_map(fn ($c) => $c['si'], $selected),
                    'question_en' => $target['question_en'],
                    'question_si' => $target['question_si'],
                    'answer' => $target['answer'],
                ];
            }

            if (count($selected) >= 10) {
                return null; // gave up reaching uniqueness within the clue budget
            }
        }

        return null;
    }

    private function allCluesHold(array $clues, array $hp, array $ap): bool
    {
        foreach ($clues as $c) {
            if (! ($c['check'])($hp, $ap)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int,array{en:string,si:string,fact_key:string,check:callable}> */
    private function candidateClues(array $heightPerm, array $agePerm, int $seed): array
    {
        $clues = [];
        $names = self::NAMES;

        foreach ([0, 1, 2, 3] as $i) {
            foreach ([0, 1, 2, 3] as $j) {
                if ($i === $j) {
                    continue;
                }
                if ($this->rankOf($heightPerm, $i) < $this->rankOf($heightPerm, $j)) {
                    $clues[] = [
                        'en' => "{$names[$i]} is taller than {$names[$j]}.",
                        'si' => "{$names[$i]} {$names[$j]}ට වඩා උසයි.",
                        'fact_key' => "h_gt_{$i}_{$j}",
                        'check' => fn ($hp, $ap) => $this->rankOf($hp, $i) < $this->rankOf($hp, $j),
                    ];
                }
                if ($this->rankOf($agePerm, $i) < $this->rankOf($agePerm, $j)) {
                    $clues[] = [
                        'en' => "{$names[$i]} is older than {$names[$j]}.",
                        'si' => "{$names[$i]} {$names[$j]}ට වඩා වයසින් වැඩිය.",
                        'fact_key' => "a_gt_{$i}_{$j}",
                        'check' => fn ($hp, $ap) => $this->rankOf($ap, $i) < $this->rankOf($ap, $j),
                    ];
                }
            }
            if ($this->rankOf($heightPerm, $i) === 0) {
                $clues[] = [
                    'en' => "{$names[$i]} is the tallest.",
                    'si' => "{$names[$i]} වඩාත්ම උසයි.",
                    'fact_key' => "h_top_{$i}",
                    'check' => fn ($hp, $ap) => $this->rankOf($hp, $i) === 0,
                ];
            }
            if ($this->rankOf($heightPerm, $i) === 3) {
                $clues[] = [
                    'en' => "{$names[$i]} is the shortest.",
                    'si' => "{$names[$i]} වඩාත්ම කෙටිය.",
                    'fact_key' => "h_bot_{$i}",
                    'check' => fn ($hp, $ap) => $this->rankOf($hp, $i) === 3,
                ];
            }
            if ($this->rankOf($agePerm, $i) === 0) {
                $clues[] = [
                    'en' => "{$names[$i]} is the oldest.",
                    'si' => "{$names[$i]} වඩාත්ම වයස්ගතය.",
                    'fact_key' => "a_top_{$i}",
                    'check' => fn ($hp, $ap) => $this->rankOf($ap, $i) === 0,
                ];
            }
            if ($this->rankOf($agePerm, $i) === 3) {
                $clues[] = [
                    'en' => "{$names[$i]} is the youngest.",
                    'si' => "{$names[$i]} වඩාත්ම බාලය.",
                    'fact_key' => "a_bot_{$i}",
                    'check' => fn ($hp, $ap) => $this->rankOf($ap, $i) === 3,
                ];
            }
        }

        mt_srand($seed * 13 + 7);
        shuffle($clues);

        return $clues;
    }

    /** @return array{question_en:string,question_si:string,answer:string}|null */
    private function pickUnrevealedTarget(array $heightPerm, array $agePerm, array $usedFacts, int $seed): ?array
    {
        $names = self::NAMES;
        $options = [];

        foreach ([0, 1, 2, 3] as $i) {
            $hRank = $this->rankOf($heightPerm, $i) + 1;
            $aRank = $this->rankOf($agePerm, $i) + 1;
            if (! isset($usedFacts["h_rank_{$i}"])) {
                $options[] = [
                    'question_en' => "What is {$names[$i]}'s rank in height, counting 1 for the tallest?",
                    'question_si' => "{$names[$i]}ගේ උස අනුව ස්ථානය කුමක්ද (වඩාත්ම උසින් 1 ලෙස ගණන් කරන්න)?",
                    'answer' => (string) $hRank,
                ];
            }
            if (! isset($usedFacts["a_rank_{$i}"])) {
                $options[] = [
                    'question_en' => "What is {$names[$i]}'s rank in age, counting 1 for the oldest?",
                    'question_si' => "{$names[$i]}ගේ වයස අනුව ස්ථානය කුමක්ද (වඩාත්ම වයස්ගතයා 1 ලෙස ගණන් කරන්න)?",
                    'answer' => (string) $aRank,
                ];
            }
        }

        if (empty($options)) {
            return null;
        }
        mt_srand($seed * 17 + 11);

        return $options[array_rand($options)];
    }

    private function toRow(int $level, array $built, int $seed): ?array
    {
        $clueList = implode(' ', array_map(fn ($i, $c) => 'Clue '.($i + 1).": {$c}", array_keys($built['clues_en']), $built['clues_en']));
        $clueListSi = implode(' ', array_map(fn ($i, $c) => 'ඉඟිය '.($i + 1).": {$c}", array_keys($built['clues_si']), $built['clues_si']));

        $en = "Four people P, Q, R, S each have a distinct height and a distinct age. {$clueList} {$built['question_en']}";
        $si = "P, Q, R, S යන පුද්ගලයන් 4 දෙනාට එකිනෙකට වෙනස් උසක් සහ වයසක් ඇත. {$clueListSi} {$built['question_si']}";

        if (isset($this->seenTexts[$en])) {
            return null;
        }
        $this->seenTexts[$en] = true;

        $answer = (int) $built['answer'];
        $distractors = array_values(array_unique(array_filter([1, 2, 3, 4], fn ($n) => $n !== $answer)));
        mt_srand($seed * 19 + 23);
        shuffle($distractors);
        $values = [$answer, ...array_slice($distractors, 0, 3)];
        shuffle($values);
        $key = ['A', 'B', 'C', 'D'][array_search($answer, $values, true)];
        $labels = array_map('strval', $values);

        return [
            $level, 'mcq_text', $en, $si,
            $this->options($labels, $labels), $key,
            'Combining the height-ranking and age-ranking clues (verified against every possible arrangement) uniquely determines the answer.',
            'උස සහ වයස සම්බන්ධ ඉඟි සියල්ල එකට යොදාගැනීමෙන් (හැකි සියලුම සැකැස්ම් සමඟ පරීක්ෂා කර) පිළිතුර අනන්‍ය ලෙස තහවුරු වේ.',
            null,
            ['subcategory' => 'multi_constraint_seating', 'solving_time_seconds' => 60 + $level * 16, 'bloom_level' => 'analyze'],
        ];
    }
}
