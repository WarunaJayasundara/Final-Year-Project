<?php

namespace App\Services\Irt;

use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * Picks the next item for the adaptively-delivered placement test: the
 * candidate (within a target category, for content balancing across the 5
 * diagnostic categories - see Kingsbury & Zara (1989) on constrained CAT)
 * whose difficulty is closest to the student's current ability estimate,
 * which is equivalent to maximizing Fisher information at theta for the
 * Rasch model (information peaks exactly where difficulty = ability).
 */
class AdaptiveItemSelectionService
{
    /** @return int[] category ids in a fixed round-robin content-balancing order */
    public function categoryRotation(): array
    {
        return Category::orderBy('id')->pluck('id')->all();
    }

    public function selectNext(int $categoryId, float $theta, Collection $excludeIds): ?Question
    {
        $candidates = Question::where('category_id', $categoryId)
            ->where('is_active', true)
            ->whereNotIn('id', $excludeIds)
            ->with('level')
            ->get();

        return $this->closestToAbility($candidates, $theta);
    }

    /**
     * Fallback used when a category's unseen pool is exhausted: picks the closest
     * match from any category instead of failing the test outright.
     */
    public function selectNextAnyCategory(float $theta, Collection $excludeIds): ?Question
    {
        $candidates = Question::where('is_active', true)
            ->whereNotIn('id', $excludeIds)
            ->with('level')
            ->get();

        return $this->closestToAbility($candidates, $theta);
    }

    /** @param \Illuminate\Support\Collection<int,Question> $candidates */
    private function closestToAbility(Collection $candidates, float $theta): ?Question
    {
        $best = null;
        $bestDistance = INF;

        foreach ($candidates as $candidate) {
            $difficulty = RaschCalibrationService::difficultyFor($candidate);
            $distance = abs($difficulty - $theta);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $best;
    }
}
