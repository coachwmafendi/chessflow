<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ordered list of published lessons and the unlock rule from MIGRATION_PLAN §4:
 * lesson n is open when lesson n-1 (by position) is completed, or the user is staff.
 */
class Curriculum
{
    /** @var Collection<int, Lesson>|null */
    private ?Collection $lessons = null;

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
        if ($previous === null) {
            return true;
        }

        $completed ??= $this->completedIds($user);

        return isset($completed[$previous->id]);
    }
}
