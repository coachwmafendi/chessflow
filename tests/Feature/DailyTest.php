<?php

use App\Actions\CompleteDaily;
use App\Actions\PickDailyPuzzle;
use App\Models\DailyPuzzle;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\LessonSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->student = User::factory()->student()->create();
});

afterEach(fn () => Carbon::setTestNow());

it('grows the streak across consecutive Malaysian days', function () {
    $daily = app(CompleteDaily::class);

    Carbon::setTestNow(Carbon::parse('2026-10-08 23:30', 'Asia/Kuala_Lumpur'));
    expect($daily->handle($this->student))->toMatchArray(['streak' => 1, 'xp' => 15, 'alreadyDone' => false]);

    Carbon::setTestNow(Carbon::parse('2026-10-09 00:30', 'Asia/Kuala_Lumpur'));
    expect($daily->handle($this->student->fresh()))->toMatchArray(['streak' => 2, 'xp' => 15]);

    expect($this->student->fresh())
        ->streak_current->toBe(2)
        ->streak_best->toBe(2)
        ->xp->toBe(30);
});

it('does not count the same day twice', function () {
    $daily = app(CompleteDaily::class);
    Carbon::setTestNow(Carbon::parse('2026-10-08 09:00', 'Asia/Kuala_Lumpur'));

    $daily->handle($this->student);
    $again = $daily->handle($this->student->fresh());

    expect($again)->toMatchArray(['streak' => 1, 'xp' => 0, 'alreadyDone' => true])
        ->and($this->student->fresh()->xp)->toBe(15);
});

it('resets the streak after a missed day but keeps the best', function () {
    $daily = app(CompleteDaily::class);

    foreach (['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-05'] as $day) {
        Carbon::setTestNow(Carbon::parse($day.' 10:00', 'Asia/Kuala_Lumpur'));
        $result = $daily->handle($this->student->fresh());
    }

    expect($result['streak'])->toBe(1)
        ->and($this->student->fresh()->streak_best)->toBe(3);
});

it('shows a lapsed streak as zero', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00', 'Asia/Kuala_Lumpur'));
    app(CompleteDaily::class)->handle($this->student);

    Carbon::setTestNow(Carbon::parse('2026-10-02 10:00', 'Asia/Kuala_Lumpur'));
    expect($this->student->fresh()->currentStreak())->toBe(1);

    Carbon::setTestNow(Carbon::parse('2026-10-03 10:00', 'Asia/Kuala_Lumpur'));
    expect($this->student->fresh()->currentStreak())->toBe(0);
});

it('only offers suitable puzzles from the daily lessons', function () {
    $bank = app(PickDailyPuzzle::class)->bank();

    expect($bank)->not->toBeEmpty();
    $bank->each(function (array $c) {
        $lesson = Lesson::find($c['lesson_id']);
        expect(config('chessflow.daily.lessons'))->toContain($lesson->slug)
            ->and(PickDailyPuzzle::suitable($lesson->steps[$c['step_index']]))->toBeTrue();
    });
});

it('picks one puzzle per day without repeats inside 30 days', function () {
    $pick = app(PickDailyPuzzle::class);
    $size = $pick->bank()->count();
    $days = min($size, 30);

    $start = Carbon::parse('2026-10-01');
    $keys = collect(range(0, $days - 1))->map(function (int $i) use ($pick, $start) {
        $p = $pick->handle($start->copy()->addDays($i)->toDateString());

        return $p->lesson_id.':'.$p->step_index;
    });

    expect($keys->unique()->count())->toBe($days)
        ->and($pick->handle('2026-10-01')->id)->toBe(DailyPuzzle::whereDate('date', '2026-10-01')->value('id'))
        ->and(DailyPuzzle::count())->toBe($days);
});

it('schedules the daily pick at 00:05 Kuala Lumpur time', function () {
    $this->artisan('chessflow:pick-daily', ['--date' => '2026-10-08'])->assertSuccessful();
    expect(DailyPuzzle::count())->toBe(1);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'chessflow:pick-daily'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('5 0 * * *')
        ->and($event->timezone)->toBe('Asia/Kuala_Lumpur');
});
