<?php

use App\Enums\GameResult;
use App\Models\Game;
use App\Support\Chessflow;
use App\Support\PgnMoves;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// The signed-in player's own games against Pak Kuda, newest first, each one openable on the analysis board.
new #[Layout('layouts::chessflow')] #[Title('Sejarah permainan')] class extends Component {
    private const PER_PAGE = 15;

    /** semua | menang | kalah | seri */
    #[Url(as: 'tapis', except: 'semua')]
    public string $filter = 'semua';

    public int $limit = self::PER_PAGE;

    public function updatedFilter(): void
    {
        $this->limit = self::PER_PAGE;
    }

    public function more(): void
    {
        $this->limit += self::PER_PAGE;
    }

    public function with(): array
    {
        // A game resigned before the first move is not a game worth listing or counting.
        $games = auth()->user()->games()->where('move_count', '>', 0);
        $results = match ($this->filter) {
            'menang' => [GameResult::Win],
            'kalah' => [GameResult::Loss, GameResult::Resign],
            'seri' => [GameResult::Draw],
            default => null,
        };

        /** @var Collection<int, Game> $all */
        $all = (clone $games)->get(['level', 'result']);
        $count = fn (Collection $c, array $r) => $c->filter(fn (Game $g) => in_array($g->result, $r, true))->count();
        $lost = [GameResult::Loss, GameResult::Resign];

        $list = (clone $games)->when($results, fn ($q) => $q->whereIn('result', $results))
            ->latest('finished_at')->latest('id')->take($this->limit + 1)->get();

        return [
            'total' => $all->count(),
            'won' => $count($all, [GameResult::Win]),
            'lost' => $count($all, $lost),
            'drawn' => $count($all, [GameResult::Draw]),
            'byLevel' => collect([0 => __('Mudah'), 1 => __('Sederhana'), 2 => __('Sukar')])->map(function (string $name, int $level) use ($all, $count, $lost) {
                $games = $all->where('level', $level);

                return ['name' => $name, 'played' => $games->count(), 'won' => $count($games, [GameResult::Win]), 'lost' => $count($games, $lost), 'drawn' => $count($games, [GameResult::Draw])];
            }),
            'games' => $list->take($this->limit),
            'hasMore' => $list->count() > $this->limit,
            'tz' => Chessflow::timezone(),
        ];
    }

    /** The analysis board at the last move of this game. */
    public function analysisUrl(Game $game): string
    {
        $moves = PgnMoves::san($game->pgn);

        return route('analisis', $moves ? ['moves' => implode(' ', $moves)] : []);
    }
}; ?>

<div>
    <p class="no-print" style="margin-block: 8px 0"><a class="ghost" href="{{ route('main') }}">{{ __('← Main') }}</a></p>
    <div class="page-head">
        <h1>{{ __('Sejarah permainan') }}</h1>
        <a class="cta" href="{{ route('main') }}">{{ __('Main lawan Pak Kuda') }}</a>
    </div>

    @if ($total === 0)
        <div class="history-empty">
            <p>{{ __('Belum ada permainan. Main lawan Pak Kuda, dan setiap permainan akan disimpan di sini.') }}</p>
        </div>
    @else
        <section class="history-summary" aria-label="{{ __('Ringkasan') }}">
            <div class="stat"><b>{{ $total }}</b><span>{{ __('Permainan') }}</span></div>
            <div class="stat good"><b>{{ $won }}</b><span>{{ __('Menang') }}</span></div>
            <div class="stat bad"><b>{{ $lost }}</b><span>{{ __('Kalah') }}</span></div>
            <div class="stat"><b>{{ $drawn }}</b><span>{{ __('Seri') }}</span></div>
        </section>

        <table class="history-levels">
            <caption class="sr-only">{{ __('Rekod mengikut tahap') }}</caption>
            <thead><tr><th scope="col">{{ __('Tahap') }}</th><th scope="col">{{ __('Main') }}</th><th scope="col">{{ __('Menang') }}</th><th scope="col">{{ __('Kalah') }}</th><th scope="col">{{ __('Seri') }}</th></tr></thead>
            <tbody>
                @foreach ($byLevel as $row)
                    <tr><th scope="row">{{ $row['name'] }}</th><td>{{ $row['played'] }}</td><td>{{ $row['won'] }}</td><td>{{ $row['lost'] }}</td><td>{{ $row['drawn'] }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <div class="seg history-filter" role="group" aria-label="{{ __('Tapis keputusan') }}">
            @foreach (['semua' => __('Semua'), 'menang' => __('Menang'), 'kalah' => __('Kalah'), 'seri' => __('Seri')] as $key => $label)
                <button type="button" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>

        <ol class="history-list">
            @forelse ($games as $game)
                @php
                    $won = $game->result === GameResult::Win;
                    $drawn = $game->result === GameResult::Draw;
                    $label = match ($game->result) {
                        GameResult::Win => __('Menang'),
                        GameResult::Draw => __('Seri'),
                        GameResult::Resign => __('Mengaku kalah'),
                        default => __('Kalah'),
                    };
                    $moves = (int) ceil($game->move_count / 2);
                @endphp
                <li class="history-game" wire:key="game-{{ $game->id }}">
                    <i class="pc {{ $game->user_color === 'w' ? 'wK' : 'bK' }}" aria-hidden="true"></i>
                    <div class="history-main">
                        <p class="history-title">
                            <span @class(['result', 'good' => $won, 'bad' => ! $won && ! $drawn])>{{ $label }}</span>
                            <span>{{ $game->user_color === 'w' ? __('Putih') : __('Hitam') }} · {{ __('tahap :level', ['level' => [__('Mudah'), __('Sederhana'), __('Sukar')][$game->level] ?? $game->level]) }} · {{ trans_choice(':n langkah', $moves, ['n' => $moves]) }}</span>
                        </p>
                        <p class="history-meta">
                            <time datetime="{{ $game->finished_at->toIso8601String() }}">{{ $game->finished_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y, g:i A') }}</time>
                            @if ($opening = \App\Support\PgnMoves::opening($game->pgn))
                                · <span class="history-opening">{{ $opening }}</span>
                            @endif
                        </p>
                    </div>
                    <a class="ghost" href="{{ $this->analysisUrl($game) }}">{{ __('Analisis') }}</a>
                </li>
            @empty
                <li class="history-none">{{ __('Tiada permainan untuk pilihan ini.') }}</li>
            @endforelse
        </ol>

        @if ($hasMore)
            <p class="history-more"><button type="button" class="ghost" wire:click="more">{{ __('Tunjuk lagi') }}</button></p>
        @endif
    @endif
</div>
