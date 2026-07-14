<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'date_of_birth',
        'password',
        'google_id',
        'avatar_url',
        'auth_provider',
        'role',
        'is_demo_user',
        'locale',
        'current_level_id',
        'placement_completed_at',
        'theta_estimate',
        'theta_se',
        'xp',
        'coins',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'placement_completed_at' => 'datetime',
        'theta_estimate' => 'float',
        'theta_se' => 'float',
        'xp' => 'integer',
        'coins' => 'integer',
        'is_demo_user' => 'boolean',
    ];

    public function currentLevel(): BelongsTo
    {
        return $this->belongsTo(IqLevel::class, 'current_level_id');
    }

    public function testSessions(): HasMany
    {
        return $this->hasMany(TestSession::class);
    }

    public function progressSnapshots(): HasMany
    {
        return $this->hasMany(UserProgressSnapshot::class);
    }

    public function gameScores(): HasMany
    {
        return $this->hasMany(GameScore::class);
    }

    public function dailyCheckins(): HasMany
    {
        return $this->hasMany(UserDailyCheckin::class);
    }

    public function aiCoachLogs(): HasMany
    {
        return $this->hasMany(AiCoachLog::class);
    }

    public function readinessPredictions(): HasMany
    {
        return $this->hasMany(ExamReadinessPrediction::class);
    }

    /**
     * The student's current active exam profile, if any - only one is ever
     * 'active' at once (see ExamProfileController::store()'s rollover);
     * examProfileHistory() holds the rest.
     */
    public function examProfile(): HasOne
    {
        return $this->hasOne(ExamProfile::class)->where('status', 'active')->latestOfMany();
    }

    public function examProfileHistory(): HasMany
    {
        return $this->hasMany(ExamProfile::class)->where('status', 'completed')->orderByDesc('exam_date');
    }

    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function xpLedger(): HasMany
    {
        return $this->hasMany(XpLedgerEntry::class, 'user_id');
    }

    public function missionClaims(): HasMany
    {
        return $this->hasMany(MissionClaim::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
