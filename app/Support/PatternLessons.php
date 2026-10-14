<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\User;

/**
 * Lessons a game mistake can point to (resources/ts/chessflow/game/patterns.ts decides which), with whether the
 * player can open them yet; a locked lesson is named but not linked.
 */
class PatternLessons
{
    public const SLUGS = ['sah', 'mate2', 'scholar', 'fork', 'pin', 'terbuka', 'cct'];

    /**
     * @return array<string, array{title: string, level: int, open: bool}>
     */
    public static function for(User $user): array
    {
        $curriculum = app(Curriculum::class);
        $completed = $curriculum->completedIds($user);

        return Lesson::with('level')->whereIn('slug', self::SLUGS)->where('is_published', true)->get()
            ->mapWithKeys(fn (Lesson $l) => [$l->slug => [
                'title' => (string) $l->title,
                'level' => $l->level->number,
                'open' => $curriculum->isUnlocked($user, $l, $completed),
            ]])->all();
    }
}
