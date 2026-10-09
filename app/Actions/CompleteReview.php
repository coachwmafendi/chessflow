<?php

namespace App\Actions;

use App\Models\ReviewItem;
use App\Models\User;
use App\Support\Chessflow;

/**
 * Moves a "Latih semula" step through its boxes. A right answer pushes it further out
 * (config chessflow.review.intervals) and retires it after the last box; a wrong one brings
 * it back tomorrow. Only a step that is due counts, so replaying it cannot farm XP.
 */
class CompleteReview
{
    public function __construct(private AwardXp $awardXp) {}

    /**
     * @return array{counted: bool, correct: bool, mastered: bool, xp: int, remaining: int}
     */
    public function handle(User $user, ReviewItem $item, bool $correct): array
    {
        abort_unless($item->user_id === $user->id, 403);

        if (! $item->isDue()) {
            return ['counted' => false, 'correct' => $correct, 'mastered' => false, 'xp' => 0, 'remaining' => $this->remaining($user)];
        }

        $intervals = array_values((array) config('chessflow.review.intervals'));
        $mastered = false;
        $xp = 0;

        if ($correct) {
            if ($item->box > count($intervals)) {
                $item->delete();
                $mastered = true;
            } else {
                $item->update([
                    'due_on' => Chessflow::now()->addDays((int) $intervals[$item->box - 1])->toDateString(),
                    'box' => $item->box + 1,
                ]);
            }
            $xp = $this->awardXp->handle($user, (int) config('chessflow.review.xp'), $mastered ? null : $item);
        } else {
            $item->update(['box' => 1, 'due_on' => Chessflow::now()->addDay()->toDateString()]);
        }

        return ['counted' => true, 'correct' => $correct, 'mastered' => $mastered, 'xp' => $xp, 'remaining' => $this->remaining($user)];
    }

    private function remaining(User $user): int
    {
        return $user->reviewItems()->due()->count();
    }
}
