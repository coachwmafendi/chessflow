<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ordered list of published lessons and the unlock rule from MIGRATION_PLAN §4:
 * lesson n is open when lesson n-1 (by position) is completed, or the user is staff,
 * or a teacher has assigned it to one of the user's classes.
 */
class Curriculum
{
    /** @var Collection<int, Lesson>|null */
    private ?Collection $lessons = null;

    /** @var array<int, array<int, bool>> lesson ids assigned to each user's classes, by user id */
    private array $assigned = [];

    /**
     * @return Collection<int, Lesson>
     */
    public function lessons(): Collection
    {
        return $this->lessons ??= Lesson::query()
            ->where('is_published', true)
            ->orderBy('position')
            ->get();
    }

    /**
     * Lesson ids the user has completed.
     *
     * @return array<int, bool>
     */
    public function completedIds(User $user): array
    {
        return $user->lessonProgress()
            ->whereNotNull('completed_at')
            ->pluck('lesson_id')
            ->mapWithKeys(fn (int $id) => [$id => true])
            ->all();
    }

    /**
     * Lessons set as an assignment in any class the user belongs to.
     *
     * @return array<int, bool>
     */
    public function assignedIds(User $user): array
    {
        return $this->assigned[$user->id] ??= Assignment::query()
            ->whereIn('classroom_id', $user->classrooms()->pluck('classrooms.id'))
            ->pluck('lesson_id')
            ->mapWithKeys(fn (int $id) => [$id => true])
            ->all();
    }

    public function previous(Lesson $lesson): ?Lesson
    {
        $lessons = $this->lessons()->values();
        $index = $lessons->search(fn (Lesson $l) => $l->id === $lesson->id);

        return is_int($index) && $index > 0 ? $lessons[$index - 1] : null;
    }

    public function next(Lesson $lesson): ?Lesson
    {
        $lessons = $this->lessons()->values();
        $index = $lessons->search(fn (Lesson $l) => $l->id === $lesson->id);

        return is_int($index) ? $lessons->get($index + 1) : null;
    }

    /**
     * @param  array<int, bool>|null  $completed  pass completedIds() when checking many lessons
     */
    public function isUnlocked(User $user, Lesson $lesson, ?array $completed = null): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if (! $lesson->is_published) {
            return false;
        }

        $previous = $this->previous($lesson);
        if ($previous === null || isset($this->assignedIds($user)[$lesson->id])) {
            return true;
        }

        $completed ??= $this->completedIds($user);

        return isset($completed[$previous->id]);
    }
}
