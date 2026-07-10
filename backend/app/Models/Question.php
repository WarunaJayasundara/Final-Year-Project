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
        'options',
        'correct_option_key',
        'explanation_en',
        'explanation_si',
        'difficulty_weight',
        'solving_time_seconds',
        'bloom_level',
        'exam_tags',
        'cognitive_skill',
        'irt_difficulty',
        'irt_discrimination',
        'irt_calibrated_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'options' => 'array',
        'exam_tags' => 'array',
        'is_active' => 'boolean',
        'irt_difficulty' => 'float',
        'irt_discrimination' => 'float',
        'irt_calibrated_at' => 'datetime',
    ];

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

    /**
     * Question data safe to send to the client before it's answered
     * (never includes correct_option_key or explanations).
     */
    public function toClientArray(string $locale = 'en'): array
    {
        $options = collect($this->options)->map(function (array $option) use ($locale) {
            return [
                'key' => $option['key'],
                'text' => $option["text_{$locale}"] ?? $option['text_en'] ?? null,
                'image_path' => $option['image_path'] ?? null,
            ];
        })->values()->all();

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'level_id' => $this->level_id,
            'question_type' => $this->question_type,
            'question_text' => $locale === 'si' ? $this->question_text_si : $this->question_text_en,
            'image_path' => $this->image_path,
            'options' => $options,
        ];
    }
}
