<?php

use App\Models\Lesson;
use App\Models\Level;
use App\Models\User;
use App\Support\Locale;

function lessonsWithEnglish(): string
{
    $data = json_decode((string) file_get_contents(base_path('data/lessons.json')), true);
    $data['levels'][0]['en'] = ['name' => 'Meet Chess'];
    $data['lessons'][0]['en'] = ['title' => 'The Chessboard'];
    $data['lessons'][0]['steps'][2]['en'] = [
        'say' => 'Before you play, set the board up correctly.',
        'options' => [['t' => 'Light', 'why' => 'Right! Remember: light on the right.']],
    ];

    $path = (string) tempnam(sys_get_temp_dir(), 'lessons');
    file_put_contents($path, json_encode($data));
    register_shutdown_function(fn () => @unlink($path));

    return $path;
}

beforeEach(function () {
    $this->artisan('chessflow:import-lessons', ['--path' => lessonsWithEnglish()])->assertSuccessful();
    $this->papan = Lesson::where('slug', 'papan')->firstOrFail();
});

afterEach(fn () => Locale::apply('ms'));

it('keeps Malay as the source and shows English where it exists', function () {
    expect($this->papan->title)->toBe('Papan Catur')
        ->and(Level::where('number', 1)->first()->name)->toBe('Kenal Catur');

    Locale::apply('en');

    expect($this->papan->fresh()->title)->toBe('The Chessboard')
        ->and(Level::where('number', 1)->first()->name)->toBe('Meet Chess')
        ->and(Lesson::where('slug', 'kuda')->first()->title)->toBe('Kenal Kuda'); // no translation yet → Malay
});

it('merges step translations field by field and never leaks the raw blocks', function () {
    $ms = $this->papan->localizedSteps()[2];
    expect($ms)->not->toHaveKey('en')
        ->and($ms['say'])->toBe($this->papan->steps[2]['say']);

    Locale::apply('en');
    $en = $this->papan->localizedSteps()[2];

    expect($en)->not->toHaveKey('en')
        ->and($en['say'])->toBe('Before you play, set the board up correctly.')
        ->and($en['options'][0]['t'])->toBe('Light')
        ->and($en['options'][0]['ok'])->toBeTrue()                        // logic kept from Malay
        ->and($en['options'][1]['t'])->toBe($this->papan->steps[2]['options'][1]['t'])   // untranslated item stays Malay
        ->and($en['task'])->toBe($this->papan->steps[2]['task'])
        ->and($en['type'])->toBe('quiz');
});

it('sends the translated lesson to the board for English users', function () {
    $student = User::factory()->student()->create(['preferences' => ['locale' => 'en']]);

    $this->actingAs($student)->get(route('pelajaran', 'papan'))
        ->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('The Chessboard')
        ->assertSee('Before you play, set the board up correctly.')
        ->assertDontSee('&quot;en&quot;:{', false);
});

it('bumps the content version when only a translation changes', function () {
    $before = $this->papan->content_version;
    $data = json_decode((string) file_get_contents(lessonsWithEnglish()), true);
    $data['lessons'][0]['en']['tip'] = 'Light square on the right!';
    $path = (string) tempnam(sys_get_temp_dir(), 'lessons');
    file_put_contents($path, json_encode($data));

    $this->artisan('chessflow:import-lessons', ['--path' => $path])->assertSuccessful();
    @unlink($path);

    expect($this->papan->fresh()->content_version)->toBe($before + 1);
});
