<?php

use App\Actions\CompleteLesson;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Chessflow;
use Database\Seeders\LessonSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->classroom = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => '5 Cerdik', 'join_code' => Classroom::newJoinCode()]);
    $this->aina = User::factory()->student()->create(['name' => 'Aina']);
    $this->badrul = User::factory()->student()->create(['name' => 'Badrul']);
    $this->classroom->students()->attach([$this->aina->id, $this->badrul->id]);
    $this->fork = Lesson::where('slug', 'fork')->firstOrFail();
});

it('lets the teacher set a lesson for the class and update it', function () {
    $due = Chessflow::now()->addDays(3)->toDateString();

    Livewire::actingAs($this->teacher)->test('pages::guru.kelas', ['classroom' => $this->classroom])
        ->set('lessonId', $this->fork->id)->set('dueOn', $due)->set('note', 'Sebelum kelab Jumaat')
        ->call('assign')
        ->assertHasNoErrors()
        ->assertSet('assignedMsg', 'Tugasan diberi: Fork Kuda.')
        ->assertSee('0/2')
        ->set('lessonId', $this->fork->id)->set('dueOn', '')
        ->call('assign')
        ->assertSet('assignedMsg', 'Tugasan dikemas kini: Fork Kuda.');

    $a = Assignment::sole();
    expect($a->due_on)->toBeNull()->and($a->note)->toBeNull();
});

it('rejects a due date in the past', function () {
    Livewire::actingAs($this->teacher)->test('pages::guru.kelas', ['classroom' => $this->classroom])
        ->set('lessonId', $this->fork->id)->set('dueOn', Chessflow::yesterday())
        ->call('assign')
        ->assertHasErrors('dueOn');

    expect(Assignment::count())->toBe(0);
});

it('keeps other teachers out', function () {
    $other = User::factory()->teacher()->create();

    Livewire::actingAs($other)->test('pages::guru.kelas', ['classroom' => $this->classroom])->assertForbidden();
});

it('opens an assigned lesson for students in the class only', function () {
    $outsider = User::factory()->student()->create();
    $this->actingAs($this->aina)->get(route('pelajaran', $this->fork))->assertForbidden();

    Assignment::create(['classroom_id' => $this->classroom->id, 'lesson_id' => $this->fork->id]);
    $this->app->forgetScopedInstances(); // a new request in production

    $this->actingAs($this->aina)->get(route('pelajaran', $this->fork))->assertOk();
    $this->actingAs($outsider)->get(route('pelajaran', $this->fork))->assertForbidden();
});

it('shows the teacher who has finished', function () {
    Assignment::create(['classroom_id' => $this->classroom->id, 'lesson_id' => $this->fork->id]);
    app(CompleteLesson::class)->handle($this->aina, $this->fork, 0);

    Livewire::actingAs($this->teacher)->test('pages::guru.kelas', ['classroom' => $this->classroom])
        ->assertSee('1/2')
        ->assertSee('Belum siap (1)')
        ->assertSee('Badrul');
});

it('lists the work on the student map with its state', function () {
    $papan = Lesson::where('slug', 'papan')->firstOrFail();
    Assignment::create(['classroom_id' => $this->classroom->id, 'lesson_id' => $this->fork->id, 'due_on' => Chessflow::now()->addDays(2)->toDateString(), 'note' => 'Untuk kelab']);
    Assignment::create(['classroom_id' => $this->classroom->id, 'lesson_id' => $papan->id, 'due_on' => Chessflow::now()->subDay()->toDateString()]);

    $this->actingAs($this->aina)->get(route('peta'))
        ->assertOk()
        ->assertSee('Tugasan daripada guru')
        ->assertSee('Untuk kelab')
        ->assertSee('Lewat')
        ->assertSeeInOrder(['Papan Catur', 'Fork Kuda']); // overdue first: earlier due date

    // Finished and past its due date: drops off the list.
    app(CompleteLesson::class)->handle($this->aina, $papan, 0);
    $this->actingAs($this->aina)->get(route('peta'))->assertDontSee('Lewat');
});

it('relocks the lesson when the assignment is removed', function () {
    $a = Assignment::create(['classroom_id' => $this->classroom->id, 'lesson_id' => $this->fork->id]);

    Livewire::actingAs($this->teacher)->test('pages::guru.kelas', ['classroom' => $this->classroom])
        ->call('unassign', $a->id);

    expect(Assignment::count())->toBe(0);
    $this->app->forgetScopedInstances();
    $this->actingAs($this->aina)->get(route('pelajaran', $this->fork))->assertForbidden();
});
