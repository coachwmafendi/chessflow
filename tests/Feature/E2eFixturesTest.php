<?php

use App\Models\Assignment;
use App\Models\ReviewItem;
use App\Models\User;
use Database\Seeders\LessonSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->seed(LessonSeeder::class));

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(Artisan::call('chessflow:e2e-fixtures'))->toBe(1)
        ->and(User::where('username', 'e2e.murid')->exists())->toBeFalse();
});

it('creates fresh test accounts with working credentials, every run', function () {
    Artisan::call('chessflow:e2e-fixtures');
    $first = json_decode(trim(Artisan::output()), true);
    Artisan::call('chessflow:e2e-fixtures');
    $second = json_decode(trim(Artisan::output()), true);

    $student = User::where('username', 'e2e.murid')->sole();
    expect($first['student']['pin'])->not->toBe($second['student']['pin'])
        ->and(Hash::check($second['student']['pin'], $student->pin))->toBeTrue()
        ->and(Hash::check($second['teacher']['password'], User::where('email', 'e2e.guru@chessflow.test')->sole()->password))->toBeTrue()
        ->and($student->reviewItems()->count())->toBe(1)
        ->and(Assignment::count())->toBe(1)
        ->and(ReviewItem::count())->toBe(2); // the student and the read-only viewer
});
