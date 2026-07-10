<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyCheckin extends Model
{
    protected $fillable = [
        'user_id',
        'checkin_date',
        'study_hours',
        'motivation_score',
        'attended',
    ];

    protected $casts = [
        'checkin_date' => 'date',
        'study_hours' => 'float',
        'motivation_score' => 'integer',
        'attended' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
