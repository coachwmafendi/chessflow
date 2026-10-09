<?php

use App\Actions\AwardBadges;
use App\Actions\CompleteLesson;
use App\Actions\RecordGame;
use App\Enums\GameResult;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\Badges;
use Database\Seeders\LessonSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->student = User::factory()->student()->create();
});

it('has a complete catalogue', function () {
    foreach (Badges::all() as $key => $b) {
        expect($key)->toMatch('/^[a-z0-9-]{1,40}$/')
            ->and($b['name'])->not->toBeEmpty()
            ->and(array_key_exists($b['stat'], Badges::stats($this->student)))->toBeTrue("unknown stat {$b['stat']} for {$key}");
    }
});

it('awards a badge once, when its threshold is reached', function () {
    expect(app(AwardBadges::class)->handle($this->student))->toBe([]);

    app(CompleteLesson::class)->handle($this->student, Lesson::where('slug', 'papan')->firstOrFail(), 0);
    $new = app(AwardBadges::class)->handle($this->student);

    expect(collect($new)->pluck('key')->all())->toBe(['pelajaran-pertama'])
        ->and(app(AwardBadges::class)->handle($this->student))->toBe([])
        ->and(UserBadge::count())->toBe(1);
});

it('reads game badges by level and result', function () {
    $record = app(RecordGame::class);
    $record->handle($this->student, 'w', 1, GameResult::Win, '', 30);
    $record->handle($this->student, 'w', 0, GameResult::Draw, '', 40);

    $keys = collect(app(AwardBadges::class)->handle($this->student))->pluck('key')->all();

    expect($keys)->toContain('menang-pertama', 'pencabar', 'sama-kuat')
        ->not->toContain('penakluk');
});

it('needs every exam for the champion badge', function () {
    $stats = ['exams' => 4, 'exams_total' => 5];
    $juara = Badges::all()['juara'];

    expect(Badges::earned($juara, $stats))->toBeFalse()
        ->and(Badges::earned($juara, ['exams' => 5, 'exams_total' => 5]))->toBeTrue()
        ->and(Badges::progress($juara, $stats))->toBe(['have' => 4, 'need' => 5]);
});

it('sends new badges with the lesson result', function () {
    $papan = Lesson::where('slug', 'papan')->firstOrFail();
    $page = Livewire::actingAs($this->student)->test('pages::pelajaran', ['lesson' => $papan]);
    $this->travel(2)->minutes();

    $page->dispatch('lesson-completed', slug: 'papan', mistakes: 0, failedSteps: [], durationMs: 1000)
        ->assertDispatched('lesson-result', fn (string $name, array $p) => collect($p['badges'])->pluck('name')->contains('Langkah Pertama'));
});

it('catches up on the map and celebrates unseen badges only once', function () {
    app(CompleteLesson::class)->handle($this->student, Lesson::where('slug', 'papan')->firstOrFail(), 0);

    $this->actingAs($this->student)->get(route('peta'))
        ->assertSee('Lencana baru!')
        ->assertSee('Langkah Pertama');
    $this->actingAs($this->student)->get(route('peta'))
        ->assertDontSee('Lencana baru!')
        ->assertSee('Lencana');
});

it('shows earned and locked badges with progress on /lencana', function () {
    $this->student->forceFill(['xp' => 250])->save();

    $this->actingAs($this->student)->get(route('lencana'))
        ->assertOk()
        ->assertSee('0/14 dikumpul')
        ->assertSee('Seribu XP')
        ->assertSee('250/1000');
});
