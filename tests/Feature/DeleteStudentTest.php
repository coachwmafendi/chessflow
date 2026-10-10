<?php

use App\Actions\CompleteLesson;
use App\Actions\DeleteStudent;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Database\Seeders\LessonSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->parent = User::factory()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->classroom = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => '6 Arif', 'join_code' => Classroom::newJoinCode()]);
    $this->child = User::factory()->student()->create(['name' => 'Aina']);
});

it('lets a parent delete their child with all of the child\'s data and sessions', function () {
    $this->parent->students()->attach($this->child->id);
    app(CompleteLesson::class)->handle($this->child, Lesson::where('slug', 'papan')->firstOrFail(), 0);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $this->child->id, 'payload' => '', 'last_activity' => time()]);

    Livewire::actingAs($this->parent)->test('pages::anak')
        ->call('deleteChild', $this->child->id)
        ->assertSet('notice', 'Akaun Aina dan semua datanya telah dipadam.');

    expect(User::find($this->child->id))->toBeNull()
        ->and(LessonProgress::where('user_id', $this->child->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $this->child->id)->exists())->toBeFalse()
        ->and(User::find($this->parent->id))->not->toBeNull();
});

it('lets a teacher delete a student only they look after', function () {
    $this->classroom->students()->attach($this->child->id);

    Livewire::actingAs($this->teacher)->test('pages::guru.kelas', ['classroom' => $this->classroom])
        ->assertSee('Padam akaun')
        ->call('deleteStudent', $this->child->id)
        ->assertSet('notice', 'Akaun Aina dan semua datanya telah dipadam.');

    expect(User::find($this->child->id))->toBeNull();
});

it('keeps a teacher from deleting a student a parent or another teacher also looks after', function () {
    $this->classroom->students()->attach($this->child->id);
    $this->parent->students()->attach($this->child->id);

    Livewire::actingAs($this->teacher)->test('pages::guru.kelas', ['classroom' => $this->classroom])
        ->assertDontSee('Padam akaun')
        ->call('deleteStudent', $this->child->id)
        ->assertSet('notice', fn (string $n) => str_contains($n, 'diurus oleh ibu bapa atau guru lain'));

    expect(User::find($this->child->id))->not->toBeNull();

    $this->parent->students()->detach($this->child->id);
    $other = Classroom::create(['teacher_id' => User::factory()->teacher()->create()->id, 'name' => 'Kelab', 'join_code' => Classroom::newJoinCode()]);
    $other->students()->attach($this->child->id);
    expect(app(DeleteStudent::class)->deniedReason($this->teacher, $this->child))->not->toBeNull();
});

it('never deletes someone else\'s child or an adult account', function () {
    $stranger = User::factory()->create();
    expect(fn () => app(DeleteStudent::class)->handle($stranger, $this->child))->toThrow(AuthorizationException::class);

    Livewire::actingAs($stranger)->test('pages::anak')->call('deleteChild', $this->child->id)->assertNotFound();

    $this->parent->students()->attach($this->teacher->id); // not a student
    expect(app(DeleteStudent::class)->deniedReason($this->parent, $this->teacher))->toBe('Hanya akaun murid boleh dipadam di sini.')
        ->and(User::count())->toBe(4);
});
