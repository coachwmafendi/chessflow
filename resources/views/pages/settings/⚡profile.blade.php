<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tetapan profil')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }


    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        {{-- Account type: what this account can do, and how a parent becomes a teacher. --}}
        @php $role = auth()->user()->role; @endphp
        <section class="mt-10 space-y-3" data-test="account-type">
            <flux:heading>{{ __('Jenis akaun') }}</flux:heading>
            <div><flux:badge color="teal">{{ __("roles.{$role->value}") }}</flux:badge></div>
            @if ($role === App\Enums\Role::IbuBapa)
                <flux:text>{{ __('Anda seorang guru? Minta pentadbir aktifkan akaun guru untuk mencipta kelas dan memberi tugasan.') }}</flux:text>
                <flux:button icon="envelope" :href="'mailto:wmafendi@gmail.com?subject='.rawurlencode(__('Permohonan akaun guru ChessFlow')).'&body='.rawurlencode(__('E-mel akaun: :email', ['email' => auth()->user()->email]))">{{ __('Mohon akaun guru') }}</flux:button>
            @elseif ($role === App\Enums\Role::Guru)
                <flux:text>{{ __('Anda boleh mencipta kelas, menambah murid dan memberi tugasan di halaman Kelas.') }}</flux:text>
            @elseif ($role === App\Enums\Role::Admin)
                <flux:text>{{ __('Anda boleh mengurus pengguna dan pelajaran di panel pentadbir.') }}</flux:text>
            @endif
        </section>

        <flux:text class="mt-10 text-sm">{{ __('Muat turun atau padam data akaun anda di') }} <flux:link :href="route('data.edit')" wire:navigate>{{ __('Data & privasi') }}</flux:link>.</flux:text>
    </x-pages::settings.layout>
</section>
