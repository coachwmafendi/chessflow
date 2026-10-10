<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Masuk murid')] class extends Component {
    public string $username = '';

    public string $pin = '';

    public function login(): void
    {
        $this->validate([
            'username' => ['required', 'string', 'max:30'],
            'pin' => ['required', 'digits_between:4,6'],
        ], [], ['username' => __('nama pengguna'), 'pin' => 'PIN']);

        $key = 'student-login:'.Str::lower($this->username).'|'.request()->ip();
        $max = (int) config('chessflow.student_login.max_attempts_per_minute');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'username' => __('Terlalu banyak cubaan. Cuba lagi dalam :seconds saat.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $user = User::where('username', Str::lower($this->username))->where('role', Role::Murid)->first();

        if (! $user || ! $user->pin || ! Hash::check($this->pin, $user->pin)) {
            RateLimiter::hit($key);
            $this->reset('pin');

            throw ValidationException::withMessages(['username' => __('Nama pengguna atau PIN salah.')]);
        }

        RateLimiter::clear($key);
        Auth::login($user, remember: true);
        session()->regenerate();

        $this->redirectIntended(route('peta'));
    }
}; ?>

<div class="auth-card">
    <h1>{{ __('Masuk murid') }}</h1>
    <p>{{ __('Tulis nama pengguna dan PIN yang diberi oleh guru atau ibu bapa awak.') }}</p>
    <form wire:submit="login">
        <label for="username">{{ __('Nama pengguna') }}</label>
        <input id="username" type="text" wire:model="username" autocomplete="username" autocapitalize="none" autofocus required>
        @error('username') <p class="err">{{ $message }}</p> @enderror

        <label for="pin">PIN</label>
        <input id="pin" type="password" wire:model="pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="current-password" required>
        @error('pin') <p class="err">{{ $message }}</p> @enderror

        <button class="cta" type="submit">{{ __('Masuk') }}</button>
    </form>
    <p class="alt">{{ __('Guru atau ibu bapa?') }} <a href="{{ route('login') }}">{{ __('Masuk dengan e-mel') }}</a></p>
</div>
