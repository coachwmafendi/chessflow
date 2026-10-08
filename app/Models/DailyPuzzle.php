<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property int $lesson_id
 * @property int $step_index
 * @property Carbon|null $created_at
 */
#[Fillable(['date', 'lesson_id', 'step_index'])]
class DailyPuzzle extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
