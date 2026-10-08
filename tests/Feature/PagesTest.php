<?php

use App\Models\Certificate;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\LessonSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->student = User::factory()->student()->create();
});

it('sends guests to login', function () {
    $this->get(route('peta'))->assertRedirect(route('login'));
    $this->get(route('pelajaran', 'papan'))->assertRedirect(route('login'));
});

it('shows the map with only the first lesson open', function () {
    $this->actingAs($this->student)
        ->get(route('peta'))
        ->assertOk()
        ->assertSee('Mula: Papan Catur', false)
        ->assertSee('Kenal Kuda, terkunci', false);
});

it('blocks locked lessons', function () {
    $this->actingAs($this->student)->get(route('pelajaran', 'kuda'))->assertForbidden();
    $this->actingAs($this->student)->get(route('pelajaran', 'papan'))->assertOk()->assertSee('data-chessflow="lesson"', false);
});

it('completes a lesson through the Livewire bridge and unlocks the next one', function () {
    $papan = Lesson::where('slug', 'papan')->first();

    Livewire::actingAs($this->student)
        ->test('pages::pelajaran', ['lesson' => $papan])
        ->dispatch('lesson-completed', slug: 'papan', mistakes: 0, failedSteps: [], durationMs: 1000, xp: 99999)
        ->assertDispatched('lesson-result', fn (string $name, array $p) => $p['xp'] === $papan->xp
            && $p['stars'] === 3
            && $p['next']['url'] === route('pelajaran', 'kuda'));

    expect($this->student->fresh()->xp)->toBe($papan->xp);
    $this->actingAs($this->student)->get(route('pelajaran', 'kuda'))->assertOk();
});

it('issues a certificate for a passed level exam', function () {
    $teacherOpened = User::factory()->teacher()->create();
    $exam = Lesson::where('slug', 'ujian1')->first();

    Livewire::actingAs($teacherOpened)
        ->test('pages::pelajaran', ['lesson' => $exam])
        ->dispatch('lesson-completed', slug: 'ujian1', mistakes: 0, failedSteps: [0])
        ->assertDispatched('lesson-result', fn (string $name, array $p) => $p['passed'] === true && str_contains((string) $p['certificateUrl'], '/sijil/'));

    $certificate = Certificate::firstOrFail();
    $this->get(route('sijil', $certificate->code))->assertOk()->assertSee('Sijil sah', false)->assertSee($certificate->code);
});

it('lets only the owner rename a certificate', function () {
    $owner = User::factory()->teacher()->create();
    Livewire::actingAs($owner)
        ->test('pages::pelajaran', ['lesson' => Lesson::where('slug', 'ujian1')->first()])
        ->dispatch('lesson-completed', failedSteps: []);
    $code = Certificate::firstOrFail()->code;

    Livewire::actingAs($owner)->test('pages::sijil', ['code' => $code])
        ->set('displayName', 'Aina')
        ->call('saveName')
        ->assertHasNoErrors();
    expect(Certificate::first()->display_name)->toBe('Aina');

    Livewire::actingAs($this->student)->test('pages::sijil', ['code' => $code])
        ->set('displayName', 'Bukan Aina')
        ->call('saveName')
        ->assertForbidden();
});

it('records the daily puzzle and the game through their pages', function () {
    $this->actingAs($this->student)->get(route('harian'))->assertOk()->assertSee('data-chessflow="lesson"', false);

    Livewire::actingAs($this->student)->test('pages::harian')
        ->dispatch('lesson-completed', slug: 'harian', mistakes: 2)
        ->assertDispatched('lesson-result', fn (string $name, array $p) => $p['xp'] === 15 && $p['streak'] === 1);

    Livewire::actingAs($this->student)->test('pages::main')
        ->dispatch('game-finished', userColor: 'w', level: 1, result: 'win', pgn: '1. e4', moveCount: 1)
        ->assertDispatched('game-result', xp: 50, wins: 1, totalXp: 65);

    Livewire::actingAs($this->student)->test('pages::main')
        ->dispatch('game-finished', result: 'cheat')
        ->assertNotDispatched('game-result');
});

it('logs a student in with username and PIN', function () {
    Livewire::test('pages::masuk-murid')
        ->set('username', $this->student->username)
        ->set('pin', '1234')
        ->call('login')
        ->assertRedirect(route('peta'));

    $this->assertAuthenticatedAs($this->student);
});

it('rejects a wrong PIN and adults using the PIN form', function () {
    $adult = User::factory()->create(['username' => 'ibu', 'pin' => '1234']);

    Livewire::test('pages::masuk-murid')
        ->set('username', $this->student->username)->set('pin', '9999')->call('login')
        ->assertHasErrors('username');
    Livewire::test('pages::masuk-murid')
        ->set('username', 'ibu')->set('pin', '1234')->call('login')
        ->assertHasErrors('username');

    $this->assertGuest();
});

it('rate limits student login attempts', function () {
    foreach (range(1, 5) as $i) {
        Livewire::test('pages::masuk-murid')->set('username', $this->student->username)->set('pin', '0000')->call('login');
    }

    Livewire::test('pages::masuk-murid')
        ->set('username', $this->student->username)->set('pin', '1234')->call('login')
        ->assertHasErrors('username');
    $this->assertGuest();
});

it('creates students from the console', function () {
    $guardian = User::factory()->create(['email' => 'ibu@example.com']);

    $this->artisan('chessflow:create-student', ['username' => 'Aina', 'name' => 'Aina', '--pin' => '4321', '--guardian' => 'ibu@example.com'])
        ->assertSuccessful();

    $aina = User::where('username', 'aina')->firstOrFail();
    expect($aina->email)->toBeNull()
        ->and($aina->hasVerifiedEmail())->toBeTrue()
        ->and($guardian->students()->pluck('users.id')->all())->toBe([$aina->id]);

    $this->artisan('chessflow:create-student', ['username' => 'aina', 'name' => 'X', '--pin' => '12'])->assertFailed();
});
