<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

class Chessflow
{
    public static function timezone(): string
    {
        return (string) config('chessflow.timezone');
    }

    /** Current local (Malaysia) time. */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::instance(Date::now(self::timezone()));
    }

    /** Today's local date as Y-m-d. */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    public static function yesterday(): string
    {
        return self::now()->subDay()->toDateString();
    }

    /** Stars for a finished lesson, same as the prototype. */
    public static function lessonStars(int $mistakes): int
    {
        return $mistakes <= 1 ? 3 : ($mistakes <= 4 ? 2 : 1);
    }

    /** Stars for a passed exam, same as the prototype. */
    public static function examStars(int $score, int $total): int
    {
        return $score === $total ? 3 : ($score >= $total - 1 ? 2 : 1);
    }

    public static function examPassMark(int $total): int
    {
        return (int) ceil($total * (float) config('chessflow.exam_pass_ratio'));
    }
}
