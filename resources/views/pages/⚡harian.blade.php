<?php

use App\Actions\CompleteDaily;
use App\Actions\PickDailyPuzzle;
use App\Models\DailyPuzzle;
use App\Support\Chessflow;
use App\Support\ProgressGuard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Teka-teki Harian')] class extends Component {
    public int $puzzleId;

    public function mount(PickDailyPuzzle $pick): void
    {
        // The scheduler normally picks at 00:05; pick on demand if it has not run.
        $this->puzzleId = $pick->handle()->id;
    }

    #[On('lesson-completed')]
    public function complete(): void
    {
        $user = auth()->user();
        if (! ProgressGuard::allow($user, 'daily')) {
            $this->dispatch('lesson-result', xp: 0, passed: false, message: ProgressGuard::TOO_FAST, mapUrl: route('peta'));

            return;
        }

        $r = app(CompleteDaily::class)->handle($user);

        $this->dispatch('lesson-result', ...[
            'xp' => $r['xp'],
            'streak' => $r['streak'],
            'passed' => true,
            'totalXp' => $user->fresh()->xp,
            'mapUrl' => route('peta'),
            'next' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $puzzle = DailyPuzzle::with('lesson.level')->findOrFail($this->puzzleId);
        $step = $puzzle->lesson->steps[$puzzle->step_index];
        $date = Chessflow::now()->locale('ms')->translatedFormat('j F');

        return [
            'id' => 'harian',
            'daily' => true,
            'tahap' => $puzzle->lesson->level->number,
            'title' => 'Teka-teki Harian',
            'icon' => 'wN',
            'steps' => [array_merge($step, [
                'title' => 'Teka-teki '.$date,
                'say' => 'Teka-teki baharu setiap hari. Selesaikan untuk kekalkan streak!<br>'.($step['say'] ?? ''),
            ])],
        ];
    }

    public function with(): array
    {
        $user = auth()->user();

        return [
            'doneToday' => $user->dailyCompletions()->whereDate('date', Chessflow::today())->exists(),
            'streak' => $user->currentStreak(),
        ];
    }
}; ?>

<div>
    <p class="no-print" style="margin-block: 8px 0"><a class="ghost" href="{{ route('peta') }}">← Peta</a></p>
    @if ($doneToday)
        <p class="status good">Teka-teki hari ini sudah selesai ({{ $streak }} hari berturut-turut). Awak boleh cuba lagi, tetapi XP hanya sekali sehari.</p>
    @endif
    <div wire:ignore data-chessflow="lesson" data-lesson="{{ json_encode($this->payload()) }}"></div>
</div>
