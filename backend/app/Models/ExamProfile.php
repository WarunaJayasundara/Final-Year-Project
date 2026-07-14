<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ExamProfile extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'exam_category',
        'exam_name',
        'exam_date',
        'daily_study_hours_target',
        'target_score',
        'exam_total_questions',
        'exam_duration_minutes',
        'pass_mark',
        'negative_marking',
        'exam_sections',
        'outcome_attended',
        'outcome_passed',
        'outcome_score',
        'outcome_recorded_at',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'daily_study_hours_target' => 'float',
        'target_score' => 'integer',
        'exam_total_questions' => 'integer',
        'exam_duration_minutes' => 'integer',
        'pass_mark' => 'integer',
        'negative_marking' => 'boolean',
        'exam_sections' => 'array',
        'outcome_attended' => 'boolean',
        'outcome_passed' => 'boolean',
        'outcome_score' => 'integer',
        'outcome_recorded_at' => 'datetime',
    ];

    /** Common Sri Lankan competitive government exams; "other" covers a free-text exam_name. */
    public const EXAM_CATEGORIES = [
        'slas' => 'Sri Lanka Administrative Service (SLAS)',
        'development_officer' => 'Development Officer',
        'management_assistant' => 'Management Assistant',
        'grama_niladhari' => 'Grama Niladhari',
        'police' => 'Sri Lanka Police',
        'customs' => 'Sri Lanka Customs',
        'excise' => 'Excise Department',
        'railway' => 'Railway Department',
        'banking' => 'Banking Exams',
        'teaching_service' => 'Teaching Service',
        'university_aptitude' => 'University Aptitude Tests',
        'graduate_recruitment' => 'Graduate Recruitment Exams',
        'other' => 'Other',
    ];

    /**
     * A heuristic, not measured data: relative-difficulty weight per exam
     * category, used only to lightly tune StudyPlanService's intensity.
     */
    public const DIFFICULTY_WEIGHT = [
        'slas' => 1.2,
        'university_aptitude' => 1.2,
        'banking' => 1.15,
        'graduate_recruitment' => 1.1,
        'police' => 1.0,
        'customs' => 1.0,
        'excise' => 1.0,
        'railway' => 1.0,
        'development_officer' => 1.0,
        'management_assistant' => 1.0,
        'teaching_service' => 1.0,
        'grama_niladhari' => 0.95,
        'other' => 1.0,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function difficultyWeight(): float
    {
        return self::DIFFICULTY_WEIGHT[$this->exam_category] ?? 1.0;
    }

    public function daysRemaining(): ?int
    {
        if (! $this->exam_date) {
            return null;
        }

        return max(0, (int) Carbon::now()->startOfDay()->diffInDays($this->exam_date, false));
    }

    /**
     * True once the exam date has passed. Distinct from status='completed':
     * a profile stays 'active' (still prompting for an outcome) until the
     * student records one or starts a new profile - see
     * ExamProfileController::store()/outcome().
     */
    public function isPastDue(): bool
    {
        return $this->exam_date !== null && $this->exam_date->startOfDay()->isBefore(Carbon::now()->startOfDay());
    }

    /** Pace target derived from the real exam's duration/question count - null unless both were supplied. */
    public function targetSecondsPerQuestion(): ?float
    {
        if (! $this->exam_duration_minutes || ! $this->exam_total_questions) {
            return null;
        }

        return round(($this->exam_duration_minutes * 60) / $this->exam_total_questions, 1);
    }

    /**
     * Raw signed day-count from profile creation to exam date - can be zero
     * or negative if the exam is on/before the creation day. Private: callers
     * want one of the three clamped views below, not this raw value.
     */
    private function prepTotalDaysRaw(): ?int
    {
        if (! $this->exam_date) {
            return null;
        }

        return (int) $this->created_at->startOfDay()->diffInDays($this->exam_date, false);
    }

    /**
     * How many days this student's own prep window actually spans (from the
     * day they set up this exam profile to the exam date) - always at least
     * 1. This is deliberately independent of daysRemaining()/StudyPlanService's
     * phase boundaries (which only look at time left until the exam): two
     * students with the same daysRemaining but different prepTotalDays are on
     * genuinely different journeys - one set a short-notice exam, the other
     * has been preparing for a while - and the frontend surfaces this
     * separately as "Day X of your Y-day plan" rather than folding it into
     * the phase name.
     */
    public function prepTotalDays(): ?int
    {
        $totalDays = $this->prepTotalDaysRaw();

        return $totalDays !== null ? max(1, $totalDays) : null;
    }

    /** 1-indexed day-of-plan: the day the profile was created is day 1. */
    public function prepDayNumber(): ?int
    {
        if (! $this->exam_date) {
            return null;
        }

        $elapsedDays = (int) $this->created_at->startOfDay()->diffInDays(Carbon::now()->startOfDay(), false);

        return max(1, $elapsedDays + 1);
    }

    /** Percentage of this student's own prep window elapsed - for the dashboard countdown ring and the study-plan "Day X of Y" line. */
    public function prepProgressPercent(): ?float
    {
        $totalDays = $this->prepTotalDaysRaw();
        if ($totalDays === null) {
            return null;
        }
        if ($totalDays <= 0) {
            return 100.0;
        }

        $elapsedDays = $this->created_at->startOfDay()->diffInDays(Carbon::now(), false);

        return round(max(0, min(100, ($elapsedDays / $totalDays) * 100)), 1);
    }
}
