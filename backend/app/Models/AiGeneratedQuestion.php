<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneratedQuestion extends Model
{
    protected $fillable = [
        'category_id',
        'level_id',
        'question_type',
        'question_text_en',
        'question_text_si',
        'options',
        'correct_option_key',
        'explanation_en',
        'explanation_si',
        'difficulty_weight',
        'source',
        'status',
        'generated_by',
        'reviewed_by',
        'reviewed_at',
        'promoted_question_id',
    ];

    protected $casts = [
        'options' => 'array',
        'difficulty_weight' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(IqLevel::class, 'level_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
