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
        'solving_time_seconds',
        'source',
        'status',
        'generated_by',
        'reviewed_by',
        'reviewed_at',
        'promoted_question_id',
        'source_document_id',
        'source_type',
        'generation_method',
        'learning_objective',
        'difficulty_reason',
        'quality_score',
        'validation_status',
        'translation_status',
        'translation_quality_score',
        'sinhala_review_status',
        'semantic_equivalence_score',
        'generation_rule',
        'transformation_steps',
        'visual_complexity_score',
    ];

    protected $casts = [
        'options' => 'array',
        'difficulty_weight' => 'integer',
        'reviewed_at' => 'datetime',
        'quality_score' => 'float',
        'translation_quality_score' => 'float',
        'semantic_equivalence_score' => 'float',
        'transformation_steps' => 'array',
        'visual_complexity_score' => 'float',
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

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class);
    }
}
