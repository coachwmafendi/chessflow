<?php

namespace App\Actions;

use App\Models\Certificate;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Support\Chessflow;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitExam
{
    public function __construct(private AwardXp $awardXp) {}

    /**
     * @param  array<mixed>  $failedSteps  step indexes the student got wrong (reported by the browser)
     * @return array{score: int, total: int, need: int, passed: bool, stars: int, xp: int, certificateCode: string|null}
     */
    public function handle(User $user, Lesson $lesson, array $failedSteps): array
    {
        if (! $lesson->isExam()) {
            throw new InvalidArgumentException('Only exams can be submitted.');
        }

        $total = count($lesson->steps);
        $failed = collect($failedSteps)
            ->filter(fn ($i) => is_int($i) && $i >= 0 && $i < $total)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $score = $total - count($failed);
        $need = Chessflow::examPassMark($total);
        $passed = $score >= $need;

        return DB::transaction(function () use ($user, $lesson, $failed, $score, $total, $need, $passed) {
            $attempt = ExamAttempt::create([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'score' => $score,
                'total' => $total,
                'passed' => $passed,
                'failed_steps' => $failed,
            ]);

            if (! $passed) {
                return ['score' => $score, 'total' => $total, 'need' => $need, 'passed' => false, 'stars' => 0, 'xp' => 0, 'certificateCode' => null];
            }

            $stars = Chessflow::examStars($score, $total);
            $progress = LessonProgress::firstOrNew(['user_id' => $user->id, 'lesson_id' => $lesson->id]);
            $firstPass = $progress->completed_at === null;

            $progress->fill([
                'best_stars' => max($stars, $progress->best_stars ?? 0),
                'last_stars' => $stars,
                'mistakes' => $total - $score,
                'attempts' => ($progress->attempts ?? 0) + 1,
                'completed_at' => $progress->completed_at ?? now(),
            ])->save();

            $xp = $firstPass ? $lesson->xp : (int) round($lesson->xp * (float) config('chessflow.repeat_xp_ratio'));
            $xp = $this->awardXp->handle($user, $xp, $attempt);

            $certificate = Certificate::firstWhere(['user_id' => $user->id, 'level_id' => $lesson->level_id])
                ?? Certificate::create([
                    'user_id' => $user->id,
                    'level_id' => $lesson->level_id,
                    'exam_attempt_id' => $attempt->id,
                    'display_name' => $user->name,
                    'score' => $score,
                    'total' => $total,
                    'code' => $this->newCode(),
                    'issued_at' => now(),
                ]);

            return ['score' => $score, 'total' => $total, 'need' => $need, 'passed' => true, 'stars' => $stars, 'xp' => $xp, 'certificateCode' => $certificate->code];
        });
    }

    private function newCode(): string
    {
        // No 0/O/1/I so the code is easy to read off a printed certificate.
        do {
            $code = collect(range(1, 10))
                ->map(fn () => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)])
                ->implode('');
        } while (Certificate::where('code', $code)->exists());

        return $code;
    }
}
