<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyNote extends Model
{
    protected $fillable = [
        'source_document_id',
        'category_id',
        'subcategory',
        'title_en',
        'title_si',
        'learning_objective_en',
        'learning_objective_si',
        'content_en',
        'content_si',
        'worked_example_en',
        'worked_example_si',
        'key_technique_en',
        'key_technique_si',
        'common_mistakes_en',
        'common_mistakes_si',
        'key_concepts',
        'generation_method',
        'status',
        'generated_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'key_concepts' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
