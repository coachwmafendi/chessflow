<?php

use App\Actions\AwardBadges;
use App\Support\Badges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Lencana')] class extends Component {
    public function with(): array
    {
        $user = auth()->user();
        app(AwardBadges::class)->handle($user); // shown on this page, so they count as seen
        $earned = $user->badges()->get()->keyBy('badge');
        $stats = Badges::stats($user);

        return [
            'badges' => collect(Badges::all())->map(fn (array $b, string $key) => [
                'badge' => $b,
                'awarded' => $earned->get($key)?->awarded_at,
                'progress' => Badges::progress($b, $stats),
            ]),
            'count' => $earned->count(),
            'total' => count(Badges::all()),
        ];
    }
}; ?>

<div>
    <p class="no-print" style="margin-block: 8px 0"><a class="ghost" href="{{ route('peta') }}">{{ __('← Peta') }}</a></p>
    <div class="page-head">
        <h1>{{ __('Lencana') }}</h1>
        <span class="pill">{{ __(':count/:total dikumpul', ['count' => $count, 'total' => $total]) }}</span>
    </div>
    <p class="badges-intro">{{ __('Kumpul lencana dengan belajar, selesaikan teka-teki dan main lawan Pak Kuda. Lencana yang belum dapat menunjukkan berapa lagi yang perlu.') }}</p>
    <div class="badge-grid">
        @foreach ($badges as $key => $row)
            <x-chessflow.badge :badge="$row['badge']" :earned="$row['awarded'] !== null" :date="$row['awarded']" :progress="$row['progress']" wire:key="badge-{{ $key }}" />
        @endforeach
    </div>
</div>
