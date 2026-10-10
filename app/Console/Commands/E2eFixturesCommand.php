<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\ReviewItem;
use App\Models\User;
use App\Support\Chessflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

#[Signature('chessflow:e2e-fixtures')]
#[Description('Reset the Playwright test accounts (local/testing only) and print their fresh credentials as JSON')]
class E2eFixturesCommand extends Command
{
    public const STUDENT = 'e2e.murid';

    /** A second student for read-only page checks (accessibility), so they never race the feature specs. */
    public const VIEWER = 'e2e.pemerhati';

    public const TEACHER = 'e2e.guru@chessflow.test';

    public const CLASSROOM = 'Kelas E2E';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('chessflow:e2e-fixtures only runs in the local or testing environment.');

            return self::FAILURE;
        }

        // Fresh secrets every run: nothing to keep in the repository.
        $pin = (string) random_int(100000, 999999);
        $viewerPin = (string) random_int(100000, 999999);
        $password = Str::random(24);

        $data = DB::transaction(function () use ($pin, $viewerPin, $password) {
            $teacher = User::firstOrNew(['email' => self::TEACHER]);
            $teacher->forceFill(['name' => 'Cikgu E2E', 'role' => Role::Guru, 'password' => $password, 'email_verified_at' => now()])->save();

            $student = User::firstOrNew(['username' => self::STUDENT]);
            $student->forceFill([
                'name' => 'Murid E2E', 'role' => Role::Murid, 'pin' => $pin, 'password' => Str::random(40),
                'xp' => 0, 'streak_current' => 0, 'streak_best' => 0, 'streak_last_date' => null,
            ])->save();

            // Start from a clean slate.
            $student->lessonProgress()->delete();
            $student->examAttempts()->delete();
            $student->dailyCompletions()->delete();
            $student->games()->delete();
            $student->xpEvents()->delete();
            $student->reviewItems()->delete();
            $student->badges()->delete();
            $teacher->taughtClassrooms()->delete(); // cascades to memberships and assignments

            $lesson = fn (string $slug) => Lesson::where('slug', $slug)->firstOrFail();

            // One finished lesson, no badge rows yet: the map should celebrate "Langkah Pertama".
            LessonProgress::create(['user_id' => $student->id, 'lesson_id' => $lesson('papan')->id, 'best_stars' => 3, 'last_stars' => 3, 'mistakes' => 0, 'attempts' => 1, 'completed_at' => now()]);

            // One step due for "Latih semula": Nilai Buah, quiz "Untung atau rugi?" (answer: "Untung 2 mata").
            ReviewItem::create(['user_id' => $student->id, 'lesson_id' => $lesson('nilai')->id, 'step_index' => 1, 'box' => 1, 'due_on' => Chessflow::today()]);

            // A class with an assignment the student has not reached on the map yet.
            $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => self::CLASSROOM, 'join_code' => Classroom::newJoinCode()]);
            $classroom->students()->attach($student->id);

            $viewer = User::firstOrNew(['username' => self::VIEWER]);
            $viewer->forceFill(['name' => 'Pemerhati E2E', 'role' => Role::Murid, 'pin' => $viewerPin, 'password' => Str::random(40)])->save();
            $viewer->reviewItems()->delete();
            ReviewItem::create(['user_id' => $viewer->id, 'lesson_id' => $lesson('nilai')->id, 'step_index' => 1, 'box' => 1, 'due_on' => Chessflow::today()]);
            $classroom->students()->attach($viewer->id);
            $teacher->students()->syncWithoutDetaching([$viewer->id]); // so the teacher's Anak page has a row too
            Assignment::create(['classroom_id' => $classroom->id, 'lesson_id' => $lesson('fork')->id, 'due_on' => Chessflow::now()->addDays(3)->toDateString(), 'note' => 'Tugasan E2E']);

            return ['classroomId' => $classroom->id];
        });

        // Earlier runs must not leave the accounts rate limited.
        RateLimiter::clear('student-login:'.self::STUDENT.'|127.0.0.1');
        RateLimiter::clear('student-login:'.self::VIEWER.'|127.0.0.1');
        // Fortify's "login" limiter counts every sign-in, so back-to-back runs (3 teacher logins each) hit its 5 a minute.
        $loginKey = self::TEACHER.'|127.0.0.1';
        RateLimiter::clear(md5('login'.$loginKey));
        RateLimiter::clear('login:'.$loginKey);

        $this->line((string) json_encode([
            'student' => ['username' => self::STUDENT, 'pin' => $pin],
            'viewer' => ['username' => self::VIEWER, 'pin' => $viewerPin],
            'teacher' => ['email' => self::TEACHER, 'password' => $password],
            'classroomId' => $data['classroomId'],
        ]));

        return self::SUCCESS;
    }
}
