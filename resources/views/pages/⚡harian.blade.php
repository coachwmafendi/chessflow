<?php

use App\Actions\AwardBadges;
use App\Actions\CompleteDaily;
use App\Actions\ScheduleReview;
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

    /**
     * @param  array<mixed>  $failedSteps  index 0 = the puzzle itself
     */
    #[On('lesson-completed')]
    public function complete(array $failedSteps = []): void
    {
        $user = auth()->user();
        if (! ProgressGuard::allow($user, 'daily')) {
            $this->dispatch('lesson-result', xp: 0, passed: false, message: ProgressGuard::tooFastMessage(), mapUrl: route('peta'));

            return;
        }

        $r = app(CompleteDaily::class)->handle($user);

        if (in_array(0, $failedSteps, true)) {
            $puzzle = DailyPuzzle::with('lesson')->findOrFail($this->puzzleId);
            app(ScheduleReview::class)->handle($user, $puzzle->lesson, [$puzzle->step_index]);
        }

        $this->dispatch('lesson-result', ...[
            'xp' => $r['xp'],
            'streak' => $r['streak'],
            'passed' => true,
            'badges' => app(AwardBadges::class)->handle($user->fresh()),
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
        $step = $puzzle->lesson->localizedStep($puzzle->step_index) ?? [];
        $date = Chessflow::now()->locale(app()->getLocale())->translatedFormat('j F');

        return [
            'id' => 'harian',
            'daily' => true,
            'tahap' => $puzzle->lesson->level->number,
            'title' => __('Teka-teki Harian'),
            'icon' => 'wN',
            'steps' => [array_merge($step, [
                'title' => __('Teka-teki :date', ['date' => $date]),
                'say' => __('Teka-teki baharu setiap hari. Selesaikan untuk kekalkan streak!').'<br>'.($step['say'] ?? ''),
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
    <p class="no-print" style="margin-block: 8px 0"><a class="ghost" href="{{ route('peta') }}">{{ __('← Peta') }}</a></p>
    @if ($doneToday)
        <p class="status good">{{ __('Teka-teki hari ini sudah selesai (:n hari berturut-turut). Awak boleh cuba lagi, tetapi XP hanya sekali sehari.', ['n' => $streak]) }}</p>
    @endif
    <div wire:ignore data-chessflow="lesson" data-lesson="{{ json_encode($this->payload()) }}"></div>
</div>
