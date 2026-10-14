<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The opening names the browser may report (public/openings/openings.json, lichess-org/chess-openings, CC0).
 * A game's opening is only stored when it is one of them.
 */
class Openings
{
    /** @return array<string, true> "C50 Italian Game" => true */
    private static function known(): array
    {
        $path = public_path('openings/openings.json');

        return Cache::remember('openings:'.(is_file($path) ? filemtime($path) : 0), now()->addDay(), function () use ($path) {
            $table = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];

            $keys = array_map(fn ($v) => $v[0].' '.$v[1], is_array($table) ? array_values($table) : []);

            return array_fill_keys($keys, true);
        });
    }

    /**
     * @return array{eco: string, name: string}|null
     */
    public static function check(mixed $opening): ?array
    {
        if (! is_array($opening) || ! is_string($opening['eco'] ?? null) || ! is_string($opening['name'] ?? null)) {
            return null;
        }

        return isset(self::known()[$opening['eco'].' '.$opening['name']]) ? ['eco' => $opening['eco'], 'name' => $opening['name']] : null;
    }
}
