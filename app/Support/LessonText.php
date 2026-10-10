<?php

namespace App\Support;

/**
 * Lesson content is written in Bahasa Melayu; translations sit next to it under a locale key
 * (`"en": {...}`) in data/lessons.json. This merges a translation over the Malay text: any field
 * the translation leaves out stays in Malay, and lists (options, seq, mids…) merge item by item.
 */
class LessonText
{
    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    public static function step(array $step, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $translation = $step[$locale] ?? null;
        $codes = array_keys(Locale::available());

        // Never send the raw translation blocks to the browser.
        $step = array_diff_key($step, array_flip($codes));

        return is_array($translation) && $locale !== Locale::default()
            ? self::merge($step, $translation)
            : $step;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return list<array<string, mixed>>
     */
    public static function steps(array $steps, ?string $locale = null): array
    {
        return array_map(fn (array $s) => self::step($s, $locale), $steps);
    }

    /**
     * @param  array<array-key, mixed>|null  $i18n  e.g. ['en' => ['title' => '…']]
     */
    public static function field(?array $i18n, string $field, ?string $original, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $value = $i18n[$locale][$field] ?? null;

        return is_string($value) && $value !== '' ? $value : $original;
    }

    /**
     * @param  array<array-key, mixed>  $base
     * @param  array<array-key, mixed>  $over
     * @return array<array-key, mixed>
     */
    private static function merge(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } elseif ($value !== null && $value !== '') {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
