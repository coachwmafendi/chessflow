<?php

use App\Actions\CreateStudents;
use App\Actions\DeleteStudent;
use App\Actions\ResetStudentPin;
use App\Enums\Role;
use App\Models\User;
use App\Support\Curriculum;
use App\Support\ProgressReport;
use App\Support\UserDataExport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Anak saya')] class extends Component {
    public string $childName = '';

    public string $username = '';

    public string $pin = '';

    public array $credentials = [];

    public ?string $notice = null;

    public function createChild(CreateStudents $create): void
    {
        $this->validate(['childName' => 'required|string|max:60'], [], ['childName' => __('nama anak')]);
        $this->credentials = $create->handle(auth()->user(), [$this->childName]);
        $this->childName = '';
    }

    /** Link a child who already has an account: proving the PIN shows the adult knows the child. */
    public function linkChild(): void
    {
        $this->validate([
            'username' => 'required|string|max:30',
            'pin' => 'required|digits_between:4,6',
        ], [], ['username' => __('nama pengguna'), 'pin' => 'PIN']);

        $key = 'link-child:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['username' => __('Terlalu banyak cubaan. Cuba lagi dalam :seconds saat.', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        $child = User::where('username', Str::lower($this->username))->where('role', Role::Murid)->first();
        if (! $child || ! $child->pin || ! Hash::check($this->pin, $child->pin)) {
            RateLimiter::hit($key);
            $this->reset('pin');
            throw ValidationException::withMessages(['username' => __('Nama pengguna atau PIN salah.')]);
        }

        RateLimiter::clear($key);
        auth()->user()->students()->syncWithoutDetaching([$child->id]);
        $this->reset('username', 'pin');
    }

    public function resetPin(int $childId, ResetStudentPin $reset): void
    {
        $child = auth()->user()->students()->findOrFail($childId);
        $this->credentials = [['id' => $child->id, 'name' => $child->name, 'username' => $child->username, 'pin' => $reset->handle($child)]];
    }

    public function unlink(int $childId): void
    {
        auth()->user()->students()->detach($childId);
    }

    /** A copy of everything held about the child, as JSON (PDPA access / portability). */
    public function exportChild(int $childId, UserDataExport $export)
    {
        return $export->download(auth()->user()->students()->findOrFail($childId));
    }

    /** Parents may delete their child's account and all its data (PDPA). */
    public function deleteChild(int $childId, DeleteStudent $delete): void
    {
        $child = auth()->user()->students()->findOrFail($childId);
        $delete->handle(auth()->user(), $child);
        $this->credentials = [];
        $this->notice = __('Akaun :name dan semua datanya telah dipadam.', ['name' => $child->name]);
    }

    /** Typed confirmation (wire:confirm.prompt): deleting cannot be undone. */
    private function deleteConfirm(User $student): string
    {
        return e(__('Padam akaun :name selama-lamanya? Semua kemajuan, sijil, lencana dan permainan akan hilang dan tidak boleh dipulihkan. Taip :word untuk sahkan.', ['name' => $student->name, 'word' => __('PADAM')])).'|'.e(__('PADAM'));
    }

    public function actionsHtml(User $child): string
    {
        return '<button class="ghost" type="button" wire:click="resetPin('.$child->id.')">'.e(__('PIN baru')).'</button> '
            .'<button class="ghost" type="button" wire:click="unlink('.$child->id.')" wire:confirm="'
            .e(__('Buang pautan dengan :name? Akaun anak tidak dipadam.', ['name' => $child->name])).'">'.e(__('Buang pautan')).'</button> '
            .'<button class="ghost" type="button" wire:click="exportChild('.$child->id.')">'.e(__('Muat turun data')).'</button> '
            .'<button class="ghost danger" type="button" wire:click="deleteChild('.$child->id.')" wire:confirm.prompt="'
            .$this->deleteConfirm($child).'">'.e(__('Padam akaun')).'</button>';
    }

    public function with(): array
    {
        $children = auth()->user()->students()->orderBy('name')->get();

        return [
            'rows' => app(ProgressReport::class)->forStudents($children),
            'lessons' => app(Curriculum::class)->lessons(),
        ];
    }
}; ?>

<div>
    <div class="page-head"><h1>{{ __('Anak saya') }}</h1></div>

    @if ($notice)
        <p class="status good" wire:key="notice">{{ $notice }}</p>
    @endif

    @if ($credentials)
        <section class="panel-card" wire:key="creds">
            <h2>{{ __('Akaun anak') }}</h2>
            <p>{{ __('Simpan maklumat ini. PIN tidak akan dipaparkan lagi.') }}</p>
            <table class="creds">
                <thead><tr><th>{{ __('Nama') }}</th><th>{{ __('Nama pengguna') }}</th><th>PIN</th></tr></thead>
                <tbody>
                    @foreach ($credentials as $c)
                        <tr><td>{{ $c['name'] }}</td><td>{{ $c['username'] }}</td><td>{{ $c['pin'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <p style="margin-top: 8px">{!! __('Anak masuk di :url', ['url' => '<b>'.e(route('murid.login')).'</b>']) !!}
                <button class="ghost" type="button" wire:click="$set('credentials', [])">{{ __('Tutup') }}</button></p>
        </section>
    @endif

    <x-chessflow.progress-table :rows="$rows" :lessons="$lessons" :actions="fn ($c) => $this->actionsHtml($c)" />

    <section class="panel-card">
        <h2>{{ __('Cipta akaun anak') }}</h2>
        <p>{{ __('Tiada e-mel diperlukan. Kami jana nama pengguna dan PIN.') }}</p>
        <form wire:submit="createChild">
            <input type="text" wire:model="childName" placeholder="{{ __('Nama panggilan anak') }}" aria-label="{{ __('Nama anak') }}" maxlength="60">
            <button class="cta" type="submit">{{ __('Cipta') }}</button>
            @error('childName') <p class="err">{{ $message }}</p> @enderror
            <p class="consent">{!! __('Dengan mencipta akaun, anda mengesahkan anda ibu bapa atau penjaga kanak-kanak ini dan bersetuju dengan :policy. Hanya nama panggilan diperlukan.', ['policy' => '<a href="'.route('privasi').'">'.e(__('Dasar Privasi')).'</a>']) !!}</p>
        </form>
    </section>

    <section class="panel-card">
        <h2>{{ __('Pautkan anak yang sudah ada akaun') }}</h2>
        <p>{{ __('Contohnya akaun yang dicipta oleh guru. Masukkan nama pengguna dan PIN anak.') }}</p>
        <form wire:submit="linkChild">
            <input type="text" wire:model="username" placeholder="{{ __('Nama pengguna') }}" aria-label="{{ __('Nama pengguna anak') }}" autocapitalize="none">
            <input type="password" wire:model="pin" placeholder="PIN" aria-label="{{ __('PIN anak') }}" inputmode="numeric" maxlength="6">
            <button class="cta" type="submit">{{ __('Pautkan') }}</button>
            @error('username') <p class="err">{{ $message }}</p> @enderror
            @error('pin') <p class="err">{{ $message }}</p> @enderror
        </form>
    </section>
</div>
