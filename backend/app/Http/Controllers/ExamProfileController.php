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
        // Query fresh via the relation builder, not the cached ->examProfile
        // property, so a reused User instance (e.g. across test calls)
        // never serves a stale value after store()/outcome() changes it.
        $profile = $request->user()->examProfile()->first();

        return response()->json(['data' => $profile ? $this->present($profile) : null]);
    }

    /**
     * Past (status='completed') exam profiles for the "Past Exams" list -
     * each optionally carries an attended/passed/score outcome the student
     * recorded after the exam date passed.
     */
    public function history(Request $request)
    {
        $profiles = $request->user()->examProfileHistory()->get();

        return response()->json(['data' => $profiles->map(fn (ExamProfile $p) => $this->present($p))->values()]);
    }

    /**
     * Records what happened at a past-due exam and archives the profile
     * (status -> 'completed'), freeing the student to start a new one via
     * store(). A bare attended=false "skip" is valid - forcing an outcome
     * would just train students to enter junk data.
     */
    public function outcome(Request $request)
    {
        $profile = $request->user()->examProfile()->first();

        if (! $profile || ! $profile->isPastDue()) {
            return response()->json(['message' => 'No past-due exam profile to record an outcome for.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'attended' => ['required', 'boolean'],
            'passed' => ['nullable', 'boolean'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $profile->update([
            'status' => 'completed',
            'outcome_attended' => $request->input('attended'),
            'outcome_passed' => $request->input('attended') ? $request->input('passed') : null,
            'outcome_score' => $request->input('attended') ? $request->input('score') : null,
            'outcome_recorded_at' => now(),
        ]);

        return response()->json(['data' => $this->present($profile)]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // exam_category (the old fixed dropdown) is no longer collected -
            // the setup flow now takes a freeform exam name + date instead
            // (ExamProfileDialog.tsx). The column stays for
            // difficultyWeight()'s lookup, always stored as 'other' (weight 1.0).
            'exam_name' => ['required', 'string', 'max:150'],
            'exam_date' => ['required', 'date', 'after_or_equal:today'],
            'daily_study_hours_target' => ['required', 'numeric', 'min:0.5', 'max:16'],
            'target_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            // Real-exam structure - all optional/skippable, used only to
            // derive a pace target (ExamProfile::targetSecondsPerQuestion())
            // and size mock exams; nothing here is required elsewhere.
            'exam_total_questions' => ['nullable', 'integer', 'min:1', 'max:500'],
            'exam_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'pass_mark' => ['nullable', 'integer', 'min:0', 'max:100'],
            'negative_marking' => ['nullable', 'boolean'],
            'exam_sections' => ['nullable', 'array'],
            'exam_sections.*' => ['string', 'exists:categories,code'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $existing = $request->user()->examProfile()->first();

        // A not-yet-due profile is edited in place. Once past due, a new
        // submission means "preparing for something else" - archive the old
        // row and start fresh so predictions/study plan never target a
        // stale exam.
        if ($existing && $existing->isPastDue()) {
            $existing->update(['status' => 'completed']);
            $existing = null;
        }

        if ($existing) {
            $existing->update(array_merge($validator->validated(), ['exam_category' => 'other']));
            $profile = $existing;
        } else {
            $profile = ExamProfile::create(array_merge(
                $validator->validated(),
                ['user_id' => $request->user()->id, 'exam_category' => 'other', 'status' => 'active']
            ));
        }

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
            'status' => $profile->status,
            'exam_category' => $profile->exam_category,
            'exam_category_label' => ExamProfile::EXAM_CATEGORIES[$profile->exam_category] ?? $profile->exam_category,
            'exam_name' => $profile->exam_name,
            'exam_date' => $profile->exam_date?->toDateString(),
            'daily_study_hours_target' => (float) $profile->daily_study_hours_target,
            'target_score' => $profile->target_score,
            'exam_total_questions' => $profile->exam_total_questions,
            'exam_duration_minutes' => $profile->exam_duration_minutes,
            'pass_mark' => $profile->pass_mark,
            'negative_marking' => $profile->negative_marking,
            'exam_sections' => $profile->exam_sections,
            'target_seconds_per_question' => $profile->targetSecondsPerQuestion(),
            'days_remaining' => $profile->daysRemaining(),
            'prep_progress_percent' => $profile->prepProgressPercent(),
            'prep_day_number' => $profile->prepDayNumber(),
            'prep_total_days' => $profile->prepTotalDays(),
            'is_past_due' => $profile->isPastDue(),
            'needs_outcome' => $profile->status === 'active' && $profile->isPastDue(),
            'outcome_attended' => $profile->outcome_attended,
            'outcome_passed' => $profile->outcome_passed,
            'outcome_score' => $profile->outcome_score,
            'outcome_recorded_at' => $profile->outcome_recorded_at?->toIso8601String(),
        ];
    }

    /**
     * Percentage of the prep window (profile creation -> exam date) elapsed,
     * for the dashboard's countdown progress circle. Null with no exam_date.
     */}
