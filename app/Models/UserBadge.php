<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $badge
 * @property Carbon $awarded_at
 * @property Carbon|null $seen_at
 */
#[Fillable(['user_id', 'badge', 'awarded_at', 'seen_at'])]
class UserBadge extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
            'seen_at' => 'datetime',
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
