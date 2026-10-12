<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Widgets\ChartWidget;

/** New accounts per week for the last 12 weeks: students vs adults (parents, teachers). */
class SignupsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return __('Akaun baharu setiap minggu');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $start = now()->startOfWeek()->subWeeks(11);
        $users = User::where('created_at', '>=', $start)->get(['role', 'created_at']);
        $weeks = collect(range(0, 11))->map(fn (int $i) => $start->copy()->addWeeks($i));
        $count = fn (bool $students) => $weeks->map(fn (CarbonInterface $w) => $users
            ->filter(fn (User $u) => ($u->role === Role::Murid) === $students && $u->created_at->between($w, $w->copy()->endOfWeek()))
            ->count())->all();

        return [
            'datasets' => [
                ['label' => __('Murid'), 'data' => $count(true), 'backgroundColor' => '#087466', 'borderRadius' => 4],
                ['label' => __('Ibu bapa & guru'), 'data' => $count(false), 'backgroundColor' => '#FFBE3D', 'borderRadius' => 4],
            ],
            'labels' => $weeks->map(fn (CarbonInterface $w) => $w->translatedFormat('j M'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
