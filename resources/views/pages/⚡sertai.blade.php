<?php

use App\Models\Classroom;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Sertai kelas')] class extends Component {
    public string $code = '';

    public ?string $joined = null;

    public function join(): void
    {
        $this->validate(['code' => 'required|string|max:12'], [], ['code' => 'kod kelas']);

        $key = 'join-class:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['code' => 'Terlalu banyak cubaan. Cuba lagi sebentar.']);
        }

        $classroom = Classroom::where('join_code', strtoupper(trim($this->code)))->first();
        if (! $classroom) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['code' => 'Kod kelas tidak dijumpai.']);
        }

        $classroom->students()->syncWithoutDetaching([auth()->id()]);
        $this->joined = $classroom->name;
        $this->code = '';
    }

    public function with(): array
    {
        return ['classrooms' => auth()->user()->classrooms()->orderBy('name')->pluck('name')];
    }
}; ?>

<div class="auth-card">
    <h1>Sertai kelas</h1>
    @if ($joined)
        <p class="status good">Awak sudah sertai kelas {{ $joined }}.</p>
    @endif
    <p>Tulis kod kelas yang guru beri.</p>
    <form wire:submit="join">
        <label for="code">Kod kelas</label>
        <input id="code" type="text" wire:model="code" autocapitalize="characters" maxlength="12" required>
        @error('code') <p class="err">{{ $message }}</p> @enderror
        <button class="cta" type="submit">Sertai</button>
    </form>
    @if ($classrooms->isNotEmpty())
        <p class="alt">Kelas awak: {{ $classrooms->implode(', ') }}</p>
    @endif
    <p class="alt"><a href="{{ route('peta') }}">← Peta</a></p>
</div>
