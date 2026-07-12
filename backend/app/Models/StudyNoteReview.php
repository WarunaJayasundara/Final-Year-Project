<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyNoteReview extends Model
{
    protected $fillable = [
        'user_id',
        'study_note_id',
        'ease_factor',
        'interval_days',
        'review_count',
        'last_result',
        'next_review_at',
    ];

    protected $casts = [
        'ease_factor' => 'float',
        'interval_days' => 'integer',
        'review_count' => 'integer',
        'next_review_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyNote(): BelongsTo
    {
        return $this->belongsTo(StudyNote::class);
    }
}
