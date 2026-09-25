<?php

namespace App\Services\Sessions;

use App\Models\Category;
use App\Models\SessionAnswer;
use Illuminate\Support\Collection;

/** Biases daily-session category allocation toward a student's weakest categories. */
class WeakAreaWeightingService
{
    private const MIN_SHARE_OF_EVEN_SPLIT = 0.5;

    /** A category needs at least this many past answers before its accuracy is trusted as a real signal. */
    private const MIN_SAMPLE_SIZE = 5;

    /** Exam-approaching training mode (brief §14): as the exam gets closer, sharpen. */
    private const PHASE_WEIGHT_EXPONENT = [
        'foundation' => 1.0,
        'practice' => 1.0,
        'intensive' => 1.3,
        'final_revision' => 1.6,
        'exam_day' => 1.0,
    ];

    /** @return array<int,int> category_id => question count, summing to exactly $totalQuestions */
    public function allocationFor(int $userId, int $totalQuestions, ?string $phase = null): array
    {
        $categories = Category::orderBy('id')->get();
        $n = $categories->count();

        if ($n === 0) {
            return [];
        }

        $accuracy = $this->categoryAccuracy($userId);
        $evenShare = $totalQuestions / $n;
        $minCount = (int) floor($evenShare * self::MIN_SHARE_OF_EVEN_SPLIT);
        $exponent = self::PHASE_WEIGHT_EXPONENT[$phase] ?? 1.0;

        // Weight = (1 - accuracy) ^ exponent, clamped so no category ever gets a zero or runaway weight from a fluke 0%/100% streak.
        $weights = $categories->mapWithKeys(function (Category $category) use ($accuracy, $exponent) {
            $acc = $accuracy[$category->id] ?? 0.5;
            $acc = max(0.05, min(0.95, $acc));

            return [$category->id => (1 - $acc) ** $exponent];
        });

        $totalWeight = $weights->sum();

        $allocation = $weights->map(fn (float $w) => $totalWeight > 0
            ? max($minCount, (int) round($totalQuestions * $w / $totalWeight))
            : (int) round($evenShare));

        return $this->reconcileToExactTotal($allocation, $weights, $totalQuestions);
    }

    /**
     * Independent per-category rounding can drift the sum away from $totalQuestions by a few items.
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
