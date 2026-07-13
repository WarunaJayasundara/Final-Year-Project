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

    /**
     * Common Sri Lankan competitive government examinations, shown as fixed
     * options in the exam-profile setup form. "other" allows a free-text
     * exam_name for anything not on this list.
     */
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
     * Documented heuristic, not measured data: a rough relative-difficulty
     * weight per exam category used only to lightly tune how intensive
     * StudyPlanService's recommendations are (more mock tests / weak-area
     * drilling for exams generally considered more competitive/rigorous).
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
     * True once the exam date has passed (strictly before today). Distinct
     * from `status === 'completed'`: a profile becomes past-due the moment
     * the date passes, but stays 'active' (and keeps showing the "did you
     * attend?" prompt) until the student records an outcome or starts a new
     * exam profile - see ExamProfileController::store()/outcome().
     */
    public function isPastDue(): bool
    {
        return $this->exam_date !== null && $this->exam_date->startOfDay()->isBefore(Carbon::now()->startOfDay());
    }

    /**
     * The "72 seconds/question" pace target from the real exam's own
     * structure - null unless the student supplied both duration and
     * question count (both optional/skippable fields).
     */
    public function targetSecondsPerQuestion(): ?float
    {
        if (! $this->exam_duration_minutes || ! $this->exam_total_questions) {
            return null;
        }

        return round(($this->exam_duration_minutes * 60) / $this->exam_total_questions, 1);
    }
}
