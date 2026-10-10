<?php

use App\Models\Certificate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Sijil')] class extends Component {
    public Certificate $certificate;

    #[Validate('required|string|max:40')]
    public string $displayName = '';

    public function mount(string $code): void
    {
        $this->certificate = Certificate::with('level')->where('code', strtoupper($code))->firstOrFail();
        $this->displayName = $this->certificate->display_name;
    }

    public function isOwner(): bool
    {
        return auth()->id() === $this->certificate->user_id;
    }

    /** The student writes their own name on the certificate (MIGRATION_PLAN §4, privacy). */
    public function saveName(): void
    {
        abort_unless($this->isOwner(), 403);
        $this->validate();

        $this->certificate->update(['display_name' => trim($this->displayName)]);
    }
}; ?>

<div class="cert-page">
    <div class="cert">
        <small>{{ __('Sijil ChessFlow') }}</small>
        <h2>{{ __('Tahap :n: :name', ['n' => $certificate->level->number, 'name' => $certificate->level->name]) }}</h2>
        <p>{{ __('Dengan ini disahkan bahawa') }}</p>
        <p class="cert-name">{{ $certificate->display_name }}</p>
        <p>{!! __('telah lulus Ujian Tahap :n dengan markah :score.', ['n' => $certificate->level->number, 'score' => '<b>'.$certificate->score.'/'.$certificate->total.'</b>']) !!}</p>
        <small class="cert-date">{{ $certificate->issued_at->locale(app()->getLocale())->translatedFormat('j F Y') }} · Pak Kuda</small>
    </div>

    <p class="verify">{!! __('Sijil sah. Kod pengesahan: :code', ['code' => '<b>'.e($certificate->code).'</b>']) !!}<br>{{ route('sijil', $certificate->code) }}</p>

    <div class="no-print">
        @if ($this->isOwner())
            <form wire:submit="saveName">
                <input class="cert-name" type="text" wire:model="displayName" maxlength="40" aria-label="{{ __('Nama pada sijil') }}" placeholder="{{ __('Tulis nama awak') }}">
                <button class="cta" type="submit">{{ __('Simpan nama') }}</button>
            </form>
            @error('displayName') <p class="status bad">{{ $message }}</p> @enderror
        @endif
        <div class="actions" style="justify-content: center; margin-top: 12px">
            <button class="ghost" type="button" onclick="window.print()">{{ __('Cetak') }}</button>
            @auth <a class="ghost" href="{{ route('peta') }}">{{ __('Ke peta') }}</a> @endauth
        </div>
    </div>
</div>
