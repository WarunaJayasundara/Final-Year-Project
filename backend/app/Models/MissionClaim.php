<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionClaim extends Model
{
    protected $fillable = [
        'user_id',
        'mission_code',
        'period_key',
        'xp_awarded',
        'coin_awarded',
        'claimed_at',
    ];

    protected $casts = [
        'xp_awarded' => 'integer',
        'coin_awarded' => 'integer',
        'claimed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
