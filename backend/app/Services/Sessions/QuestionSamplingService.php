<?php

namespace App\Services\Sessions;

use App\Models\Category;
use App\Models\IqLevel;
use App\Models\Question;
use App\Models\SessionAnswer;
use Illuminate\Support\Collection;

class QuestionSamplingService
{
    /**
     * Placement test: evenly sampled across all categories, drawn from levels 2-4 only.
     * Excludes questions the user has already been shown in a previous session
     * (falls back to allowing repeats only if a category's pool is exhausted).
     */
    public function sampleForPlacement(int $userId, int $totalQuestions = 30): Collection
    {
        $categories = Category::all();
        $levelNumbers = [2, 3, 4];
        $levelIds = IqLevel::whereIn('level_number', $levelNumbers)->pluck('id');
        $excludeIds = $this->seenQuestionIds($userId);

        $perCategory = (int) ceil($totalQuestions / max($categories->count(), 1));

        $questions = collect();

        foreach ($categories as $category) {
            $fresh = Question::where('category_id', $category->id)
                ->whereIn('level_id', $levelIds)
                ->where('is_active', true)
                ->whereNotIn('id', $excludeIds)
                ->inRandomOrder()
                ->limit($perCategory)
                ->get();

            if ($fresh->count() < $perCategory) {
                $remaining = $perCategory - $fresh->count();
                $repeats = Question::where('category_id', $category->id)
                    ->whereIn('level_id', $levelIds)
                    ->where('is_active', true)
                    ->whereNotIn('id', $fresh->pluck('id'))
                    ->inRandomOrder()
                    ->limit($remaining)
                    ->get();
                $fresh = $fresh->merge($repeats);
            }

            $questions = $questions->merge($fresh);
        }

        return $questions->shuffle()->take($totalQuestions)->values();
    }

    /**
     * Daily session: sampled across all categories at the user's current level,
     * falling back to level +/- 1, then to repeats, if a category/level cell is
     * short. $categoryAllocation (from WeakAreaWeightingService) overrides the
     * default even split per category when provided - see that service's
     * docblock for why placement/practice never pass one in.
     *
     * @param  array<int,int>|null  $categoryAllocation  category_id => question count
     */
    public function sampleForDaily(int $userId, IqLevel $level, int $totalQuestions = 30, ?array $categoryAllocation = null): Collection
    {
        $categories = Category::all();
        $evenShare = (int) ceil($totalQuestions / max($categories->count(), 1));
        $excludeIds = $this->seenQuestionIds($userId);

        $questions = collect();

        foreach ($categories as $category) {
            $count = $categoryAllocation[$category->id] ?? $evenShare;
            $questions = $questions->merge(
                $this->pullForCategoryAtLevel($category->id, $level, $count, $excludeIds)
            );
        }

        return $questions->shuffle()->take($totalQuestions)->values();
    }

    /**
     * Practice session: all questions from a single chosen category, at the user's current level.
     */
    public function sampleForPractice(int $userId, Category $category, IqLevel $level, int $totalQuestions = 20): Collection
    {
        $excludeIds = $this->seenQuestionIds($userId);

        return $this->pullForCategoryAtLevel($category->id, $level, $totalQuestions, $excludeIds)->shuffle()->values();
    }

    /**
     * Mock exam: weighted-but-bounded category representation - weak
     * categories are over-sampled relative to their measured mastery, but
     * every requested category still gets a guaranteed minimum share so the
     * mock stays realistic-coverage (brief's own example: a student weak in
     * Numerical/Spatial gets more of those, while Logical - already strong -
     * still appears, just less often).
     *
     * @param  array<int,float>  $categoryAccuracy  category_id => accuracy_percent (0-100)
     * @param  \Illuminate\Support\Collection<int,int>  $categoryIds  categories to include (full syllabus or a selected subset)
     * @param  bool  $adaptiveDifficulty  when true, nudges each category's level up/down by the user's mastery in that category instead of using a single flat level for every category
     */
    public function sampleForMockExam(
        int $userId,
        IqLevel $baseLevel,
        int $totalQuestions,
        Collection $categoryIds,
        array $categoryAccuracy,
        bool $adaptiveDifficulty = false
    ): Collection {
        if ($categoryIds->isEmpty()) {
            return collect();
        }

        $excludeIds = $this->seenQuestionIds($userId);
        $allocation = $this->weightedAllocation($totalQuestions, $categoryIds, $categoryAccuracy);

        $questions = collect();
        foreach ($allocation as $categoryId => $count) {
            if ($count <= 0) {
                continue;
            }

            $level = $adaptiveDifficulty
                ? $this->adaptiveLevelFor($baseLevel, $categoryAccuracy[$categoryId] ?? 50.0)
                : $baseLevel;

            $questions = $questions->merge(
                $this->pullForCategoryAtLevel($categoryId, $level, $count, $excludeIds->merge($questions->pluck('id')))
            );
        }

        return $questions->shuffle()->values();
    }

    /**
     * Baseline 50% of the exam split evenly across every requested category
     * (guaranteed minimum coverage); the remaining 50% distributed by
     * inverse-mastery weight (weaker categories get more of it). Weights are
     * floored at 5 so an already-mastered category never drops to literally
     * zero extra share. Rounding remainders are reconciled onto the
     * lowest-mastery category so the total always matches exactly.
     *
     * @return array<int,int> category_id => question count
     */
    private function weightedAllocation(int $totalQuestions, Collection $categoryIds, array $categoryAccuracy): array
    {
        $n = $categoryIds->count();
        $baseShare = intdiv((int) round($totalQuestions * 0.5), $n);
        $remaining = $totalQuestions - ($baseShare * $n);

        $weights = $categoryIds->mapWithKeys(fn ($id) => [$id => max(5.0, 100.0 - ($categoryAccuracy[$id] ?? 50.0))]);
        $weightSum = $weights->sum();

        $allocation = [];
        foreach ($categoryIds as $categoryId) {
            $extra = $weightSum > 0 ? (int) round($remaining * $weights[$categoryId] / $weightSum) : 0;
            $allocation[$categoryId] = $baseShare + $extra;
        }

        $diff = $totalQuestions - array_sum($allocation);
        if ($diff !== 0) {
            $weakestCategoryId = $categoryIds->sortByDesc(fn ($id) => $weights[$id])->first();
            $allocation[$weakestCategoryId] = max(0, $allocation[$weakestCategoryId] + $diff);
        }

        return $allocation;
    }

    /**
     * +/-1 level nudge per category based on that category's own mastery,
     * documented heuristic (not a second ability estimate) - clamped to the
     * platform's 5 authored levels.
     */
    private function adaptiveLevelFor(IqLevel $baseLevel, float $categoryAccuracy): IqLevel
    {
        $delta = $categoryAccuracy >= 70 ? 1 : ($categoryAccuracy <= 40 ? -1 : 0);
        $targetNumber = max(1, min(5, $baseLevel->level_number + $delta));

        return IqLevel::where('level_number', $targetNumber)->first() ?? $baseLevel;
    }

    /**
     * All question ids ever presented to this user across any past session -
     * used to keep daily/placement/practice sessions from re-serving the same
     * question until the category/level pool is genuinely exhausted.
     */
    private function seenQuestionIds(int $userId): Collection
    {
        return SessionAnswer::whereHas('session', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->pluck('question_id')->unique()->values();
    }

    private function pullForCategoryAtLevel(int $categoryId, IqLevel $level, int $limit, Collection $excludeIds): Collection
    {
        $questions = Question::where('category_id', $categoryId)
            ->where('level_id', $level->id)
            ->where('is_active', true)
            ->whereNotIn('id', $excludeIds)
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        if ($questions->count() >= $limit) {
            return $questions;
        }

        // Fall back to adjacent levels (still excluding seen questions) if this
        // category/level cell is short.
        $adjacentLevelIds = IqLevel::whereIn('level_number', [
            $level->level_number - 1,
            $level->level_number + 1,
        ])->pluck('id');

        $remaining = $limit - $questions->count();

        $fallback = Question::where('category_id', $categoryId)
            ->whereIn('level_id', $adjacentLevelIds)
            ->where('is_active', true)
            ->whereNotIn('id', $excludeIds->merge($questions->pluck('id')))
            ->inRandomOrder()
            ->limit($remaining)
            ->get();

        $combined = $questions->merge($fallback);

        if ($combined->count() >= $limit) {
            return $combined;
        }

        // The unseen pool is genuinely exhausted for this category (the user has
        // answered nearly everything available) - only now allow repeats, picked
        // at random from across level, level-1 and level+1.
        $stillNeeded = $limit - $combined->count();
        $allLevelIds = IqLevel::whereIn('level_number', [
            $level->level_number - 1,
            $level->level_number,
            $level->level_number + 1,
        ])->pluck('id');

        $repeats = Question::where('category_id', $categoryId)
            ->whereIn('level_id', $allLevelIds)
            ->where('is_active', true)
            ->whereNotIn('id', $combined->pluck('id'))
            ->inRandomOrder()
            ->limit($stillNeeded)
            ->get();

        return $combined->merge($repeats);
    }
}
