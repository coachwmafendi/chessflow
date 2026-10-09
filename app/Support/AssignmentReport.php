<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Assignments of a class (teacher view) or of a student's classes (map), with who has finished.
 * "Done" means the lesson is completed (an exam: passed), before or after it was assigned.
 */
class AssignmentReport
{
    /**
     * @return Collection<int, array{assignment: Assignment, done: non-negative-int, total: non-negative-int, pending: EloquentCollection<int, User>}>
     */
    public function forClassroom(Classroom $classroom): Collection
    {
        $assignments = $classroom->assignments()->with('lesson')->get()->sortBy([
            fn (Assignment $a, Assignment $b) => ($a->due_on === null) <=> ($b->due_on === null),
            fn (Assignment $a, Assignment $b) => $a->due_on?->getTimestamp() <=> $b->due_on?->getTimestamp(),
            fn (Assignment $a, Assignment $b) => $b->id <=> $a->id,
        ])->values();
        $students = $classroom->students()->orderBy('name')->get();

        $done = LessonProgress::whereIn('user_id', $students->pluck('id'))
            ->whereIn('lesson_id', $assignments->pluck('lesson_id'))
            ->whereNotNull('completed_at')
            ->get(['user_id', 'lesson_id'])
            ->groupBy('lesson_id')
            ->map(fn (Collection $rows) => $rows->pluck('user_id')->flip());

        return $assignments->map(function (Assignment $a) use ($students, $done) {
            $finished = $done->get($a->lesson_id, collect());

            return [
                'assignment' => $a,
                'done' => $students->filter(fn (User $s) => $finished->has($s->id))->count(),
                'total' => $students->count(),
                'pending' => $students->reject(fn (User $s) => $finished->has($s->id))->values(),
            ];
        });
    }

    /**
     * Open work first (by due date), then recently finished ones; at most $limit.
     *
     * @return Collection<int, array{assignment: Assignment, done: bool}>
     */
    public function forStudent(User $student, int $limit = 6): Collection
    {
        $assignments = Assignment::query()
            ->whereIn('classroom_id', $student->classrooms()->pluck('classrooms.id'))
            ->with(['lesson', 'classroom'])
            ->get();
        $completed = $student->lessonProgress()->whereNotNull('completed_at')->pluck('lesson_id')->flip();

        return $assignments
            ->map(fn (Assignment $a) => ['assignment' => $a, 'done' => $completed->has($a->lesson_id)])
            // Finished assignments drop off once their due date (or two weeks after setting) has passed.
            ->reject(fn (array $r) => $r['done'] && ($r['assignment']->isOverdue() || ($r['assignment']->due_on === null && $r['assignment']->created_at?->lt(now()->subWeeks(2)))))
            ->sortBy([
                fn (array $a, array $b) => $a['done'] <=> $b['done'],
                fn (array $a, array $b) => ($a['assignment']->due_on === null) <=> ($b['assignment']->due_on === null),
                fn (array $a, array $b) => $a['assignment']->due_on?->getTimestamp() <=> $b['assignment']->due_on?->getTimestamp(),
            ])
            ->take($limit)
            ->values();
    }
}
