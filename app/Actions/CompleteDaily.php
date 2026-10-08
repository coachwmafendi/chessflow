<?php

namespace App\Actions;

use App\Models\DailyCompletion;
use App\Models\User;
use App\Support\Chessflow;
use Illuminate\Support\Facades\DB;

class CompleteDaily
{
    public function __construct(private AwardXp $awardXp) {}

    /**
     * Streak rule (MIGRATION_PLAN §4): last day = yesterday → +1, today → unchanged, otherwise → 1.
     *
     * @return array{streak: int, xp: int, alreadyDone: bool}
     */
    public function handle(User $user): array
    {
        $today = Chessflow::today();

        return DB::transaction(function () use ($user, $today) {
            $exists = DailyCompletion::where('user_id', $user->id)->whereDate('date', $today)->exists();
            if ($exists) {
                return ['streak' => $user->streak_current, 'xp' => 0, 'alreadyDone' => true];
            }

            $completion = DailyCompletion::create([
                'user_id' => $user->id,
                'date' => $today,
                'solved_at' => now(),
            ]);

            $last = $user->streak_last_date?->toDateString();
            $streak = match ($last) {
                $today => $user->streak_current,
                Chessflow::yesterday() => $user->streak_current + 1,
                default => 1,
            };

            $user->forceFill([
                'streak_current' => $streak,
                'streak_best' => max($streak, $user->streak_best),
                'streak_last_date' => $today,
            ])->save();

            $xp = $this->awardXp->handle($user, (int) config('chessflow.daily.xp'), $completion);

            return ['streak' => $streak, 'xp' => $xp, 'alreadyDone' => false];
        });
    }
}
