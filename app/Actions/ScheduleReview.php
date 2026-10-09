<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\ReviewItem;
use App\Models\User;
use App\Support\Chessflow;

/** Queues the steps a student got wrong for "Latih semula" tomorrow (back to the first box if already queued). */
class ScheduleReview
{
    /**
     * @param  array<mixed>  $stepIndexes  as reported by the browser: anything that is not a reviewable step is ignored
     * @return int number of steps queued
     */
    public function handle(User $user, Lesson $lesson, array $stepIndexes): int
    {
        $types = (array) config('chessflow.review.step_types');
        $steps = $lesson->steps;
        $tomorrow = Chessflow::now()->addDay()->toDateString();

        $indexes = collect($stepIndexes)
            ->filter(fn ($i) => is_int($i) && isset($steps[$i]) && in_array($steps[$i]['type'] ?? null, $types, true))
            ->unique()
            ->values();

        foreach ($indexes as $i) {
            ReviewItem::updateOrCreate(
                ['user_id' => $user->id, 'lesson_id' => $lesson->id, 'step_index' => $i],
                ['box' => 1, 'due_on' => $tomorrow],
            );
        }

        return $indexes->count();
    }
}
