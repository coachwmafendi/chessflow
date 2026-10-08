<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Role $role
 * @property string|null $pin
 * @property int $xp
 * @property int $streak_current
 * @property int $streak_best
 * @property Carbon|null $streak_last_date
 * @property array<string, mixed>|null $preferences
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'password', 'role', 'pin', 'preferences'])]
#[Hidden(['password', 'pin', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'role' => Role::class,
            'streak_last_date' => 'date',
            'preferences' => 'array',
        ];
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [Role::Guru, Role::Admin], true);
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * @return HasMany<ExamAttempt, $this>
     */
    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return HasMany<DailyCompletion, $this>
     */
    public function dailyCompletions(): HasMany
    {
        return $this->hasMany(DailyCompletion::class);
    }

    /**
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * @return HasMany<XpEvent, $this>
     */
    public function xpEvents(): HasMany
    {
        return $this->hasMany(XpEvent::class);
    }

    /**
     * @return HasMany<Classroom, $this>
     */
    public function taughtClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    /**
     * @return BelongsToMany<Classroom, $this>
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student', 'guardian_id', 'student_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student', 'student_id', 'guardian_id');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
