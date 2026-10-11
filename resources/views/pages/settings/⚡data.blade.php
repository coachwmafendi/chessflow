<?php

use App\Support\UserDataExport;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Data & privasi')] class extends Component {
    /** A copy of everything held about this account, as JSON (PDPA access / portability). */
    public function downloadMyData(UserDataExport $export)
    {
        return $export->download(Auth::user());
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail || Auth::user()->hasVerifiedEmail();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Data & privasi')" :subheading="__('Lihat, muat turun atau padam data akaun anda.')">
        <section class="space-y-3">
            <flux:heading>{{ __('Data yang kami simpan untuk akaun ini') }}</flux:heading>
            <ul class="list-disc space-y-1 ps-5 text-sm text-zinc-600 dark:text-zinc-300">
                <li>{{ __('Nama dan alamat e-mel.') }}</li>
                <li>{{ __('Kata laluan dalam bentuk hash, dan tetapan keselamatan pilihan (pengesahan dua faktor, passkey).') }}</li>
                @if (auth()->user()->isStaff())
                    <li>{{ __('Kelas, kod sertai dan tugasan yang anda cipta.') }}</li>
                @endif
                @can('guardian')
                    <li>{{ __('Pautan kepada akaun anak anda.') }}</li>
                @endcan
            </ul>
            <flux:text>
                {{ __('Baca') }} <flux:link :href="route('privasi')">{{ __('Dasar Privasi') }}</flux:link>
                {{ __('dan') }} <flux:link :href="route('terma')">{{ __('Terma Penggunaan') }}</flux:link>.
            </flux:text>
        </section>

        <section class="mt-10 space-y-3">
            <flux:heading>{{ __('Muat turun data saya') }}</flux:heading>
            <flux:subheading>{{ __('Salinan semua data akaun ini dalam format JSON (boleh dibaca mesin).') }}</flux:subheading>
            <flux:button wire:click="downloadMyData" icon="arrow-down-tray" data-test="download-my-data">{{ __('Muat turun data saya') }}</flux:button>
        </section>

        @can('guardian')
            <section class="mt-10 space-y-3">
                <flux:heading>{{ __('Data anak') }}</flux:heading>
                <flux:text>{{ __('Data anak diurus di halaman Anak: muat turun salinan atau padam akaun anak di sana.') }}</flux:text>
                <flux:button :href="route('anak')" icon="users" variant="ghost">{{ __('Ke halaman Anak') }}</flux:button>
            </section>
        @endcan

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
            <flux:text class="mt-3 text-sm">{{ __('Memadam akaun anda tidak memadam akaun anak atau murid anda. Kelas yang anda cipta akan dipadam.') }}</flux:text>
        @endif
    </x-pages::settings.layout>
</section>
