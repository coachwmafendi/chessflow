<?php

use App\Actions\CompleteLesson;
use App\Enums\Role;
use App\Filament\Resources\Lessons\LessonResource;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Filament\Resources\Lessons\Pages\ViewLesson;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Widgets\ChessflowStats;
use App\Filament\Widgets\SignupsChart;
use App\Models\Lesson;
use App\Models\User;
use App\Support\LessonStepView;
use Database\Seeders\LessonSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    Filament::setCurrentPanel('admin');
});

it('lets only admins into the panel', function () {
    $this->actingAs($this->admin)->get('/admin/lessons')->assertOk();
    $this->actingAs(User::factory()->teacher()->create())->get('/admin/lessons')->assertForbidden();
});

it('lists lessons in curriculum order and shows a preview', function () {
    $papan = Lesson::where('slug', 'papan')->first();

    Livewire::actingAs($this->admin)->test(ListLessons::class)
        ->assertCanSeeTableRecords(Lesson::orderBy('position')->get(), inOrder: true);

    Livewire::actingAs($this->admin)->test(ViewLesson::class, ['record' => $papan->getRouteKey()])
        ->assertOk()
        ->assertSee(route('pratonton', $papan), false);

    $this->actingAs($this->admin)->get(route('pratonton', $papan))->assertOk()->assertSee('data-chessflow="lesson"', false);
    $this->actingAs(User::factory()->teacher()->create())->get(route('pratonton', $papan))->assertForbidden();
});

it('shows the dashboard stats and sign-up chart', function () {
    $student = User::factory()->create(['role' => Role::Murid, 'email' => null]);
    app(CompleteLesson::class)->handle($student, Lesson::where('slug', 'papan')->first(), 0);

    $this->actingAs($this->admin)->get('/admin')->assertOk();
    Livewire::actingAs($this->admin)->test(ChessflowStats::class)->assertOk()->assertSee(__('Pelajaran disiapkan'));
    Livewire::actingAs($this->admin)->test(SignupsChart::class)->assertOk();
    Livewire::actingAs($this->admin)->test(ManageUsers::class)->assertOk()->assertSee(__('Aktif terakhir'));
});

it('shows each step readably and can open the preview at a step', function () {
    $fork = Lesson::where('slug', 'fork')->first();

    Livewire::actingAs($this->admin)->test(ViewLesson::class, ['record' => $fork->getRouteKey()])
        ->assertOk()
        ->assertSee($fork->steps[1]['title'])
        ->assertSee('cf-preview-step', false)
        ->assertSee(LessonStepView::move($fork->steps[1]['line'][0]));

    $this->actingAs($this->admin)->get(route('pratonton', $fork).'?langkah=3')->assertSee('data-start="2"', false);
});

it('describes steps for the admin view', function () {
    $board = collect(LessonStepView::board(['fen' => '4k3/8/8/8/8/8/8/R3K3 w - - 0 1', 'ring' => ['e1'], 'hl' => ['rank:8']]))->keyBy('name');
    expect($board['a1'])->toMatchArray(['light' => false, 'piece' => 'wR'])
        ->and($board['h1']['light'])->toBeTrue()
        ->and($board['e8'])->toMatchArray(['piece' => 'bK', 'hl' => true])
        ->and($board['e1']['ring'])->toBeTrue();
    expect(collect(LessonStepView::board(['pcs' => '', 'orient' => 'b']))->first()['name'])->toBe('h1');
    expect(LessonStepView::move('e7e8q'))->toBe('e7→e8=Q')
        ->and(LessonStepView::acceptText(['piece' => 'n']))->toBe('Langkah Kuda');
});

it('publishes, hides and reorders lessons', function () {
    $kuda = Lesson::where('slug', 'kuda')->first();

    Livewire::actingAs($this->admin)->test(ListLessons::class)
        ->call('updateTableColumnState', 'is_published', (string) $kuda->getKey(), false);
    expect($kuda->fresh()->is_published)->toBeFalse();

    $ids = Lesson::orderBy('position')->pluck('id')->map(fn ($id) => (string) $id)->all();
    [$ids[0], $ids[1]] = [$ids[1], $ids[0]];
    Livewire::actingAs($this->admin)->test(ListLessons::class)->call('reorderTable', $ids);

    expect(Lesson::orderBy('position')->first()->slug)->toBe('kuda');
});

it('does not let admins create or edit lesson content', function () {
    expect(LessonResource::canCreate())->toBeFalse()
        ->and(LessonResource::getPages())->not->toHaveKey('edit');
});

it('lets admins promote a parent to teacher', function () {
    $user = User::factory()->create();

    Livewire::actingAs($this->admin)->test(ManageUsers::class)
        ->callTableAction('edit', $user, data: ['name' => $user->name, 'role' => Role::Guru->value])
        ->assertHasNoTableActionErrors();

    expect($user->fresh()->role)->toBe(Role::Guru);
});

it('sets roles from the console', function () {
    $user = User::factory()->create(['email' => 'cikgu@example.com']);

    $this->artisan('chessflow:set-role', ['email' => 'cikgu@example.com', 'role' => 'admin'])->assertSuccessful();
    expect($user->fresh()->role)->toBe(Role::Admin);

    $this->artisan('chessflow:set-role', ['email' => 'cikgu@example.com', 'role' => 'raja'])->assertFailed();
});
