<?php

use App\Actions\AwardBadges;
use App\Actions\CompleteReview;
use App\Models\ReviewItem;
use App\Support\ProgressGuard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Latih semula')] class extends Component {
    /** The step being practised; locked so the browser cannot swap it for another one. */
    #[Locked]
    public ?int $itemId = null;

    #[Locked]
    public int $startedAt = 0;

    public function mount(): void
    {
        $this->itemId = auth()->user()->reviewItems()->due()->orderBy('due_on')->orderBy('id')->value('id');
        $this->startedAt = now()->getTimestamp();
    }

    /**
     * Right means the step was solved without a mistake. The browser only reports what happened;
     * the schedule and XP are decided in CompleteReview.
     *
     * @param  array<mixed>  $failedSteps
     */
    #[On('lesson-completed')]
    public function complete(int $mistakes = 0, array $failedSteps = []): void
    {
        $user = auth()->user();
        $item = $this->itemId ? $user->reviewItems()->find($this->itemId) : null;

        $tooFast = now()->getTimestamp() - $this->startedAt < (int) config('chessflow.limits.min_seconds_per_step');
        if (! $item || $tooFast || ! ProgressGuard::allow($user, 'review')) {
            $this->dispatch('lesson-result', xp: 0, passed: false, message: ProgressGuard::tooFastMessage(), mapUrl: route('peta'));

            return;
        }

        $r = app(CompleteReview::class)->handle($user, $item, $mistakes === 0 && $failedSteps === []);

        $this->dispatch('lesson-result', ...[
            'xp' => $r['xp'],
            'passed' => $r['correct'],
            'review' => ['correct' => $r['correct'], 'mastered' => $r['mastered'], 'remaining' => $r['remaining']],
            'badges' => app(AwardBadges::class)->handle($user->fresh()),
            'totalXp' => $user->fresh()->xp,
            'mapUrl' => route('peta'),
            'next' => $r['remaining'] > 0 ? ['url' => route('latih'), 'title' => 'latihan seterusnya'] : null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(): ?array
    {
        $item = $this->itemId ? ReviewItem::with('lesson.level')->find($this->itemId) : null;
        if (! $item || ! isset($item->lesson->steps[$item->step_index])) {
            return null;
        }
        $step = $item->lesson->localizedStep($item->step_index) ?? [];

        return [
            'id' => 'latih',
            'review' => true,
            'tahap' => $item->lesson->level->number,
            'title' => __('Latih semula'),
            'icon' => $item->lesson->icon,
            'steps' => [array_merge($step, [
                'say' => __('Dari pelajaran :lesson. Awak tersilap di sini sebelum ini, jom cuba lagi!', ['lesson' => '<b>'.e($item->lesson->title).'</b>']).'<br>'.($step['say'] ?? ''),
            ])],
        ];
    }

    public function with(): array
    {
        return ['due' => auth()->user()->reviewItems()->due()->count()];
    }
}; ?>

<div>
    <p class="no-print" style="margin-block: 8px 0"><a class="ghost" href="{{ route('peta') }}">{{ __('← Peta') }}</a></p>
    @if ($payload = $this->payload())
        <p class="review-left">{{ __(':n latihan untuk hari ini', ['n' => $due]) }}</p>
        <div wire:ignore data-chessflow="lesson" data-lesson="{{ json_encode($payload) }}"></div>
    @else
        <section class="panel-card review-empty">
            <h1>{{ __('Tiada latihan hari ini') }}</h1>
            <p>{{ __('Bila awak tersilap dalam pelajaran atau teka-teki, Pak Kuda simpan soalan itu dan bawa semula ke sini pada hari yang sesuai. Teruskan belajar!') }}</p>
            <a class="cta" href="{{ route('peta') }}">{{ __('Ke peta') }}</a>
        </section>
    @endif
</div>
