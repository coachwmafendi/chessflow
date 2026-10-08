<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/** Per-user rate limit for browser-reported progress (lessons, daily puzzle, games). */
class ProgressGuard
{
    public const TOO_FAST = 'Perlahan sikit! Keputusan ini tidak direkodkan. Cuba lagi sebentar.';

    public static function allow(User $user, string $action): bool
    {
        $max = (int) config("chessflow.limits.{$action}_per_minute");

        return RateLimiter::attempt("progress:{$action}:{$user->id}", $max, fn () => true, 60) === true;
    }
}
