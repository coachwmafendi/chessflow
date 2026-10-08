<?php

namespace App\Models;

use App\Enums\GameResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $user_color
 * @property int $level
 * @property GameResult $result
 * @property string $pgn
 * @property int $move_count
 * @property Carbon $finished_at
 */
#[Fillable(['user_id', 'user_color', 'level', 'result', 'pgn', 'move_count', 'finished_at'])]
class Game extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'result' => GameResult::class,
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
