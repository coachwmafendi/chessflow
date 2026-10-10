<?php

namespace App\Models;

use App\Support\Chessflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $classroom_id
 * @property int $lesson_id
 * @property Carbon|null $due_on
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['classroom_id', 'lesson_id', 'due_on', 'note'])]
class Assignment extends Model
{
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->due_on !== null && $this->due_on->toDateString() < Chessflow::today();
    }

    /** "Sebelum Jum, 17 Okt", "Hari ini" or null when there is no due date. */
    public function dueLabel(): ?string
    {
        if ($this->due_on === null) {
            return null;
        }
        if ($this->due_on->toDateString() === Chessflow::today()) {
            return __('Hari ini');
        }

        return __('Sebelum :date', ['date' => $this->due_on->copy()->settings(['locale' => app()->getLocale()])->translatedFormat('D, j M')]);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
