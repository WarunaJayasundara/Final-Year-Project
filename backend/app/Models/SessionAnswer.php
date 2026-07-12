<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_session_id',
        'question_id',
        'selected_option_key',
        'is_correct',
        'answered_at',
        'response_time_ms',
        'time_performance_ratio',
        'answered_within_expected_time',
        'ai_feedback_text',
        'ai_feedback_generated_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'answered_at' => 'datetime',
        'response_time_ms' => 'integer',
        'time_performance_ratio' => 'float',
        'answered_within_expected_time' => 'boolean',
        'ai_feedback_generated_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TestSession::class, 'test_session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
