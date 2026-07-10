<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_type',
        'category_id',
        'level_id',
        'total_questions',
        'correct_count',
        'score_percent',
        'started_at',
        'completed_at',
        'level_before_id',
        'level_after_id',
        'theta',
        'theta_se',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'score_percent' => 'decimal:2',
        'theta' => 'float',
        'theta_se' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(IqLevel::class, 'level_id');
    }

    public function levelBefore(): BelongsTo
    {
        return $this->belongsTo(IqLevel::class, 'level_before_id');
    }

    public function levelAfter(): BelongsTo
    {
        return $this->belongsTo(IqLevel::class, 'level_after_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SessionAnswer::class);
    }
}
