<?php

namespace App\Models;

use App\Support\Chessflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $lesson_id
 * @property int|null $step_index
 * @property int|null $game_id
 * @property int|null $ply
 * @property array{fen: string, best: string, bestSan: string, also: list<string>, played: string, lesson?: string|null}|null $position
 * @property int $box
 * @property Carbon $due_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'lesson_id', 'step_index', 'game_id', 'ply', 'position', 'box', 'due_on'])]
class ReviewItem extends Model
{
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'position' => 'array',
        ];
    }

    /**
     * @param  Builder<ReviewItem>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->whereDate('due_on', '<=', Chessflow::today());
    }

    public function isDue(): bool
    {
        return $this->due_on->toDateString() <= Chessflow::today();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A position from the player's own game (otherwise a lesson step). */
    public function isFromGame(): bool
    {
        return $this->game_id !== null;
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
