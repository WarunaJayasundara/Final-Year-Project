<?php

namespace App\Services\Sessions;

use App\Models\Category;
use App\Models\SessionAnswer;
use Illuminate\Support\Collection;

/**
 * Biases daily-session category allocation toward a student's weakest
 * categories, instead of the flat even split QuestionSamplingService used
 * before. Deliberately NOT applied to the placement test (which needs even
 * coverage across categories to produce an unbiased theta estimate) or
 * practice sessions (single-category by definition - the student already
 * chose what to drill).
 *
 * Weighting is accuracy-inverse: a category the student answers less
 * accurately gets more questions next time, floored so no category is ever
 * starved below half of what an even split would give it - a student
 * should keep seeing their strong categories too, both to confirm mastery
 * and to avoid the session feeling punitive.
 */
class WeakAreaWeightingService
{
    private const MIN_SHARE_OF_EVEN_SPLIT = 0.5;

    /** A category needs at least this many past answers before its accuracy is trusted as a real signal. */
    private const MIN_SAMPLE_SIZE = 5;

    /** @return array<int,int> category_id => question count, summing to exactly $totalQuestions */
    public function allocationFor(int $userId, int $totalQuestions): array
    {
        $categories = Category::orderBy('id')->get();
        $n = $categories->count();

        if ($n === 0) {
            return [];
        }

        $accuracy = $this->categoryAccuracy($userId);
        $evenShare = $totalQuestions / $n;
        $minCount = (int) floor($evenShare * self::MIN_SHARE_OF_EVEN_SPLIT);

        // Weight = 1 - accuracy, clamped so no category ever gets a zero or
        // runaway weight from a fluke 0%/100% streak. Categories with too
        // little history default to a neutral 0.5 (even split) rather than
        // being treated as either strong or weak on no evidence.
        $weights = $categories->mapWithKeys(function (Category $category) use ($accuracy) {
            $acc = $accuracy[$category->id] ?? 0.5;
            $acc = max(0.05, min(0.95, $acc));

            return [$category->id => 1 - $acc];
        });

        $totalWeight = $weights->sum();

        $allocation = $weights->map(fn (float $w) => $totalWeight > 0
            ? max($minCount, (int) round($totalQuestions * $w / $totalWeight))
            : (int) round($evenShare));

        return $this->reconcileToExactTotal($allocation, $weights, $totalQuestions);
    }

    /**
     * Independent per-category rounding can drift the sum away from
     * $totalQuestions by a few items; the difference is applied to the
     * single weakest category (highest weight) since that's where an extra
     * or missing question matters least to the student's experience.
     *
     * @param  Collection<int,int>  $allocation
     * @param  Collection<int,float>  $weights
     * @return array<int,int>
     */
    private function reconcileToExactTotal(Collection $allocation, Collection $weights, int $totalQuestions): array
    {
        $diff = $totalQuestions - $allocation->sum();

        if ($diff !== 0) {
            $weakestId = $weights->sortDesc()->keys()->first();
            $allocation[$weakestId] = max(1, $allocation[$weakestId] + $diff);
        }

        return $allocation->all();
    }

    /** @return array<int,float> category_id => accuracy in [0,1], only for categories with enough history */
    private function categoryAccuracy(int $userId): array
    {
        $rows = SessionAnswer::query()
            ->join('questions', 'questions.id', '=', 'session_answers.question_id')
            ->join('test_sessions', 'test_sessions.id', '=', 'session_answers.test_session_id')
            ->where('test_sessions.user_id', $userId)
            ->whereNotNull('session_answers.answered_at')
            ->selectRaw('questions.category_id as category_id, avg(session_answers.is_correct) as acc, count(*) as n')
            ->groupBy('questions.category_id')
            ->get();

        return $rows->filter(fn ($row) => $row->n >= self::MIN_SAMPLE_SIZE)
            ->mapWithKeys(fn ($row) => [(int) $row->category_id => (float) $row->acc])
            ->all();
    }
}
