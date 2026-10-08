<?php

use App\Models\Classroom;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Kelas saya')] class extends Component {
    #[Validate('required|string|max:60', as: 'nama kelas')]
    public string $name = '';

    public function create(): void
    {
        $this->authorize('create', Classroom::class);
        $this->validate();

        $classroom = Classroom::create([
            'teacher_id' => auth()->id(),
            'name' => trim($this->name),
            'join_code' => Classroom::newJoinCode(),
        ]);

        $this->redirectRoute('guru.kelas', $classroom);
    }

    public function with(): array
    {
        return [
            'classrooms' => auth()->user()->taughtClassrooms()->withCount('students')->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <div class="page-head">
        <h1>Kelas saya</h1>
    </div>

    <div class="class-list">
        @forelse ($classrooms as $classroom)
            <a class="qcard" href="{{ route('guru.kelas', $classroom) }}">
                <span class="qico"><i class="pc wK"></i></span>
                <span><b>{{ $classroom->name }}</b><small>Kod sertai: {{ $classroom->join_code }}</small></span>
                <span class="qnum">{{ $classroom->students_count }}<small>murid</small></span>
            </a>
        @empty
            <p>Belum ada kelas. Cipta kelas pertama awak di bawah.</p>
        @endforelse
    </div>

    <section class="panel-card">
        <h2>Cipta kelas</h2>
        <form wire:submit="create">
            <input type="text" wire:model="name" placeholder="Contoh: 4 Bestari" aria-label="Nama kelas" maxlength="60">
            <button class="cta" type="submit">Cipta</button>
            @error('name') <p class="err">{{ $message }}</p> @enderror
        </form>
    </section>
</div>
