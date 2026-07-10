<?php

namespace App\Http\Controllers;

use App\Models\ExamProfile;
use App\Services\Gamification\BadgeService;
use App\Services\Study\StudyPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExamProfileController extends Controller
{
    public function __construct(private StudyPlanService $studyPlan, private BadgeService $badges)
    {
    }

    public function show(Request $request)
    {
        $profile = $request->user()->examProfile;

        return response()->json(['data' => $profile ? $this->present($profile) : null]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_category' => ['required', 'string', 'in:'.implode(',', array_keys(ExamProfile::EXAM_CATEGORIES))],
            'exam_name' => ['nullable', 'string', 'max:150'],
            'exam_date' => ['nullable', 'date', 'after_or_equal:today'],
            'daily_study_hours_target' => ['required', 'numeric', 'min:0.5', 'max:16'],
            'target_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $profile = ExamProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validator->validated()
        );

        $newBadges = $this->badges->evaluate($request->user()->fresh());

        return response()->json([
            'data' => $this->present($profile),
            'new_badges' => array_map(fn ($b) => $b->toRewardArray(), $newBadges),
        ]);
    }

    public function studyPlan(Request $request)
    {
        return response()->json(['data' => $this->studyPlan->generate($request->user())]);
    }

    public function examCategories()
    {
        $categories = collect(ExamProfile::EXAM_CATEGORIES)->map(fn ($label, $code) => [
            'code' => $code,
            'label' => $label,
        ])->values();

        return response()->json(['data' => $categories]);
    }

    private function present(ExamProfile $profile): array
    {
        return [
            'exam_category' => $profile->exam_category,
            'exam_category_label' => ExamProfile::EXAM_CATEGORIES[$profile->exam_category] ?? $profile->exam_category,
            'exam_name' => $profile->exam_name,
            'exam_date' => $profile->exam_date?->toDateString(),
            'daily_study_hours_target' => (float) $profile->daily_study_hours_target,
            'target_score' => $profile->target_score,
            'days_remaining' => $profile->daysRemaining(),
            'prep_progress_percent' => $this->prepProgressPercent($profile),
        ];
    }

    /**
     * Percentage of the prep window (profile creation -> exam date) elapsed
     * so far, for the dashboard's countdown progress circle. Null when no
     * exam_date is set - there's no window to show progress through.
     */
    private function prepProgressPercent(ExamProfile $profile): ?float
    {
        if (! $profile->exam_date) {
            return null;
        }

        $totalDays = $profile->created_at->startOfDay()->diffInDays($profile->exam_date, false);
        if ($totalDays <= 0) {
            return 100.0;
        }

        $elapsedDays = $profile->created_at->startOfDay()->diffInDays(now(), false);

        return round(max(0, min(100, ($elapsedDays / $totalDays) * 100)), 1);
    }
}
