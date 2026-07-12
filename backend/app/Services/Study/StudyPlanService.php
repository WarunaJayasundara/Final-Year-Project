<?php

namespace App\Services\Study;

use App\Models\Category;
use App\Models\ExamReadinessPrediction;
use App\Models\SessionAnswer;
use App\Models\TestSession;
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

    /** Used as the readiness target when the student hasn't set a real exam pass_mark. */
    private const DEFAULT_TARGET_READINESS_PERCENT = 80.0;

    /**
     * Documented rule-of-thumb, not a fitted coefficient: roughly how many
     * minutes of additional weekly focused practice tend to move readiness
     * by one percentage point, based on the same order-of-magnitude as
     * BASE_DAILY_QUESTIONS/PHASE_INTENSITY's existing hand-picked constants.
     * Used only to size the "is the current plan enough?" warning below -
     * never presented as a precise prediction.
     */
    private const MINUTES_PER_READINESS_POINT_PER_WEEK = 12.0;

    /** Below this many days remaining, an insufficient-plan warning becomes worth surfacing at all. */
    private const WARNING_DAYS_WINDOW = 30;

    /** Minimum readiness-percent gap before a warning is worth surfacing (a 2-3pt gap isn't actionable). */
    private const WARNING_MIN_GAP_POINTS = 10.0;

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

        $phase = self::determinePhase($daysRemaining);
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
            'readiness_gap' => $this->readinessGap($user, $examProfile, $daysRemaining, $dailyHours, $weakCategories),
        ];
    }

    /**
     * "Is the current plan actually enough?" (brief's own critical §11
     * feature): compares latest predicted readiness against a target,
     * current answering pace against the real exam's pace requirement (if
     * supplied), and - only when the exam is genuinely close AND the gap is
     * meaningful AND the current daily-hours plan can't plausibly close it -
     * a warning object the frontend can surface. Never guarantees anything;
     * always explains why, per the brief's explicit "do not guarantee
     * success" instruction.
     */
    private function readinessGap(User $user, $examProfile, ?int $daysRemaining, float $dailyHours, Collection $weakCategories): array
    {
        $currentReadiness = ExamReadinessPrediction::where('user_id', $user->id)
            ->orderByDesc('predicted_at')
            ->value('readiness_percent');
        $currentReadiness = $currentReadiness !== null ? (float) $currentReadiness : null;

        $targetReadiness = $examProfile?->pass_mark !== null
            ? (float) $examProfile->pass_mark
            : self::DEFAULT_TARGET_READINESS_PERCENT;

        $currentPaceSeconds = $this->currentPaceSeconds($user);
        $targetPaceSeconds = $examProfile?->targetSecondsPerQuestion();

        $result = [
            'current_readiness_percent' => $currentReadiness,
            'target_readiness_percent' => $targetReadiness,
            'readiness_gap_points' => $currentReadiness !== null ? round($targetReadiness - $currentReadiness, 1) : null,
            'current_pace_seconds' => $currentPaceSeconds,
            'target_pace_seconds' => $targetPaceSeconds,
            'pace_gap_seconds' => $targetPaceSeconds !== null && $currentPaceSeconds !== null
                ? round($targetPaceSeconds - $currentPaceSeconds, 1)
                : null,
            'warning' => null,
        ];

        if ($daysRemaining === null || $daysRemaining > self::WARNING_DAYS_WINDOW || $currentReadiness === null) {
            return $result;
        }

        $gapPoints = $targetReadiness - $currentReadiness;
        if ($gapPoints < self::WARNING_MIN_GAP_POINTS) {
            return $result;
        }

        $weeksRemaining = max($daysRemaining / 7, 0.5);
        $requiredWeeklyMinutes = $gapPoints * self::MINUTES_PER_READINESS_POINT_PER_WEEK;
        $requiredDailyMinutes = $requiredWeeklyMinutes / 7;
        $currentDailyMinutes = $dailyHours * 60;

        if ($requiredDailyMinutes <= $currentDailyMinutes * 1.1) {
            return $result;
        }

        $severity = $daysRemaining <= 14 ? 'high' : 'medium';
        $weakNames = $weakCategories->take(2)->pluck('name_en')->filter()->implode(' + ');
        $weakNamesSi = $weakCategories->take(2)->pluck('name_si')->filter()->implode(' + ');
        $recommendedDailyMinutes = (int) round($requiredDailyMinutes);

        $result['warning'] = [
            'severity' => $severity,
            'recommended_daily_minutes' => $recommendedDailyMinutes,
            'message_en' => sprintf(
                'Based on your current performance and the %d day%s remaining, your planned %d minutes/day may not be enough to close the %.0f-point readiness gap. %s Increasing focused practice to roughly %d minutes/day may help.',
                $daysRemaining,
                $daysRemaining === 1 ? '' : 's',
                (int) round($currentDailyMinutes),
                $gapPoints,
                $weakNames ? "Your weakest areas are {$weakNames}." : '',
                $recommendedDailyMinutes
            ),
            'message_si' => sprintf(
                'දින %d ක් ඉතිරිව ඇත. ලකුණු %.0f ක පරතරය ඇත. වත්මන් දිනකට මිනිත්තු %d ප්‍රමාණවත් නොවේ. %s දිනකට මිනිත්තු %d අවශ්‍ය වේ.',
                $daysRemaining,
                $gapPoints,
                (int) round($currentDailyMinutes),
                $weakNamesSi ? "දුර්වලම ප්‍රවර්ග වෙත අවධානය යොමු කරන්න: {$weakNamesSi}." : '',
                $recommendedDailyMinutes
            ),
        ];

        return $result;
    }

    /** Median response time (seconds) over the user's most recent answered questions, null if none yet. */
    private function currentPaceSeconds(User $user): ?float
    {
        $sessionIds = TestSession::where('user_id', $user->id)->pluck('id');

        $ms = SessionAnswer::whereIn('test_session_id', $sessionIds)
            ->whereNotNull('response_time_ms')
            ->orderByDesc('answered_at')
            ->limit(100)
            ->pluck('response_time_ms')
            ->all();

        if (empty($ms)) {
            return null;
        }

        sort($ms);
        $count = count($ms);
        $mid = intdiv($count, 2);
        $medianMs = $count % 2 === 0 ? ($ms[$mid - 1] + $ms[$mid]) / 2 : $ms[$mid];

        return round($medianMs / 1000, 1);
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

    /**
     * Pure function of days-remaining -> phase, made public/static so other
     * services (e.g. WeakAreaWeightingService's exam-approaching training
     * mode) can derive the same phase without duplicating the boundary
     * logic or depending on a full generate() call.
     */
    public static function determinePhase(?int $daysRemaining): string
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
