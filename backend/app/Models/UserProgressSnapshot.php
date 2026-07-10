<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProgressSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'snapshot_date',
        'level_id',
        'category_id',
        'accuracy_percent',
        'questions_answered',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'accuracy_percent' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(IqLevel::class, 'level_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
