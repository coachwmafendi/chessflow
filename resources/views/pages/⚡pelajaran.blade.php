<?php

use App\Actions\CompleteLesson;
use App\Actions\SubmitExam;
use App\Models\Lesson;
use App\Support\Curriculum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts::chessflow')] class extends Component {
    public Lesson $lesson;

    public function mount(Lesson $lesson): void
    {
        $this->authorize('view', $lesson);
        $this->lesson = $lesson;
    }

    /**
     * Fired by the TS island when the last step is done. Only mistakes / failed step
     * indexes come from the browser; XP, stars, unlocks and the exam mark are decided here.
     *
     * @param  array<mixed>  $failedSteps
     */
    #[On('lesson-completed')]
    public function complete(string $slug = '', int $mistakes = 0, array $failedSteps = []): void
    {
        $this->authorize('view', $this->lesson);
        $user = auth()->user();
        $next = app(Curriculum::class)->next($this->lesson);

        if ($this->lesson->isExam()) {
            $r = app(SubmitExam::class)->handle($user, $this->lesson, $failedSteps);
            $result = [
                'xp' => $r['xp'],
                'stars' => $r['stars'],
                'passed' => $r['passed'],
                'certificateUrl' => $r['certificateCode'] ? route('sijil', $r['certificateCode']) : null,
                'retryUrl' => $r['passed'] ? null : route('pelajaran', $this->lesson),
            ];
        } else {
            $r = app(CompleteLesson::class)->handle($user, $this->lesson, $mistakes);
            $result = ['xp' => $r['xp'], 'stars' => $r['stars'], 'passed' => true];
        }

        $this->dispatch('lesson-result', ...$result + [
            'totalXp' => $user->fresh()->xp,
            'totalStars' => $user->totalStars(),
            'mapUrl' => route('peta'),
            'next' => $next && ($result['passed'] ?? true) ? ['url' => route('pelajaran', $next), 'title' => $next->title] : null,
        ]);
    }

    public function render()
    {
        return $this->view()->title($this->lesson->title);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'id' => $this->lesson->slug,
            'tahap' => $this->lesson->level->number,
            'title' => $this->lesson->title,
            'icon' => $this->lesson->icon,
            'exam' => $this->lesson->isExam(),
            'tip' => $this->lesson->tip,
            'steps' => $this->lesson->steps,
        ];
    }
}; ?>

<div>
    <p class="no-print" style="margin-block: 8px 0"><a class="ghost" href="{{ route('peta') }}">← Peta</a></p>
    <div wire:ignore data-chessflow="lesson" data-lesson="{{ json_encode($this->payload()) }}"></div>
</div>
