<?php

namespace App\Services\Study;

use App\Models\Category;
use App\Models\User;
use App\Models\UserProgressSnapshot;
use Illuminate\Support\Collection;

/**
 * Rule-based adaptive study planner - deliberately not a machine-learning
 * model. A well-specified rules engine is the right tool here: the inputs
 * (days remaining, weak categories, stated daily availability) and the
 * desired behaviour ("get harder and more mock-test-heavy as the exam
 * approaches") are fully known upfront, so a transparent, auditable rule
 * set is both simpler and more defensible than training a model to
 * approximate the same thing.
 */
class StudyPlanService
{
    private const PHASE_BOUNDARIES = [
        ['phase' => 'foundation', 'label_en' => 'Foundation Phase', 'label_si' => 'මූලික අවධිය', 'min_days_before_end' => 60],
        ['phase' => 'practice', 'label_en' => 'Practice Phase', 'label_si' => 'අභ්‍යාස අවධිය', 'min_days_before_end' => 30],
        ['phase' => 'intensive', 'label_en' => 'Intensive Phase', 'label_si' => 'දැඩි අවධිය', 'min_days_before_end' => 14],
        ['phase' => 'final_revision', 'label_en' => 'Final Revision Phase', 'label_si' => 'අවසන් සමාලෝචන අවධිය', 'min_days_before_end' => 0],
    ];

    /** Base recommended daily practice questions, scaled by phase + exam difficulty. */
    private const BASE_DAILY_QUESTIONS = 15;

    private const PHASE_INTENSITY = [
        'foundation' => 1.0,
        'practice' => 1.15,
        'intensive' => 1.35,
        'final_revision' => 1.6,
        'exam_day' => 1.0,
    ];

    private const PHASE_WEEKLY_MOCK_TESTS = [
        'foundation' => 0,
        'practice' => 1,
        'intensive' => 2,
        'final_revision' => 4,
        'exam_day' => 0,
    ];

    /** Fraction of the daily study-hours budget spent on each activity, per phase. */
    private const PHASE_DAILY_ALLOCATION = [
        'foundation' => ['weak_1' => 0.35, 'weak_2' => 0.25, 'mock' => 0.15, 'game' => 0.15, 'strong_review' => 0.10],
        'practice' => ['weak_1' => 0.35, 'weak_2' => 0.25, 'mock' => 0.25, 'game' => 0.15, 'strong_review' => 0.00],
        'intensive' => ['weak_1' => 0.30, 'weak_2' => 0.20, 'mock' => 0.35, 'game' => 0.15, 'strong_review' => 0.00],
        'final_revision' => ['weak_1' => 0.25, 'weak_2' => 0.15, 'mock' => 0.50, 'game' => 0.10, 'strong_review' => 0.00],
        'exam_day' => ['weak_1' => 0.00, 'weak_2' => 0.00, 'mock' => 0.00, 'game' => 0.00, 'strong_review' => 0.00],
    ];

    /** 7-day rotation pattern per phase; 'weak_1'/'weak_2' resolve to the student's weakest categories. */
    private const PHASE_WEEKLY_PATTERN = [
        'foundation' => ['weak_1', 'weak_2', 'weak_1', 'weak_2', 'mixed', 'mock', 'rest'],
        'practice' => ['weak_1', 'weak_2', 'mixed', 'weak_1', 'weak_2', 'mock', 'rest'],
        'intensive' => ['weak_1', 'mock', 'weak_2', 'mixed', 'weak_1', 'mock', 'rest'],
        'final_revision' => ['mock', 'weak_1', 'mock', 'weak_2', 'mock', 'mock', 'rest_light'],
        'exam_day' => ['rest_light', 'rest_light', 'rest_light', 'rest_light', 'rest_light', 'rest_light', 'rest_light'],
    ];

    public function generate(User $user): array
    {
        $examProfile = $user->examProfile;
        $daysRemaining = $examProfile?->daysRemaining();
        $difficultyWeight = $examProfile?->difficultyWeight() ?? 1.0;
        $dailyHours = $examProfile ? (float) $examProfile->daily_study_hours_target : 1.5;

        $categoryScores = $this->categoryScores($user);
        $weakCategories = $categoryScores->sortBy('accuracy_percent')->values();
        $strongestCategory = $categoryScores->sortByDesc('accuracy_percent')->first();

        $phase = $this->determinePhase($daysRemaining);
        $intensity = self::PHASE_INTENSITY[$phase] * $difficultyWeight;

        return [
            'phase' => $phase,
            'exam_category' => $examProfile?->exam_category,
            'exam_name' => $examProfile?->exam_name,
            'days_remaining' => $daysRemaining,
            'weeks_remaining' => $daysRemaining !== null ? (int) ceil($daysRemaining / 7) : null,
            'weak_categories' => $weakCategories->take(2)->values()->all(),
            'strongest_category' => $strongestCategory,
            'recommended_daily_questions' => (int) round(self::BASE_DAILY_QUESTIONS * $intensity),
            'recommended_weekly_mock_tests' => (int) round(self::PHASE_WEEKLY_MOCK_TESTS[$phase] * $difficultyWeight),
            'daily_plan' => $this->buildDailyPlan($phase, $dailyHours, $weakCategories, $strongestCategory),
            'weekly_schedule' => $this->buildWeeklySchedule($phase, $weakCategories),
            'phase_timeline' => $this->buildPhaseTimeline($daysRemaining),
        ];
    }

    private function categoryScores(User $user): Collection
    {
        return Category::orderBy('name_en')->get()->map(function (Category $category) use ($user) {
            $latest = UserProgressSnapshot::where('user_id', $user->id)
                ->where('category_id', $category->id)
                ->orderByDesc('snapshot_date')
                ->first();

            return [
                'category_id' => $category->id,
                'code' => $category->code,
                'name_en' => $category->name_en,
                'name_si' => $category->name_si,
                'accuracy_percent' => $latest ? (float) $latest->accuracy_percent : 50.0,
            ];
        });
    }

    private function determinePhase(?int $daysRemaining): string
    {
        if ($daysRemaining === null) {
            return 'foundation';
        }
        if ($daysRemaining <= 1) {
            return 'exam_day';
        }
        foreach (self::PHASE_BOUNDARIES as $boundary) {
            if ($daysRemaining > $boundary['min_days_before_end']) {
                return $boundary['phase'];
            }
        }

        return 'final_revision';
    }

    private function buildDailyPlan(string $phase, float $dailyHours, Collection $weakCategories, ?array $strongestCategory): array
    {
        if ($phase === 'exam_day') {
            return [
                ['activity' => 'confidence_review', 'category' => null, 'minutes' => 20],
                ['activity' => 'rest', 'category' => null, 'minutes' => null],
            ];
        }

        $weak1 = $weakCategories->get(0);
        $weak2 = $weakCategories->get(1);
        $allocation = self::PHASE_DAILY_ALLOCATION[$phase];
        $totalMinutes = $dailyHours * 60;

        $blocks = [];
        if ($allocation['weak_1'] > 0 && $weak1) {
            $blocks[] = ['activity' => 'weak_category_practice', 'category' => $weak1, 'minutes' => (int) round($totalMinutes * $allocation['weak_1'])];
        }
        if ($allocation['weak_2'] > 0 && $weak2) {
            $blocks[] = ['activity' => 'weak_category_practice', 'category' => $weak2, 'minutes' => (int) round($totalMinutes * $allocation['weak_2'])];
        }
        if ($allocation['mock'] > 0) {
            $blocks[] = ['activity' => 'timed_mock_practice', 'category' => null, 'minutes' => (int) round($totalMinutes * $allocation['mock'])];
        }
        if ($allocation['strong_review'] > 0 && $strongestCategory) {
            $blocks[] = ['activity' => 'strong_category_maintenance', 'category' => $strongestCategory, 'minutes' => (int) round($totalMinutes * $allocation['strong_review'])];
        }
        if ($allocation['game'] > 0) {
            $blocks[] = ['activity' => 'cognitive_game_warmup', 'category' => null, 'minutes' => (int) round($totalMinutes * $allocation['game'])];
        }

        return $blocks;
    }

    private function buildWeeklySchedule(string $phase, Collection $weakCategories): array
    {
        $weak1 = $weakCategories->get(0);
        $weak2 = $weakCategories->get(1);
        $pattern = self::PHASE_WEEKLY_PATTERN[$phase];
        $dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        return collect($pattern)->map(function (string $dayType, int $index) use ($dayNames, $weak1, $weak2) {
            $category = match ($dayType) {
                'weak_1' => $weak1,
                'weak_2' => $weak2,
                default => null,
            };

            return [
                'day' => $dayNames[$index],
                'focus' => $dayType,
                'category' => $category,
            ];
        })->values()->all();
    }

    private function buildPhaseTimeline(?int $daysRemaining): array
    {
        if ($daysRemaining === null) {
            return [[
                'phase' => 'foundation',
                'label_en' => 'Foundation Phase',
                'label_si' => 'මූලික අවධිය',
                'from_days_remaining' => null,
                'to_days_remaining' => null,
                'is_current' => true,
            ]];
        }

        $timeline = [];
        $upperBound = $daysRemaining;

        foreach (self::PHASE_BOUNDARIES as $boundary) {
            $lowerBound = $boundary['min_days_before_end'];
            if ($upperBound <= $lowerBound) {
                continue;
            }

            $timeline[] = [
                'phase' => $boundary['phase'],
                'label_en' => $boundary['label_en'],
                'label_si' => $boundary['label_si'],
                'from_days_remaining' => $upperBound,
                'to_days_remaining' => $lowerBound,
                'is_current' => $daysRemaining <= $upperBound && $daysRemaining > $lowerBound,
            ];
            $upperBound = $lowerBound;
        }

        return $timeline;
    }
}
