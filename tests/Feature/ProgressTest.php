<?php

use App\Actions\CompleteLesson;
use App\Actions\SubmitExam;
use App\Models\Certificate;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Curriculum;
use Database\Seeders\LessonSeeder;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->student = User::factory()->student()->create();
});

function lesson(string $slug): Lesson
{
    return Lesson::where('slug', $slug)->firstOrFail();
}

it('opens only the first lesson for a new student', function () {
    $curriculum = app(Curriculum::class);

    expect($curriculum->isUnlocked($this->student, lesson('papan')))->toBeTrue()
        ->and($curriculum->isUnlocked($this->student, lesson('kuda')))->toBeFalse()
        ->and($curriculum->isUnlocked($this->student, lesson('ujian1')))->toBeFalse();
});

it('unlocks the next lesson and awards XP from the database', function () {
    $result = app(CompleteLesson::class)->handle($this->student, lesson('papan'), 0);

    expect($result)->toMatchArray(['stars' => 3, 'xp' => lesson('papan')->xp, 'firstTime' => true])
        ->and($this->student->fresh()->xp)->toBe(lesson('papan')->xp)
        ->and($this->student->xpEvents()->count())->toBe(1)
        ->and(app(Curriculum::class)->isUnlocked($this->student->fresh(), lesson('kuda')))->toBeTrue()
        ->and(app(Curriculum::class)->isUnlocked($this->student->fresh(), lesson('gajah')))->toBeFalse();
});

it('gives 20% XP on replay and keeps the best stars', function () {
    $action = app(CompleteLesson::class);
    $xp = lesson('papan')->xp;

    $action->handle($this->student, lesson('papan'), 0);
    $replay = $action->handle($this->student, lesson('papan'), 6);

    $progress = $this->student->lessonProgress()->first();
    expect($replay)->toMatchArray(['stars' => 1, 'xp' => (int) round($xp * 0.2), 'firstTime' => false])
        ->and($progress->best_stars)->toBe(3)
        ->and($progress->last_stars)->toBe(1)
        ->and($progress->attempts)->toBe(2)
        ->and($this->student->fresh()->xp)->toBe($xp + (int) round($xp * 0.2));
});

it('maps mistakes to stars like the prototype', function (int $mistakes, int $stars) {
    expect(app(CompleteLesson::class)->handle($this->student, lesson('papan'), $mistakes)['stars'])->toBe($stars);
})->with([[0, 3], [1, 3], [2, 2], [4, 2], [5, 1]]);

it('passes an exam at 70% and issues one certificate per level', function () {
    $exam = lesson('ujian1');
    $total = count($exam->steps);
    $allowedWrong = $total - (int) ceil($total * 0.7);

    $result = app(SubmitExam::class)->handle($this->student, $exam, range(0, $allowedWrong - 1));

    expect($result['passed'])->toBeTrue()
        ->and($result['score'])->toBe($total - $allowedWrong)
        ->and($result['xp'])->toBe($exam->xp)
        ->and($result['certificateCode'])->toHaveLength(10);

    $again = app(SubmitExam::class)->handle($this->student, $exam, []);
    expect($again['certificateCode'])->toBe($result['certificateCode'])
        ->and($again['xp'])->toBe((int) round($exam->xp * 0.2))
        ->and(Certificate::count())->toBe(1);
});

it('records a failed exam without XP, progress or certificate', function () {
    $exam = lesson('ujian1');
    $total = count($exam->steps);

    $result = app(SubmitExam::class)->handle($this->student, $exam, range(0, $total - 1));

    expect($result)->toMatchArray(['passed' => false, 'score' => 0, 'xp' => 0, 'certificateCode' => null])
        ->and($this->student->examAttempts()->count())->toBe(1)
        ->and($this->student->lessonProgress()->count())->toBe(0)
        ->and($this->student->fresh()->xp)->toBe(0);
});

it('ignores bogus failed step indexes from the browser', function () {
    $exam = lesson('ujian1');

    $result = app(SubmitExam::class)->handle($this->student, $exam, [0, 0, -1, 999, 'x']);

    expect($result['score'])->toBe(count($exam->steps) - 1);
});

it('rejects lessons and exams sent to the wrong action', function () {
    expect(fn () => app(CompleteLesson::class)->handle($this->student, lesson('ujian1'), 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(SubmitExam::class)->handle($this->student, lesson('papan'), []))->toThrow(InvalidArgumentException::class);
});

it('lets teachers open every lesson', function () {
    $teacher = User::factory()->teacher()->create();

    expect(app(Curriculum::class)->isUnlocked($teacher, lesson('lucena')))->toBeTrue();
});
