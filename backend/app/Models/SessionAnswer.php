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
        'ai_feedback_text',
        'ai_feedback_generated_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'answered_at' => 'datetime',
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
