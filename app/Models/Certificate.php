<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $level_id
 * @property int $exam_attempt_id
 * @property string $display_name
 * @property int $score
 * @property int $total
 * @property string $code
 * @property Carbon $issued_at
 */
#[Fillable(['user_id', 'level_id', 'exam_attempt_id', 'display_name', 'score', 'total', 'code', 'issued_at'])]
class Certificate extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
