<?php

namespace App\Actions;

use App\Enums\GameResult;
use App\Models\Game;
use App\Models\User;
use App\Support\Chessflow;
use Illuminate\Support\Facades\DB;

class RecordGame
{
    public function __construct(private AwardXp $awardXp) {}

    /**
     * Games are played in the browser, so the result is trusted as reported; XP for wins is
     * capped per day to limit what a forged request can earn.
     *
     * @return array{xp: int, wins: int}
     */
    public function handle(User $user, string $userColor, int $level, GameResult $result, string $pgn, int $moveCount): array
    {
        /** @var list<int> $levelXp */
        $levelXp = config('chessflow.game.xp');
        $level = max(0, min(count($levelXp) - 1, $level));

        return DB::transaction(function () use ($user, $userColor, $level, $result, $pgn, $moveCount, $levelXp) {
            $game = Game::create([
                'user_id' => $user->id,
                'user_color' => $userColor === 'b' ? 'b' : 'w',
                'level' => $level,
                'result' => $result,
                'pgn' => mb_substr($pgn, 0, 20000),
                'move_count' => max(0, min($moveCount, 2000)),
                'finished_at' => now(),
            ]);

            $xp = 0;
            if ($result === GameResult::Win && $this->rewardedWinsToday($user) < (int) config('chessflow.game.rewarded_wins_per_day')) {
                $xp = $this->awardXp->handle($user, $levelXp[$level], $game);
            }

            return ['xp' => $xp, 'wins' => $user->games()->where('result', GameResult::Win)->count()];
        });
    }

    private function rewardedWinsToday(User $user): int
    {
        $start = Chessflow::now()->startOfDay();

        return $user->xpEvents()
            ->where('source_type', (new Game)->getMorphClass())
            ->where('created_at', '>=', $start)
            ->count();
    }
}
