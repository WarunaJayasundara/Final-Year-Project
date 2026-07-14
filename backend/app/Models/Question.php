<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'level_id',
        'question_type',
        'subcategory',
        'question_text_en',
        'question_text_si',
        'image_path',
        'generation_rule',
        'transformation_steps',
        'visual_complexity_score',
        'options',
        'correct_option_key',
        'explanation_en',
        'explanation_si',
        'difficulty_weight',
        'solving_time_seconds',
        'learned_expected_time_seconds',
        'time_sample_count',
        'time_calibration_status',
        'bloom_level',
        'exam_tags',
        'cognitive_skill',
        'irt_difficulty',
        'irt_discrimination',
        'irt_calibrated_at',
        'irt_response_count',
        'irt_calibration_status',
        'is_active',
        'created_by',
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
        'reviewed_by',
        'semantic_equivalence_score',
    ];

    protected $casts = [
        'options' => 'array',
        'exam_tags' => 'array',
        'transformation_steps' => 'array',
        'visual_complexity_score' => 'float',
        'is_active' => 'boolean',
        'irt_difficulty' => 'float',
        'irt_discrimination' => 'float',
        'irt_calibrated_at' => 'datetime',
        'irt_response_count' => 'integer',
        'quality_score' => 'float',
        'learned_expected_time_seconds' => 'float',
        'time_sample_count' => 'integer',
        'translation_quality_score' => 'float',
        'semantic_equivalence_score' => 'float',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Learned time (once calibrated) > authored baseline > generic default -
     * same fallback order used when scoring time_performance_ratio.
     */
    public function expectedTimeSeconds(): float
    {
        return $this->learned_expected_time_seconds
            ?? $this->solving_time_seconds
            ?? 60.0;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(IqLevel::class, 'level_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class);
    }

    /**
     * Client-safe question data - never includes correct_option_key or the
     * explanation unless $includeExplanation is true, which only the
     * completed-session report endpoint should pass. Live question payloads
     * must keep the default false or a student could see the answer early.
     */
    public function toClientArray(string $locale = 'en', bool $includeExplanation = false): array
    {
        $options = collect($this->options)->map(function (array $option) use ($locale) {
            return [
                'key' => $option['key'],
                'text' => $option["text_{$locale}"] ?? $option['text_en'] ?? null,
                'image_path' => $option['image_path'] ?? null,
            ];
        })->values()->all();

        $data = [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'level_id' => $this->level_id,
            'question_type' => $this->question_type,
            'question_text' => $locale === 'si' ? $this->question_text_si : $this->question_text_en,
            'image_path' => $this->image_path,
            'options' => $options,
            'expected_time_seconds' => $this->expectedTimeSeconds(),
        ];

        if ($includeExplanation) {
            $data['explanation'] = $locale === 'si' ? $this->explanation_si : $this->explanation_en;
        }

        return $data;
    }
}
