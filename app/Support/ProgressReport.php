<?php

namespace App\Support;

use App\Models\DailyCompletion;
use App\Models\ExamAttempt;
use App\Models\Game;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-student summary for teacher and parent dashboards: stars per lesson, XP, streak,
 * exams passed and last activity. Queries are batched for the whole list of students.
 */
class ProgressReport
{
    /**
     * @param  Collection<int, User>  $students
     * @return Collection<int, array{student: User, stars: array<int, int>, done: int, examsPassed: int, streak: int, lastActive: Carbon|null}>
     */
    public function forStudents(Collection $students): Collection
    {
        $ids = $students->pluck('id')->all();
        if ($ids === []) {
            return collect();
        }

        $progress = LessonProgress::whereIn('user_id', $ids)->whereNotNull('completed_at')->get()->groupBy('user_id');
        $examsPassed = ExamAttempt::whereIn('user_id', $ids)->where('passed', true)
            ->selectRaw('user_id, count(distinct lesson_id) as n')->groupBy('user_id')->pluck('n', 'user_id');

        $last = collect([
            LessonProgress::whereIn('user_id', $ids)->selectRaw('user_id, max(updated_at) as t')->groupBy('user_id')->pluck('t', 'user_id'),
            ExamAttempt::whereIn('user_id', $ids)->selectRaw('user_id, max(created_at) as t')->groupBy('user_id')->pluck('t', 'user_id'),
            DailyCompletion::whereIn('user_id', $ids)->selectRaw('user_id, max(solved_at) as t')->groupBy('user_id')->pluck('t', 'user_id'),
            Game::whereIn('user_id', $ids)->selectRaw('user_id, max(finished_at) as t')->groupBy('user_id')->pluck('t', 'user_id'),
        ]);

        return $students->values()->map(function (User $student) use ($progress, $examsPassed, $last) {
            /** @var Collection<int, LessonProgress> $rows */
            $rows = $progress->get($student->id, collect());
            $times = $last->map(fn (Collection $c) => $c->get($student->id))->filter();

            return [
                'student' => $student,
                'stars' => $rows->mapWithKeys(fn (LessonProgress $p) => [$p->lesson_id => $p->best_stars])->all(),
                'done' => $rows->count(),
                'examsPassed' => (int) $examsPassed->get($student->id, 0),
                'streak' => $student->currentStreak(),
                'lastActive' => $times->isEmpty() ? null : Carbon::parse((string) $times->max()),
            ];
        });
    }
}
