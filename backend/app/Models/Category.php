<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_en',
        'name_si',
        'description_en',
        'description_si',
        'icon',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
