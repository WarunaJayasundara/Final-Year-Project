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
     * Daily session: evenly sampled across all categories at the user's current level,
     * falling back to level +/- 1, then to repeats, if a category/level cell is short.
     */
    public function sampleForDaily(int $userId, IqLevel $level, int $totalQuestions = 30): Collection
    {
        $categories = Category::all();
        $perCategory = (int) ceil($totalQuestions / max($categories->count(), 1));
        $excludeIds = $this->seenQuestionIds($userId);

        $questions = collect();

        foreach ($categories as $category) {
            $questions = $questions->merge(
                $this->pullForCategoryAtLevel($category->id, $level, $perCategory, $excludeIds)
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
