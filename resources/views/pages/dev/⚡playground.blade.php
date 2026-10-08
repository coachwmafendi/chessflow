<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::playground')] #[Title('ChessFlow Playground')] class extends Component {
    public array $lessons = [];
    public ?array $lesson = null;

    public function mount(): void
    {
        abort_unless(app()->environment('local'), 404);

        $data = json_decode(file_get_contents(base_path('data/lessons.json')), true);
        $this->lessons = $data['lessons'];

        $slug = request()->query('lesson');
        if ($slug) {
            $this->lesson = collect($this->lessons)->firstWhere('id', $slug);
        }
    }
}; ?>

<div class="app" style="max-width:1080px;margin-inline:auto;padding-block:24px">
    <h1 style="font-size:28px;font-weight:800;margin-bottom:4px">ChessFlow — Playground (dev)</h1>
    <p style="color:var(--muted,#58677D);margin-bottom:20px">
        Hanya untuk ujian tempatan (bukan untuk murid). Pilih pelajaran untuk main.
    </p>

    @if ($lesson)
        <p style="margin-bottom:16px">
            <a href="{{ route('dev.playground') }}">&larr; Semua pelajaran ({{ count($lessons) }})</a>
        </p>
        <div wire:ignore data-chessflow="lesson" data-lesson='@json($lesson)'></div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
            @foreach ($lessons as $l)
                <a href="{{ route('dev.playground', ['lesson' => $l['id']]) }}"
                   style="display:block;border:1px solid #D6E0E8;border-radius:14px;padding:14px;text-decoration:none;color:inherit">
                    <strong>Tahap {{ $l['tahap'] }} — {{ $l['title'] }}</strong>
                    <div style="font-size:13px;color:#58677D">
                        {{ count($l['steps']) }} langkah{{ !empty($l['exam']) ? ' · ujian' : '' }}
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
