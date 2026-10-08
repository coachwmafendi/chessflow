<?php

namespace App\Actions;

use App\Models\DailyPuzzle;
use App\Models\Lesson;
use App\Support\Chessflow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class PickDailyPuzzle
{
    /**
     * Candidate puzzles: same filter as the prototype's BANK (a real position, not flipped,
     * with a clear answer: mate, accepted SAN or a line of 3+ plies).
     *
     * @return Collection<int, array{lesson_id: int, step_index: int}>
     */
    public function bank(): Collection
    {
        /** @var list<string> $slugs */
        $slugs = config('chessflow.daily.lessons');

        return Lesson::query()
            ->whereIn('slug', $slugs)
            ->where('is_published', true)
            ->orderBy('position')
            ->get()
            ->flatMap(fn (Lesson $lesson) => collect($lesson->steps)
                ->map(fn (array $step, int $i) => ['step' => $step, 'lesson_id' => $lesson->id, 'step_index' => $i])
                ->filter(fn (array $c) => self::suitable($c['step']))
                ->map(fn (array $c) => ['lesson_id' => $c['lesson_id'], 'step_index' => $c['step_index']]))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $step
     */
    public static function suitable(array $step): bool
    {
        if (($step['type'] ?? null) !== 'puzzle' || empty($step['fen']) || ! empty($step['orient'])) {
            return false;
        }

        return ! empty($step['acceptMate'])
            || ! empty($step['acceptSan'])
            || (is_array($step['line'] ?? null) && count($step['line']) >= 3);
    }

    /** Returns the puzzle for $date (local Y-m-d), choosing one if none exists yet. */
    public function handle(?string $date = null): DailyPuzzle
    {
        $date ??= Chessflow::today();

        $existing = DailyPuzzle::whereDate('date', $date)->first();
        if ($existing) {
            return $existing;
        }

        $bank = $this->bank();
        if ($bank->isEmpty()) {
            throw new RuntimeException('No lessons are marked as suitable for the daily puzzle.');
        }

        $since = Carbon::parse($date)->subDays((int) config('chessflow.daily.no_repeat_days'))->toDateString();
        $recent = DailyPuzzle::whereDate('date', '>', $since)
            ->get(['lesson_id', 'step_index'])
            ->map(fn (DailyPuzzle $p) => $p->lesson_id.':'.$p->step_index)
            ->all();

        $fresh = $bank->reject(fn (array $c) => in_array($c['lesson_id'].':'.$c['step_index'], $recent, true));
        $pick = ($fresh->isNotEmpty() ? $fresh : $bank)->random();

        return DailyPuzzle::create(['date' => $date] + $pick);
    }
}
