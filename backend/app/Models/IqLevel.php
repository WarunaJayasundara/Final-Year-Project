<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IqLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'level_number',
        'name_en',
        'name_si',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'level_id');
    }
}
