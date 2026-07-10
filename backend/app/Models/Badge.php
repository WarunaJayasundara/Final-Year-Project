<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    protected $fillable = [
        'code',
        'name_en',
        'name_si',
        'description_en',
        'description_si',
        'icon',
        'xp_reward',
        'coin_reward',
    ];

    protected $casts = [
        'xp_reward' => 'integer',
        'coin_reward' => 'integer',
    ];

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    /** Compact shape used when surfacing a newly-earned badge in an API response for a toast/notification. */
    public function toRewardArray(): array
    {
        return [
            'code' => $this->code,
            'name_en' => $this->name_en,
            'name_si' => $this->name_si,
            'icon' => $this->icon,
        ];
    }
}
