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
            'certificates' => $user->certificates()->with('level')->get()->sortBy(fn ($c) => $c->level->number),
            'isStudent' => $user->isStudent(),
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

    @if ($isStudent || $certificates->isNotEmpty())
        <section class="my-certs" aria-labelledby="my-certs-h">
            <h2 id="my-certs-h">{{ __('Sijil saya') }}</h2>
            @forelse ($certificates as $c)
                <a class="cert-row" href="{{ route('sijil', $c->code) }}">
                    <span class="qico"><x-chessflow.medal /></span>
                    <span><b>{{ __('Tahap :n: :name', ['n' => $c->level->number, 'name' => $c->level->name]) }}</b><small>{{ $c->score }}/{{ $c->total }} · {{ $c->issued_at->locale(app()->getLocale())->translatedFormat('j F Y') }}</small></span>
                    <span class="cert-open">{{ __('Lihat sijil') }}</span>
                </a>
            @empty
                <p class="muted">{{ __('Belum ada sijil. Lulus ujian tahap (70% ke atas) untuk mendapat sijil pertama.') }}</p>
            @endforelse
        </section>
    @endif
</div>
