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
        'risk_of_dropping_practice_probability',
        'at_risk_of_dropping_practice',
        'predicted_next_assessment_score',
        'predicted_score_change',
        'plain_english_explanation',
        'time_management_readiness_percent',
        'predicted_score_range',
    ];

    protected $casts = [
        'features' => 'array',
        'readiness_percent' => 'float',
        'reasons' => 'array',
        'predicted_at' => 'datetime',
        'risk_of_dropping_practice_probability' => 'float',
        'at_risk_of_dropping_practice' => 'boolean',
        'predicted_next_assessment_score' => 'float',
        'predicted_score_change' => 'float',
        'time_management_readiness_percent' => 'float',
        'predicted_score_range' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
