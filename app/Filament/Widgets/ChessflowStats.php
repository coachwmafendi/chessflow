<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Models\Certificate;
use App\Models\LessonProgress;
use App\Models\User;
use App\Models\XpEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Admin dashboard: who uses ChessFlow and how much learning happened this week. */
class ChessflowStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|array|null $columns = ['default' => 1, 'md' => 2, 'xl' => 3];

    protected function getStats(): array
    {
        $week = now()->subDays(7);
        $count = fn (Role $role) => User::where('role', $role)->count();
        $newThisWeek = fn (Role $role) => User::where('role', $role)->where('created_at', '>=', $week)->count();

        $active = XpEvent::where('created_at', '>=', $week)->distinct('user_id')->count('user_id');
        $done = LessonProgress::where('completed_at', '>=', $week)->count();

        return [
            Stat::make(__('Murid'), $count(Role::Murid))
                ->description(__('+:n minggu ini', ['n' => $newThisWeek(Role::Murid)]))
                ->chart($this->daily(User::where('role', Role::Murid)->where('created_at', '>=', now()->subDays(14))->pluck('created_at'))),
            Stat::make(__('Ibu bapa · Guru'), $count(Role::IbuBapa).' · '.$count(Role::Guru))
                ->description(__('+:n minggu ini', ['n' => $newThisWeek(Role::IbuBapa) + $newThisWeek(Role::Guru)])),
            Stat::make(__('Aktif 7 hari'), $active)
                ->description(__('pengguna yang dapat XP'))
                ->chart($this->daily(XpEvent::where('created_at', '>=', now()->subDays(14))->pluck('created_at'))),
            Stat::make(__('Pelajaran disiapkan'), $done)
                ->description(__('dalam 7 hari'))
                ->chart($this->daily(LessonProgress::where('completed_at', '>=', now()->subDays(14))->pluck('completed_at'))),
            Stat::make(__('Sijil dikeluarkan'), Certificate::count())
                ->description(__('+:n minggu ini', ['n' => Certificate::where('issued_at', '>=', $week)->count()])),
        ];
    }

    /**
     * Counts per day for the last 14 days, for the sparkline.
     *
     * @param  Collection<int, mixed>  $times
     * @return list<float> Filament's sparkline takes floats
     */
    private function daily(Collection $times): array
    {
        $byDay = $times->filter()->countBy(fn ($t) => Carbon::parse($t)->toDateString());

        return array_map(fn (int $d) => (float) $byDay->get(now()->subDays($d)->toDateString(), 0), range(13, 0));
    }
}
