<?php

use App\Actions\AwardBadges;
use App\Actions\RecordGame;
use App\Actions\ScheduleGameReview;
use App\Enums\GameResult;
use App\Support\ProgressGuard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Main lawan Pak Kuda')] class extends Component {
    #[On('game-finished')]
    public function finish(string $userColor = 'w', int $level = 0, string $result = '', string $pgn = '', int $moveCount = 0): void
    {
        $gameResult = GameResult::tryFrom($result);
        if (! $gameResult) {
            return;
        }

        $user = auth()->user();
        if (! ProgressGuard::allow($user, 'games')) {
            return;
        }

        $r = app(RecordGame::class)->handle($user, $userColor, $level, $gameResult, $pgn, $moveCount);

        $this->dispatch('game-result', xp: $r['xp'], wins: $r['wins'], gameId: $r['game'], totalXp: $user->fresh()->xp, badges: app(AwardBadges::class)->handle($user->fresh()));
    }

    /**
     * Mistakes the post-game review found, to practise again in "Latih semula". ScheduleGameReview checks them
     * against the stored game.
     *
     * @param  array<mixed>  $mistakes
     */
    #[On('game-mistakes')]
    public function saveMistakes(int $gameId = 0, array $mistakes = []): void
    {
        $user = auth()->user();
        $game = $user->games()->find($gameId);
        if (! $game || ! ProgressGuard::allow($user, 'games')) {
            return;
        }

        $this->dispatch('game-mistakes-saved', count: app(ScheduleGameReview::class)->handle($user, $game, $mistakes));
    }
}; ?>

<div>
    <p class="no-print" style="margin-block: 8px 0"><a class="ghost" href="{{ route('peta') }}">{{ __('← Peta') }}</a></p>
    <div wire:ignore data-chessflow="game"></div>
</div>
