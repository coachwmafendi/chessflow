<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Support\Chessflow;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompleteLesson
{
    public function __construct(private AwardXp $awardXp) {}

    /**
     * @return array{stars: int, xp: int, firstTime: bool}
     */
    public function handle(User $user, Lesson $lesson, int $mistakes): array
    {
        if ($lesson->isExam()) {
            throw new InvalidArgumentException('Exams are submitted with SubmitExam.');
        }

        $mistakes = max(0, $mistakes);
        $stars = Chessflow::lessonStars($mistakes);

        return DB::transaction(function () use ($user, $lesson, $mistakes, $stars) {
            $progress = LessonProgress::firstOrNew(['user_id' => $user->id, 'lesson_id' => $lesson->id]);
            $firstTime = $progress->completed_at === null;

            $progress->fill([
                'best_stars' => max($stars, $progress->best_stars ?? 0),
                'last_stars' => $stars,
                'mistakes' => $mistakes,
                'attempts' => ($progress->attempts ?? 0) + 1,
                'completed_at' => $progress->completed_at ?? now(),
            ])->save();

            $xp = $firstTime ? $lesson->xp : (int) round($lesson->xp * (float) config('chessflow.repeat_xp_ratio'));
            $xp = $this->awardXp->handle($user, $xp, $lesson);

            return ['stars' => $stars, 'xp' => $xp, 'firstTime' => $firstTime];
        });
    }
}
