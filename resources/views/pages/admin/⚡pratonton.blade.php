<?php

use App\Models\Lesson;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Admin preview (also embedded in Filament): the real TS island, but no `lesson-completed`
// listener here, so nothing is recorded.
new #[Layout('layouts::playground')] class extends Component {
    public Lesson $lesson;

    public function mount(Lesson $lesson): void
    {
        $this->lesson = $lesson;
    }

    public function render()
    {
        return $this->view()->title('Pratonton: '.$this->lesson->title);
    }
}; ?>

<div class="app" style="padding-block: 12px">
    <div wire:ignore data-chessflow="lesson" data-lesson="{{ json_encode([
        'id' => $lesson->slug,
        'tahap' => $lesson->level->number,
        'title' => $lesson->title,
        'icon' => $lesson->icon,
        'exam' => $lesson->isExam(),
        'tip' => $lesson->tip,
        'steps' => $lesson->steps,
    ]) }}"></div>
</div>
