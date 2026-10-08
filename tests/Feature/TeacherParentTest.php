<?php

use App\Actions\CompleteLesson;
use App\Actions\CreateStudents;
use App\Enums\Role;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Support\ProgressReport;
use Database\Seeders\LessonSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->parent = User::factory()->create();
});

function classroomFor(User $teacher): Classroom
{
    return Classroom::create(['teacher_id' => $teacher->id, 'name' => '4 Bestari', 'join_code' => Classroom::newJoinCode()]);
}

it('lets teachers create a class with a join code', function () {
    Livewire::actingAs($this->teacher)->test('pages::guru.index')
        ->set('name', '4 Bestari')
        ->call('create')
        ->assertRedirect();

    $classroom = Classroom::firstOrFail();
    expect($classroom->teacher_id)->toBe($this->teacher->id)
        ->and($classroom->join_code)->toMatch('/^[2-9A-HJ-NP-Z]{6}$/');
});

it('keeps parents and students out of teacher pages', function () {
    $this->actingAs($this->parent)->get(route('guru'))->assertForbidden();
    $this->actingAs(User::factory()->student()->create())->get(route('guru'))->assertForbidden();

    $other = classroomFor(User::factory()->teacher()->create());
    $this->actingAs($this->teacher)->get(route('guru.kelas', $other))->assertForbidden();
});

it('creates student accounts in bulk that can log in with their PIN', function () {
    $classroom = classroomFor($this->teacher);

    $component = Livewire::actingAs($this->teacher)->test('pages::guru.kelas', ['classroom' => $classroom])
        ->set('names', "Aina Sofea\n\n  Badrul  \nChong Wei")
        ->call('addStudents');

    $credentials = $component->get('credentials');
    expect($credentials)->toHaveCount(3)
        ->and($classroom->students()->count())->toBe(3)
        ->and(collect($credentials)->pluck('username')->unique())->toHaveCount(3)
        ->and($credentials[0]['username'])->toStartWith('aina');

    $aina = User::find($credentials[0]['id']);
    expect($aina->role)->toBe(Role::Murid)
        ->and($aina->email)->toBeNull()
        ->and(Hash::check($credentials[0]['pin'], $aina->pin))->toBeTrue();

    Livewire::test('pages::masuk-murid')
        ->set('username', $credentials[0]['username'])->set('pin', $credentials[0]['pin'])->call('login')
        ->assertRedirect(route('peta'));
});

it('caps a batch at 40 students', function () {
    $created = app(CreateStudents::class)->handle($this->teacher, array_map(fn ($i) => "Murid $i", range(1, 50)), classroomFor($this->teacher));

    expect($created)->toHaveCount(CreateStudents::MAX_PER_BATCH);
});

it('shows class progress and resets a PIN only for the teacher\'s own students', function () {
    $classroom = classroomFor($this->teacher);
    [$aina] = app(CreateStudents::class)->handle($this->teacher, ['Aina'], $classroom);
    $student = User::find($aina['id']);
    app(CompleteLesson::class)->handle($student, Lesson::where('slug', 'papan')->first(), 2);

    $this->actingAs($this->teacher)->get(route('guru.kelas', $classroom))
        ->assertOk()->assertSee('Aina')->assertSee('★★')->assertSee($classroom->join_code);

    $component = Livewire::actingAs($this->teacher)->test('pages::guru.kelas', ['classroom' => $classroom])
        ->call('resetPin', $student->id);
    $newPin = $component->get('credentials')[0]['pin'];
    expect(Hash::check($newPin, $student->fresh()->pin))->toBeTrue();

    $outsider = User::factory()->student()->create();
    Livewire::actingAs($this->teacher)->test('pages::guru.kelas', ['classroom' => $classroom])
        ->call('resetPin', $outsider->id)
        ->assertNotFound();
});

it('lets a student join a class with its code', function () {
    $classroom = classroomFor($this->teacher);
    $student = User::factory()->student()->create();

    Livewire::actingAs($student)->test('pages::sertai')->set('code', 'nope00')->call('join')->assertHasErrors('code');
    Livewire::actingAs($student)->test('pages::sertai')->set('code', strtolower($classroom->join_code))->call('join')
        ->assertHasNoErrors()->assertSet('joined', '4 Bestari');

    expect($classroom->students()->pluck('users.id')->all())->toBe([$student->id])
        ->and($this->teacher->canSeeStudent($student))->toBeTrue();
});

it('lets parents create, link and unlink children', function () {
    $component = Livewire::actingAs($this->parent)->test('pages::anak')->set('childName', 'Aina')->call('createChild');
    $created = $component->get('credentials')[0];
    expect($this->parent->students()->pluck('users.id')->all())->toBe([$created['id']]);

    $child = User::factory()->student()->create(['pin' => '4321']);
    Livewire::actingAs($this->parent)->test('pages::anak')
        ->set('username', $child->username)->set('pin', '1111')->call('linkChild')->assertHasErrors('username');
    Livewire::actingAs($this->parent)->test('pages::anak')
        ->set('username', $child->username)->set('pin', '4321')->call('linkChild')->assertHasNoErrors();

    expect($this->parent->canSeeStudent($child))->toBeTrue();
    $this->actingAs($this->parent)->get(route('anak'))->assertOk()->assertSee($child->name);

    Livewire::actingAs($this->parent)->test('pages::anak')->call('unlink', $child->id);
    expect($this->parent->canSeeStudent($child))->toBeFalse()
        ->and(User::find($child->id))->not->toBeNull();
});

it('keeps students out of the parent page', function () {
    $this->actingAs(User::factory()->student()->create())->get(route('anak'))->assertForbidden();
});

it('summarises progress for a list of students', function () {
    $student = User::factory()->student()->create();
    $papan = Lesson::where('slug', 'papan')->first();
    app(CompleteLesson::class)->handle($student, $papan, 0);

    $row = app(ProgressReport::class)->forStudents(collect([$student->fresh()]))->first();

    expect($row['stars'])->toBe([$papan->id => 3])
        ->and($row['done'])->toBe(1)
        ->and($row['examsPassed'])->toBe(0)
        ->and($row['lastActive'])->not->toBeNull();
});
