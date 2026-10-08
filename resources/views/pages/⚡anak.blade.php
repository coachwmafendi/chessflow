<?php

use App\Actions\CreateStudents;
use App\Actions\ResetStudentPin;
use App\Enums\Role;
use App\Models\User;
use App\Support\Curriculum;
use App\Support\ProgressReport;
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

    public function createChild(CreateStudents $create): void
    {
        $this->validate(['childName' => 'required|string|max:60'], [], ['childName' => 'nama anak']);
        $this->credentials = $create->handle(auth()->user(), [$this->childName]);
        $this->childName = '';
    }

    /** Link a child who already has an account: proving the PIN shows the adult knows the child. */
    public function linkChild(): void
    {
        $this->validate([
            'username' => 'required|string|max:30',
            'pin' => 'required|digits_between:4,6',
        ], [], ['username' => 'nama pengguna', 'pin' => 'PIN']);

        $key = 'link-child:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['username' => 'Terlalu banyak cubaan. Cuba lagi dalam '.RateLimiter::availableIn($key).' saat.']);
        }

        $child = User::where('username', Str::lower($this->username))->where('role', Role::Murid)->first();
        if (! $child || ! $child->pin || ! Hash::check($this->pin, $child->pin)) {
            RateLimiter::hit($key);
            $this->reset('pin');
            throw ValidationException::withMessages(['username' => 'Nama pengguna atau PIN salah.']);
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

    public function actionsHtml(User $child): string
    {
        return '<button class="ghost" type="button" wire:click="resetPin('.$child->id.')">PIN baru</button> '
            .'<button class="ghost" type="button" wire:click="unlink('.$child->id.')" wire:confirm="'
            .e('Buang pautan dengan '.$child->name.'? Akaun anak tidak dipadam.').'">Buang pautan</button>';
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
    <div class="page-head"><h1>Anak saya</h1></div>

    @if ($credentials)
        <section class="panel-card" wire:key="creds">
            <h2>Akaun anak</h2>
            <p>Simpan maklumat ini. PIN tidak akan dipaparkan lagi.</p>
            <table class="creds">
                <thead><tr><th>Nama</th><th>Nama pengguna</th><th>PIN</th></tr></thead>
                <tbody>
                    @foreach ($credentials as $c)
                        <tr><td>{{ $c['name'] }}</td><td>{{ $c['username'] }}</td><td>{{ $c['pin'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <p style="margin-top: 8px">Anak masuk di <b>{{ route('murid.login') }}</b>
                <button class="ghost" type="button" wire:click="$set('credentials', [])">Tutup</button></p>
        </section>
    @endif

    <x-chessflow.progress-table :rows="$rows" :lessons="$lessons" :actions="fn ($c) => $this->actionsHtml($c)" />

    <section class="panel-card">
        <h2>Cipta akaun anak</h2>
        <p>Tiada e-mel diperlukan. Kami jana nama pengguna dan PIN.</p>
        <form wire:submit="createChild">
            <input type="text" wire:model="childName" placeholder="Nama panggilan anak" aria-label="Nama anak" maxlength="60">
            <button class="cta" type="submit">Cipta</button>
            @error('childName') <p class="err">{{ $message }}</p> @enderror
        </form>
    </section>

    <section class="panel-card">
        <h2>Pautkan anak yang sudah ada akaun</h2>
        <p>Contohnya akaun yang dicipta oleh guru. Masukkan nama pengguna dan PIN anak.</p>
        <form wire:submit="linkChild">
            <input type="text" wire:model="username" placeholder="Nama pengguna" aria-label="Nama pengguna anak" autocapitalize="none">
            <input type="password" wire:model="pin" placeholder="PIN" aria-label="PIN anak" inputmode="numeric" maxlength="6">
            <button class="cta" type="submit">Pautkan</button>
            @error('username') <p class="err">{{ $message }}</p> @enderror
            @error('pin') <p class="err">{{ $message }}</p> @enderror
        </form>
    </section>
</div>
