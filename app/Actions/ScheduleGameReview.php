<?php

namespace App\Actions;

use App\Models\Game;
use App\Models\ReviewItem;
use App\Models\User;
use App\Support\Chessflow;
use App\Support\PgnMoves;

/**
 * Queues the positions before the player's mistakes in a game (found by the post-game review in the browser)
 * for "Latih semula" tomorrow. The browser's report is checked against the stored game: the mistake must be the
 * player's own move at that ply, and the position and moves must look like chess. A daily cap keeps a forged
 * report from filling the queue.
 */
class ScheduleGameReview
{
    private const UCI = '/^[a-h][1-8][a-h][1-8][qrbn]?$/';

    private const SAN = '/^(?:O-O(?:-O)?|[KQRBN]?[a-h]?[1-8]?x?[a-h][1-8](?:=[QRBN])?)[+#]?$/';

    /**
     * @param  array<mixed>  $mistakes  [{ply, fen, best, bestSan, also, played}], as reported by the browser
     * @return int number of positions queued
     */
    public function handle(User $user, Game $game, array $mistakes): int
    {
        abort_unless($game->user_id === $user->id, 403);

        $moves = PgnMoves::san($game->pgn);
        $userParity = $game->user_color === 'w' ? 0 : 1;
        $max = (int) config('chessflow.review.game_positions_per_game');
        $room = (int) config('chessflow.review.game_positions_per_day') - $user->reviewItems()
            ->whereNotNull('game_id')->where('created_at', '>=', Chessflow::now()->startOfDay())->count();
        $tomorrow = Chessflow::now()->addDay()->toDateString();

        $queued = 0;
        foreach (array_slice($mistakes, 0, $max) as $m) {
            if ($queued >= $room || ! is_array($m)) {
                break;
            }
            $ply = $m['ply'] ?? null;
            $fen = is_string($m['fen'] ?? null) ? trim($m['fen']) : '';
            $best = $m['best'] ?? null;
            $bestSan = $m['bestSan'] ?? null;
            $played = $m['played'] ?? null;
            $also = array_values(array_filter((array) ($m['also'] ?? []), fn ($s) => is_string($s) && preg_match(self::SAN, $s)));

            $valid = is_int($ply) && $ply % 2 === $userParity && ($moves[$ply] ?? null) === $played
                && self::looksLikeFen($fen, $game->user_color)
                // Games against Pak Kuda start from the normal position, so the FEN's move number follows from the ply.
                && (int) (explode(' ', $fen)[5] ?? 0) === intdiv($ply, 2) + 1
                && is_string($best) && preg_match(self::UCI, $best)
                && is_string($bestSan) && preg_match(self::SAN, $bestSan) && $bestSan !== $played;
            if (! $valid) {
                continue;
            }

            ReviewItem::updateOrCreate(
                ['user_id' => $user->id, 'game_id' => $game->id, 'ply' => $ply],
                [
                    'position' => ['fen' => $fen, 'best' => $best, 'bestSan' => $bestSan, 'also' => array_slice($also, 0, 3), 'played' => $played],
                    'box' => 1,
                    'due_on' => $tomorrow,
                ],
            );
            $queued++;
        }

        return $queued;
    }

    /** Six FEN fields, eight ranks of eight squares, one king each, and the player to move. */
    public static function looksLikeFen(string $fen, string $toMove): bool
    {
        $f = preg_split('/\s+/', $fen) ?: [];
        if (count($f) !== 6 || $f[1] !== $toMove || ! preg_match('/^[KQkq-]+$/', $f[2]) || ! preg_match('/^(-|[a-h][36])$/', $f[3])) {
            return false;
        }
        $ranks = explode('/', $f[0]);
        if (count($ranks) !== 8) {
            return false;
        }
        foreach ($ranks as $r) {
            if (! preg_match('/^[pnbrqkPNBRQK1-8]+$/', $r)) {
                return false;
            }
            $width = 0;
            foreach (str_split($r) as $ch) {
                $width += ctype_digit($ch) ? (int) $ch : 1;
            }
            if ($width !== 8) {
                return false;
            }
        }

        return substr_count($f[0], 'K') === 1 && substr_count($f[0], 'k') === 1;
    }
}
