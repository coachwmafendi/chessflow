<?php

namespace App\Support;

use App\Enums\GameResult;
use App\Models\Game;
use App\Models\Lesson;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * For parents and teachers: how each student does against Pak Kuda and what keeps going wrong. Games resigned
 * before the first move are left out (as in the game history). "Kesilapan kerap" counts the patterns of the game
 * positions waiting in Latih semula, so it shows what the student still has to practise.
 */
class PlayReport
{
    /**
     * @param  Collection<int, User>  $students
     * @return Collection<int, array{student: User, played: int, won: int, lost: int, drawn: int, bestWin: int|null, opening: string|null, mistake: array{title: string, count: int}|null, reviewDue: int, reviewTotal: int, lastGame: Carbon|null}>
     */
    public function forStudents(Collection $students): Collection
    {
        $ids = $students->pluck('id');
        $games = Game::whereIn('user_id', $ids)->where('move_count', '>', 0)
            ->get(['user_id', 'level', 'result', 'opening', 'finished_at'])->groupBy('user_id');
        $reviews = ReviewItem::whereIn('user_id', $ids)->get(['user_id', 'game_id', 'position', 'due_on'])->groupBy('user_id');
        $titles = Lesson::whereIn('slug', PatternLessons::SLUGS)->get(['slug', 'title', 'i18n'])
            ->mapWithKeys(fn (Lesson $l) => [$l->slug => (string) $l->title])->all();

        return $students->values()->map(function (User $s) use ($games, $reviews, $titles) {
            /** @var Collection<int, Game> $g */
            $g = $games->get($s->id, collect());
            /** @var Collection<int, ReviewItem> $r */
            $r = $reviews->get($s->id, collect());
            $count = fn (array $results) => $g->filter(fn (Game $x) => in_array($x->result, $results, true))->count();

            $openings = array_count_values(array_filter($g->map(fn (Game $x) => $x->opening)->all(), 'is_string'));
            arsort($openings);
            $opening = array_key_first($openings);
            $pattern = $r->filter(fn (ReviewItem $i) => $i->isFromGame())
                ->map(fn (ReviewItem $i) => $i->position['lesson'] ?? null)->filter()->countBy()->sortDesc();
            $slug = $pattern->keys()->first();
            $winLevels = $g->filter(fn (Game $x) => $x->result === GameResult::Win)->map(fn (Game $x): int => $x->level)->all();

            return [
                'student' => $s,
                'played' => $g->count(),
                'won' => $count([GameResult::Win]),
                'lost' => $count([GameResult::Loss, GameResult::Resign]),
                'drawn' => $count([GameResult::Draw]),
                'bestWin' => $winLevels ? max($winLevels) : null,
                'opening' => is_string($opening) ? $opening : null,
                'mistake' => is_string($slug) && isset($titles[$slug]) ? ['title' => $titles[$slug], 'count' => (int) $pattern[$slug]] : null,
                'reviewDue' => $r->filter(fn (ReviewItem $i) => $i->isDue())->count(),
                'reviewTotal' => $r->count(),
                'lastGame' => $g->sortByDesc('finished_at')->first()?->finished_at,
            ];
        });
    }
}
