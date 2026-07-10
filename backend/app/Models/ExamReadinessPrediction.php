<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamReadinessPrediction extends Model
{
    protected $fillable = [
        'user_id',
        'features',
        'readiness_percent',
        'readiness_label',
        'reasons',
        'model_version',
        'predicted_at',
    ];

    protected $casts = [
        'features' => 'array',
        'readiness_percent' => 'float',
        'reasons' => 'array',
        'predicted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
