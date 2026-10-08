<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property int $score
 * @property int $total
 * @property bool $passed
 * @property list<int> $failed_steps
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'lesson_id', 'score', 'total', 'passed', 'failed_steps'])]
class ExamAttempt extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'failed_steps' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return HasOne<Certificate, $this>
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }
}
