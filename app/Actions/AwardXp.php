<?php

namespace App\Actions;

use App\Models\User;
use App\Models\XpEvent;
use Illuminate\Database\Eloquent\Model;

/** Single entry point for XP: writes the ledger row and bumps the cached total on users. */
class AwardXp
{
    public function handle(User $user, int $amount, ?Model $source = null): int
    {
        if ($amount <= 0) {
            return 0;
        }

        XpEvent::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
        ]);
        $user->increment('xp', $amount);

        return $amount;
    }
}
