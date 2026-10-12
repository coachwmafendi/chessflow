<?php

namespace App\Support;

/**
 * Turns a lesson step from data/lessons.json into something an admin can read at a glance:
 * a small static board and a list of type-specific details (answers, solution, goal).
 * Read-only: the JSON stays the source of truth.
 */
class LessonStepView
{
    private const FILES = 'abcdefgh';

    /** @return array<string, string> step type => label */
    public static function typeLabels(): array
    {
        return [
            'explain' => __('Penerangan'),
            'find' => __('Cari petak'),
            'collect' => __('Kutip bintang'),
            'tap' => __('Tekan petak'),
            'quiz' => __('Kuiz'),
            'puzzle' => __('Teka-teki'),
            'play' => __('Main'),
        ];
    }

    /**
     * 64 squares from rank 8 down to rank 1 (flipped when the step is oriented for Black).
     *
     * @param  array<string, mixed>  $step
     * @return list<array{name: string, light: bool, piece: ?string, hl: bool, ring: bool, dot: bool, star: bool, from: bool}>
     */
    public static function board(array $step): array
    {
        $pieces = isset($step['fen']) ? self::fenPieces((string) $step['fen']) : self::pcsPieces((string) ($step['pcs'] ?? ''));
        $hl = self::expand($step['hl'] ?? []);
        $ring = self::expand(array_merge($step['ring'] ?? [], $step['hintRing'] ?? []));
        $dots = self::expand($step['dots'] ?? []);
        $stars = self::expand($step['stars'] ?? []);
        $from = array_filter([$step['piece'] ?? null, $step['showMoves'] ?? null], fn ($s) => is_string($s) && strlen($s) === 2);

        $out = [];
        $ranks = ($step['orient'] ?? 'w') === 'b' ? range(1, 8) : range(8, 1);
        $files = ($step['orient'] ?? 'w') === 'b' ? array_reverse(str_split(self::FILES)) : str_split(self::FILES);
        foreach ($ranks as $r) {
            foreach ($files as $f) {
                $name = $f.$r;
                $out[] = [
                    'name' => $name,
                    'light' => (strpos(self::FILES, $f) + $r) % 2 === 0,
                    'piece' => $pieces[$name] ?? null,
                    'hl' => in_array($name, $hl, true),
                    'ring' => in_array($name, $ring, true),
                    'dot' => in_array($name, $dots, true),
                    'star' => in_array($name, $stars, true),
                    'from' => in_array($name, $from, true),
                ];
            }
        }

        return $out;
    }

    /**
     * Type-specific facts worth checking, as [label, html] rows. Lesson text is trusted repo content
     * (the island renders it as HTML too); everything else is escaped here.
     *
     * @param  array<string, mixed>  $step
     * @return list<array{0: string, 1: string}>
     */
    public static function details(array $step): array
    {
        $e = fn ($v) => e((string) $v);
        $moves = fn (array $l) => implode(' ', array_map(fn ($u) => '<code>'.e(self::move((string) $u)).'</code>', $l));
        $squares = fn (array $l) => implode(' ', array_map(fn ($s) => '<code>'.e((string) $s).'</code>', $l));
        $rows = [];

        switch ($step['type'] ?? '') {
            case 'explain':
                if (! empty($step['showMoves'])) {
                    $rows[] = [__('Tunjuk langkah buah di'), $squares([$step['showMoves']])];
                }
                if (! empty($step['demo'])) {
                    $rows[] = [__('Demo'), $moves($step['demo'])];
                }
                if (! empty($step['demoEnd'])) {
                    $rows[] = [__('Selepas demo'), (string) $step['demoEnd']];
                }
                break;
            case 'find':
                $rows[] = [__('Buah di'), $squares([$step['piece'] ?? ''])];
                if (! empty($step['done'])) {
                    $rows[] = [__('Bila siap'), (string) $step['done']];
                }
                break;
            case 'collect':
                $rows[] = [__('Buah di'), $squares([$step['piece'] ?? ''])];
                $rows[] = [__('Bintang'), $squares($step['stars'] ?? [])];
                break;
            case 'tap':
                $items = array_map(fn (array $i) => '<li>'.$i['ask'].' → '.$squares($i['sq'] ?? []).(! empty($i['all']) ? ' <small>('.e(__('semua')).')</small>' : '').'</li>', $step['seq'] ?? []);
                $rows[] = [__('Soalan'), '<ol>'.implode('', $items).'</ol>'];
                break;
            case 'quiz':
                $items = array_map(fn (array $o) => '<li class="'.(! empty($o['ok']) ? 'ok' : '').'">'.(! empty($o['ok']) ? '✓ ' : '✗ ').$o['t'].(! empty($o['why']) ? '<br><small>'.$o['why'].'</small>' : '').'</li>', $step['options'] ?? []);
                $rows[] = [__('Pilihan'), '<ul class="quiz">'.implode('', $items).'</ul>'];
                break;
            case 'puzzle':
                if (! empty($step['line'])) {
                    $rows[] = [__('Penyelesaian'), $moves($step['line'])];
                }
                if (! empty($step['acceptSan'])) {
                    $rows[] = [__('Juga diterima'), $squares($step['acceptSan'])];
                }
                if (! empty($step['acceptMate'])) {
                    $rows[] = [__('Diterima'), $e(__('Mana-mana sah mati'))];
                }
                if (! empty($step['accept'])) {
                    $rows[] = [__('Syarat diterima'), $e(self::acceptText($step['accept']))];
                }
                foreach (['mid' => __('Selepas langkah pertama'), 'wrongMsg' => __('Bila salah'), 'win' => __('Bila betul')] as $k => $label) {
                    if (! empty($step[$k])) {
                        $rows[] = [$label, (string) $step[$k]];
                    }
                }
                break;
            case 'play':
                $goals = ['mate' => __('Sah mati'), 'promote' => __('Promosi bidak'), 'captureQ' => __('Makan Menteri')];
                $bots = ['flee' => __('lari'), 'chase' => __('kejar'), 'defend' => __('bertahan')];
                $rows[] = [__('Matlamat'), $e(($goals[$step['goal'] ?? ''] ?? $step['goal'] ?? '').' · '.__('maksimum :n langkah', ['n' => $step['maxMoves'] ?? '?']))];
                $rows[] = [__('Lawan'), $e($bots[$step['bot'] ?? ''] ?? $step['bot'] ?? '')];
                if (! empty($step['win'])) {
                    $rows[] = [__('Bila menang'), (string) $step['win']];
                }
                break;
        }

        return $rows;
    }

    /**
     * Puzzle `accept` rules in words, e.g. {"piece":"n"} -> "Any Knight move".
     *
     * @param  array<string, mixed>  $a
     */
    public static function acceptText(array $a): string
    {
        $names = ['k' => __('Raja'), 'q' => __('Menteri'), 'r' => __('Tir'), 'b' => __('Gajah'), 'n' => __('Kuda'), 'p' => __('Bidak')];
        $piece = fn ($p) => $names[strtolower((string) $p)] ?? (string) $p;
        $parts = [];
        if (isset($a['piece'])) {
            $parts[] = __('langkah :piece', ['piece' => $piece($a['piece'])]);
        }
        if (isset($a['notPiece'])) {
            $parts[] = __('bukan :piece', ['piece' => $piece($a['notPiece'])]);
        }
        if (! empty($a['to'])) {
            $parts[] = __('ke :sq', ['sq' => implode(', ', (array) $a['to'])]);
        }
        if (! empty($a['toFile'])) {
            $parts[] = __('ke lajur :f', ['f' => implode(', ', (array) $a['toFile'])]);
        }
        if (! empty($a['capture'])) {
            $parts[] = __('mesti makan');
        }
        if (! empty($a['noMateInOne'])) {
            $parts[] = __('lawan tiada sah mati dalam 1');
        }

        return ucfirst(implode(' · ', $parts ?: [__('mana-mana langkah sah')]));
    }

    /** "e2e4" -> "e2→e4", "e7e8q" -> "e7→e8=Q"; SAN passes through. */
    public static function move(string $m): string
    {
        if (preg_match('/^([a-h][1-8])([a-h][1-8])([qrbn])?$/', $m, $x)) {
            return $x[1].'→'.$x[2].(isset($x[3]) ? '='.strtoupper($x[3]) : '');
        }

        return $m;
    }

    /** @return array<string, string> square => piece code, e.g. e4 => wN */
    private static function pcsPieces(string $pcs): array
    {
        $out = [];
        foreach (preg_split('/\s+/', trim($pcs)) ?: [] as $t) {
            if (strlen($t) === 4) {
                $out[substr($t, 2)] = $t[0].strtoupper($t[1]);
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    private static function fenPieces(string $fen): array
    {
        $out = [];
        foreach (explode('/', explode(' ', $fen)[0]) as $i => $row) {
            $f = 0;
            foreach (str_split($row) as $ch) {
                if (ctype_digit($ch)) {
                    $f += (int) $ch;

                    continue;
                }
                $out[self::FILES[$f].(8 - $i)] = (ctype_upper($ch) ? 'w' : 'b').strtoupper($ch);
                $f++;
            }
        }

        return $out;
    }

    /**
     * Square specs including "file:e" / "rank:4" lines.
     *
     * @param  array<int, mixed>  $specs
     * @return list<string>
     */
    private static function expand(array $specs): array
    {
        $out = [];
        foreach ($specs as $s) {
            if (! is_string($s)) {
                continue;
            }
            if (str_starts_with($s, 'file:')) {
                foreach (range(1, 8) as $r) {
                    $out[] = $s[5].$r;
                }
            } elseif (str_starts_with($s, 'rank:')) {
                foreach (str_split(self::FILES) as $f) {
                    $out[] = $f.$s[5];
                }
            } else {
                $out[] = $s;
            }
        }

        return $out;
    }
}
