<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class Locale
{
    public const COOKIE = 'locale';

    /**
     * @return array<string, string> code => name shown in the switcher
     */
    public static function available(): array
    {
        /** @var array<string, string> $locales */
        $locales = config('chessflow.locales');

        return $locales;
    }

    public static function default(): string
    {
        return (string) array_key_first(self::available());
    }

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && array_key_exists($locale, self::available());
    }

    public static function forRequest(Request $request): string
    {
        $user = $request->user();
        $preferred = $user instanceof User ? ($user->preferences['locale'] ?? null) : null;
        $cookie = $request->cookie(self::COOKIE);

        foreach ([$preferred, $cookie] as $candidate) {
            if (self::isSupported($candidate)) {
                return $candidate;
            }
        }

        return self::default();
    }

    public static function apply(string $locale): void
    {
        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }

    /** The other language, for the one-click header switch. */
    public static function other(): string
    {
        $codes = array_keys(self::available());

        return $codes[0] === app()->getLocale() ? ($codes[1] ?? $codes[0]) : $codes[0];
    }
}
