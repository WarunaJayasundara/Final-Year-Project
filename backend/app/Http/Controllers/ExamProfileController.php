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
        // Queried fresh via the relation's query builder (not the cached
        // ->examProfile property) - a reused User model instance (e.g. the
        // same object bound by TestCase::actingAs() across multiple calls
        // in one test) would otherwise serve a stale cached relation value
        // after store()/outcome() changes which profile is active.
        $profile = $request->user()->examProfile()->first();

        return response()->json(['data' => $profile ? $this->present($profile) : null]);
    }

    /**
     * Past (status='completed') exam profiles for the "Past Exams" list -
     * each optionally carries an attended/passed/score outcome the student
     * was asked to record once the exam date passed. This is real outcome
     * data future model-validation work could use as ground truth, unlike
     * anything else this platform currently has (see §12/ML docs).
     */
    public function history(Request $request)
    {
        $profiles = $request->user()->examProfileHistory()->get();

        return response()->json(['data' => $profiles->map(fn (ExamProfile $p) => $this->present($p))->values()]);
    }

    /**
     * Records what actually happened at a past-due exam and archives the
     * profile (status -> 'completed'), which also lets the student start a
     * fresh exam profile via store(). Deliberately does NOT require an
     * outcome to be recorded before the student can move on - "skip" is a
     * valid POST with attended=false and nothing else, since forcing the
     * outcome question would just train students to enter junk data.
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
            // exam_category (the old fixed-list dropdown) is intentionally no
            // longer collected from the user - the setup flow now asks for a
            // freeform exam name + date instead (see ExamProfileDialog.tsx).
            // The column itself stays for difficultyWeight()'s lookup table
            // and is always stored as 'other' (its documented default
            // weight of 1.0), so no schema/history is broken by this change.
            'exam_name' => ['required', 'string', 'max:150'],
            'exam_date' => ['required', 'date', 'after_or_equal:today'],
            'daily_study_hours_target' => ['required', 'numeric', 'min:0.5', 'max:16'],
            'target_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            // Real-exam structure (all optional/skippable - see §6 of the
            // upgrade brief: "student must be able to skip if not preparing
            // for a specific examination"). Used only to derive a pace
            // target (ExamProfile::targetSecondsPerQuestion()) and to size
            // mock exams; nothing here is required for the rest of the app.
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

        // A student can keep editing their current (not-yet-due) exam
        // profile in place. But once it's past due, submitting a new one
        // means "I'm now preparing for something else" - archive the old
        // row (without forcing an outcome - see outcome()'s own docblock)
        // and start a fresh active profile, so exam_readiness_predictions
        // and the study plan are never computed against a stale target.
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
            'prep_progress_percent' => $this->prepProgressPercent($profile),
            'is_past_due' => $profile->isPastDue(),
            'needs_outcome' => $profile->status === 'active' && $profile->isPastDue(),
            'outcome_attended' => $profile->outcome_attended,
            'outcome_passed' => $profile->outcome_passed,
            'outcome_score' => $profile->outcome_score,
            'outcome_recorded_at' => $profile->outcome_recorded_at?->toIso8601String(),
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
