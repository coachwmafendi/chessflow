<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserBadge;
use App\Support\Badges;

/** Gives the user every badge they now qualify for; returns only the new ones (catalogue rows). */
class AwardBadges
{
    /**
     * @param  bool  $markSeen  true when the caller shows the new badges to the student right away
     * @return list<array{key: string, name: string, description: string, icon: string}>
     */
    public function handle(User $user, bool $markSeen = true): array
    {
        $have = $user->badges()->pluck('badge')->flip();
        $stats = Badges::stats($user);
        $new = [];

        foreach (Badges::all() as $key => $badge) {
            if ($have->has($key) || ! Badges::earned($badge, $stats)) {
                continue;
            }
            $created = UserBadge::firstOrCreate(
                ['user_id' => $user->id, 'badge' => $key],
                ['awarded_at' => now(), 'seen_at' => $markSeen ? now() : null],
            );
            if ($created->wasRecentlyCreated) {
                $new[] = ['key' => $key, 'name' => __($badge['name']), 'description' => __($badge['description']), 'icon' => $badge['icon']];
            }
        }

        return $new;
    }
}
