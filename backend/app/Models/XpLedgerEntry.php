<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XpLedgerEntry extends Model
{
    protected $table = 'xp_ledger';

    protected $fillable = [
        'user_id',
        'xp_amount',
        'coin_amount',
        'reason',
    ];

    protected $casts = [
        'xp_amount' => 'integer',
        'coin_amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
