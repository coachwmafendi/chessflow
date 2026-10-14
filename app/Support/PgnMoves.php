<?php

namespace App\Support;

/**
 * The moves of a stored game (PGN from chess.js) as plain SAN, e.g. ['e4', 'e5', 'Nf3'], for the
 * analysis board link (?moves=e4 e5 Nf3). The board replays them with chess.js, which rejects anything illegal.
 */
class PgnMoves
{
    private const SAN = '/^(?:O-O(?:-O)?|[KQRBN]?[a-h]?[1-8]?x?[a-h][1-8](?:=[QRBN])?)[+#]?$/';

    /** @return list<string> */
    public static function san(string $pgn): array
    {
        $text = preg_replace(['/\[[^\]]*\]/', '/\{[^}]*\}/', '/;[^\n]*/', '/\$\d+/'], ' ', $pgn) ?? '';
        // Variations can nest: strip the innermost ones until none are left.
        do {
            $text = preg_replace('/\([^()]*\)/', ' ', $text, -1, $n) ?? '';
        } while ($n > 0);

        $moves = [];
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $token) {
            $token = preg_replace('/^\d+\.(\.\.)?/', '', $token) ?? ''; // "12." / "12..." glued to the move
            $token = str_replace('0-0', 'O-O', rtrim($token, '!?'));
            if ($token !== '' && preg_match(self::SAN, $token)) {
                $moves[] = $token;
            }
        }

        return $moves;
    }

    /** "1. e4 e5 2. Nf3 …" for a short preview of how the game started. */
    public static function opening(string $pgn, int $plies = 6): string
    {
        $moves = array_slice(self::san($pgn), 0, $plies);
        $out = [];
        foreach ($moves as $i => $m) {
            $out[] = ($i % 2 === 0 ? (intdiv($i, 2) + 1).'. ' : '').$m;
        }

        return implode(' ', $out).(count(self::san($pgn)) > $plies ? ' …' : '');
    }
}
