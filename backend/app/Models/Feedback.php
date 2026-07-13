<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = [
        'user_id',
        'overall_rating',
        'ui_rating',
        'question_quality_rating',
        'sinhala_quality_rating',
        'usefulness_rating',
        'comment',
        'suggestion',
        'locale',
        'status',
        'reviewed_at',
        'reviewed_by',
        'is_demo_feedback',
    ];

    protected $casts = [
        'overall_rating' => 'integer',
        'ui_rating' => 'integer',
        'question_quality_rating' => 'integer',
        'sinhala_quality_rating' => 'integer',
        'usefulness_rating' => 'integer',
        'reviewed_at' => 'datetime',
        'is_demo_feedback' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
