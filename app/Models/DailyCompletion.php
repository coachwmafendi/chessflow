<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property Carbon $solved_at
 */
#[Fillable(['user_id', 'date', 'solved_at'])]
class DailyCompletion extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'solved_at' => 'datetime',
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
