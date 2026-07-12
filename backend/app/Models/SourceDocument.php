<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceDocument extends Model
{
    protected $fillable = [
        'title',
        'document_type',
        'exam_type_tags',
        'year',
        'uploaded_by',
        'file_path',
        'page_count',
        'analysis_status',
        'extracted_topics',
        'detected_patterns',
        'extracted_theory_concepts',
        'reliability_note',
    ];

    protected $casts = [
        'exam_type_tags' => 'array',
        'extracted_topics' => 'array',
        'detected_patterns' => 'array',
        'extracted_theory_concepts' => 'array',
        'page_count' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function aiGeneratedQuestions(): HasMany
    {
        return $this->hasMany(AiGeneratedQuestion::class);
    }
}
