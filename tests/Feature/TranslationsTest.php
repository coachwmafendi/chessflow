<?php

use App\Models\User;
use App\Support\Badges;
use App\Support\Locale;
use Illuminate\Support\Facades\File;

/**
 * UI strings are written in Bahasa Melayu inside __(); lang/en.json holds the English.
 * Keys already in lang/ms.json are the English source strings of Laravel/Fortify views.
 *
 * @return array<string, string> key => file it was found in
 */
function translationKeys(): array
{
    $files = collect(['resources/views', 'app'])
        ->flatMap(fn (string $dir) => File::allFiles(base_path($dir)))
        ->filter(fn ($f) => str_ends_with($f->getFilename(), '.php'));

    $keys = [];
    foreach ($files as $file) {
        // __('…') calls and #[Title('…')] page titles (translated by the layouts).
        preg_match_all("/(?:__|#\\[Title)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $file->getContents(), $m);
        foreach ($m[1] as $key) {
            $keys[stripslashes($key)] = $file->getRelativePathname();
        }
    }

    return $keys;
}

it('has an English translation for every Malay UI string', function () {
    $ms = json_decode((string) file_get_contents(lang_path('ms.json')), true);
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true);

    $missing = collect(translationKeys())
        ->reject(fn ($file, $key) => array_key_exists($key, $ms) || array_key_exists($key, $en) || str_contains($key, '.') && trans()->has($key, 'en'))
        ->map(fn ($file, $key) => "{$file}: {$key}")
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('translates every badge', function () {
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true);

    $missing = collect(Badges::all())
        ->flatMap(fn (array $b) => [$b['name'], $b['description']])
        ->reject(fn (string $text) => array_key_exists($text, $en))
        ->values()->all();

    expect($missing)->toBe([]);
});

it('defaults to Bahasa Melayu', function () {
    $this->get(route('home'))->assertOk()->assertSee('<html lang="ms"', false);
});

it('switches language with a cookie for guests', function () {
    $this->post(route('locale.switch', 'en'))->assertRedirect()->assertCookie(Locale::COOKIE, 'en');

    $this->withCookie(Locale::COOKIE, 'en')->get(route('home'))->assertSee('<html lang="en"', false);
});

it('remembers the language on the account', function () {
    $user = User::factory()->student()->create();

    $this->actingAs($user)->post(route('locale.switch', 'en'))->assertRedirect();

    expect($user->fresh()->preferences['locale'])->toBe('en');
    $this->actingAs($user->fresh())->get(route('peta'))->assertSee('<html lang="en"', false);
});

it('rejects unknown languages', function () {
    $this->post(route('locale.switch', 'fr'))->assertNotFound();
    $this->withCookie(Locale::COOKIE, 'fr')->get(route('home'))->assertSee('<html lang="ms"', false);
});
