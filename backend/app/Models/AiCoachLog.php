<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCoachLog extends Model
{
    protected $fillable = [
        'user_id',
        'asked_at',
    ];

    protected $casts = [
        'asked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
