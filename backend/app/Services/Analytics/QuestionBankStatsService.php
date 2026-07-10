<?php

namespace App\Services\Analytics;

use App\Models\Question;

/**
 * Read-only bank-composition statistics for the admin question-bank QA
 * dashboard (Phase 6). Purely aggregate queries over the active bank - no
 * caching, since this is an admin-only, low-traffic view.
 */
class QuestionBankStatsService
{
    public function overview(): array
    {
        $active = Question::where('is_active', true);

        return [
            'total_active' => (clone $active)->count(),
            'total_retired' => Question::where('is_active', false)->count(),
            'by_type' => (clone $active)
                ->selectRaw('question_type, count(*) as n')
                ->groupBy('question_type')
                ->pluck('n', 'question_type'),
            'by_category' => $this->byCategory(),
            'by_subcategory' => $this->bySubcategory(),
            'by_bloom_level' => (clone $active)
                ->whereNotNull('bloom_level')
                ->selectRaw('bloom_level, count(*) as n')
                ->groupBy('bloom_level')
                ->orderByDesc('n')
                ->pluck('n', 'bloom_level'),
            'untagged_count' => (clone $active)->whereNull('exam_tags')->count(),
        ];
    }

    private function byCategory(): array
    {
        return Question::where('is_active', true)
            ->join('categories', 'categories.id', '=', 'questions.category_id')
            ->join('iq_levels', 'iq_levels.id', '=', 'questions.level_id')
            ->selectRaw('categories.code as category, categories.name_en, iq_levels.level_number, count(*) as n')
            ->groupBy('categories.code', 'categories.name_en', 'iq_levels.level_number')
            ->orderBy('categories.code')
            ->orderBy('iq_levels.level_number')
            ->get()
            ->groupBy('category')
            ->map(function ($rows) {
                return [
                    'name_en' => $rows->first()->name_en,
                    'total' => $rows->sum('n'),
                    'by_level' => $rows->pluck('n', 'level_number'),
                ];
            })
            ->all();
    }

    private function bySubcategory(): array
    {
        return Question::where('is_active', true)
            ->whereNotNull('subcategory')
            ->join('categories', 'categories.id', '=', 'questions.category_id')
            ->selectRaw('categories.code as category, questions.subcategory, count(*) as n')
            ->groupBy('categories.code', 'questions.subcategory')
            ->orderBy('categories.code')
            ->orderByDesc('n')
            ->get()
            ->groupBy('category')
            ->map(fn ($rows) => $rows->pluck('n', 'subcategory'))
            ->all();
    }
}
