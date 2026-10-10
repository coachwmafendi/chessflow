<?php

use App\Models\Certificate;
use App\Models\Lesson;
use App\Models\LessonProgress;
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

    $page = Livewire::actingAs($this->student)->test('pages::pelajaran', ['lesson' => $papan]);
    $this->travel(2)->minutes();
    $page->dispatch('lesson-completed', slug: 'papan', mistakes: 0, failedSteps: [], durationMs: 1000, xp: 99999)
        ->assertDispatched('lesson-result', fn (string $name, array $p) => $p['xp'] === $papan->xp
            && $p['stars'] === 3
            && $p['next']['url'] === route('pelajaran', 'kuda'));

    expect($this->student->fresh()->xp)->toBe($papan->xp);
    $this->actingAs($this->student)->get(route('pelajaran', 'kuda'))->assertOk();
});

it('issues a certificate for a passed level exam', function () {
    $teacherOpened = User::factory()->teacher()->create();
    $exam = Lesson::where('slug', 'ujian1')->first();

    $page = Livewire::actingAs($teacherOpened)->test('pages::pelajaran', ['lesson' => $exam]);
    $this->travel(2)->minutes();
    $page->dispatch('lesson-completed', slug: 'ujian1', mistakes: 0, failedSteps: [0])
        ->assertDispatched('lesson-result', fn (string $name, array $p) => $p['passed'] === true && str_contains((string) $p['certificateUrl'], '/sijil/'));

    $certificate = Certificate::firstOrFail();
    $this->get(route('sijil', $certificate->code))->assertOk()->assertSee('Sijil sah', false)->assertSee($certificate->code);
});

it('lets only the owner rename a certificate', function () {
    $owner = User::factory()->teacher()->create();
    $page = Livewire::actingAs($owner)->test('pages::pelajaran', ['lesson' => Lesson::where('slug', 'ujian1')->first()]);
    $this->travel(2)->minutes();
    $page->dispatch('lesson-completed', failedSteps: []);
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

it('does not record a lesson finished impossibly fast', function () {
    $papan = Lesson::where('slug', 'papan')->first();

    Livewire::actingAs($this->student)->test('pages::pelajaran', ['lesson' => $papan])
        ->dispatch('lesson-completed', slug: 'papan', mistakes: 0)
        ->assertDispatched('lesson-result', fn (string $name, array $p) => $p['xp'] === 0 && isset($p['message']));

    expect($this->student->fresh()->xp)->toBe(0)
        ->and($this->student->lessonProgress()->count())->toBe(0);
});

it('rate limits lesson completions per user', function () {
    config(['chessflow.limits.lessons_per_minute' => 2]);
    $papan = Lesson::where('slug', 'papan')->first();

    $page = Livewire::actingAs($this->student)->test('pages::pelajaran', ['lesson' => $papan]);
    $this->travel(2)->minutes();
    foreach (range(1, 3) as $i) {
        $page->dispatch('lesson-completed', slug: 'papan', mistakes: 0);
    }

    expect($this->student->lessonProgress()->first()->attempts)->toBe(2);
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

it('shows the landing page with live curriculum numbers and support email', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Kenal Catur')
        ->assertSee('Endgame')
        ->assertSee('Fork Kuda')
        ->assertSee('mailto:wmafendi@gmail.com', false)
        ->assertSee(route('murid.login'), false);
});

it('sends signed-in users from the landing page to the map', function () {
    $this->actingAs($this->student)->get(route('home'))->assertRedirect(route('peta'));
});

it('checks a certificate code from the landing page', function () {
    Livewire::test('pages::utama')
        ->set('certCode', ' abc234 ')
        ->call('checkCertificate')
        ->assertRedirect(route('sijil', 'ABC234'));

    Livewire::test('pages::utama')
        ->set('certCode', 'ab-12')
        ->call('checkCertificate')
        ->assertHasErrors('certCode')
        ->assertNoRedirect();
});

it('shares a preview image and canonical url', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.asset('images/og-chessflow.png').'"', false)
        ->assertSee('<link rel="canonical" href="'.url('/').'"', false)
        ->assertSee('<link rel="apple-touch-icon"', false);
});

it('offers sign-in links to guests in the header only', function () {
    $this->get(route('tentang'))->assertSee('>Log masuk</a>', false);
    $this->actingAs($this->student)->get(route('tentang'))->assertDontSee('>Log masuk</a>', false);
});

it('serves robots.txt and a sitemap of public pages', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap: '.route('sitemap'));

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>'.route('tentang').'</loc>', false)
        ->assertDontSee(route('peta'));
});

it('applies the saved colour theme before paint and offers a theme toggle', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee("localStorage.getItem('flux.appearance')", false)
        ->assertSee('data-theme-toggle', false);
});

it('publishes the privacy notice in Malay and English, and the terms', function () {
    $this->get(route('privasi'))
        ->assertOk()
        ->assertSee('Akta Perlindungan Data Peribadi 2010')
        ->assertSee('Privacy Notice (English)')
        ->assertSee('mailto:wmafendi@gmail.com', false);

    $this->get(route('terma'))->assertOk()->assertSee('percuma dan tanpa iklan untuk tempoh terhad', false)->assertSee('WM AFENDI ENTERPRISE');
    $this->get('/sitemap.xml')->assertSee('<loc>'.route('privasi').'</loc>', false);
});

it('tells people about the terms and privacy where accounts are made, and the price on the landing page', function () {
    $this->get(route('register'))->assertSee(route('privasi'), false)->assertSee(route('terma'), false);
    $this->get(route('home'))->assertSee('Percuma untuk tempoh terhad')->assertSee('Berapa kos?');
});

it('lists the chess terms in Malay and English, with notation', function () {
    $this->get(route('istilah'))
        ->assertOk()
        ->assertSeeInOrder(['Kuda', 'Knight', 'N'])
        ->assertSeeInOrder(['Sah mati', 'Checkmate', '#'])
        ->assertSee('Discovered attack');
    $this->get('/sitemap.xml')->assertSee('<loc>'.route('istilah').'</loc>', false);
});

it('introduces each piece with its English name in the lessons', function () {
    foreach (['kuda' => 'Knight', 'gajah' => 'Bishop', 'tir' => 'Rook', 'bidak' => 'Pawn'] as $slug => $english) {
        expect(Lesson::where('slug', $slug)->firstOrFail()->steps[0]['say'])->toContain('<i>'.$english.'</i>');
    }
});

it('folds finished and locked levels on the map and keeps the current one open', function () {
    $levelOne = Lesson::where('slug', 'papan')->firstOrFail()->level_id;
    Lesson::where('level_id', $levelOne)->get()->each(function (Lesson $l) {
        LessonProgress::create(['user_id' => $this->student->id, 'lesson_id' => $l->id, 'best_stars' => 3, 'last_stars' => 3, 'mistakes' => 0, 'attempts' => 1, 'completed_at' => now()]);
    });

    $html = $this->actingAs($this->student)->get(route('peta'))->assertOk()->getContent();

    expect($html)->toMatch('/<details class="tahap tahap-done" data-level="1"\s*>/')   // finished: folded
        ->toMatch('/<details class="tahap tahap-current" data-level="2"\s+open\s*>/')      // being worked on: open
        ->toMatch('/<details class="tahap tahap-locked" data-level="3"\s*>/')           // not reached: folded
        ->toContain('data-map-store="chessflow-map:'.$this->student->id.'"');
});

it('opens every level for staff, who have every lesson unlocked', function () {
    $teacher = User::factory()->teacher()->create();
    $html = $this->actingAs($teacher)->get(route('peta'))->getContent();

    expect(substr_count($html, 'class="tahap tahap-current"'))->toBe(5);
});
