<?php

use App\Support\Locale;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Paparan & bahasa')] class extends Component {
    /**
     * @return array<string, string> code => name, e.g. ms => Bahasa Melayu
     */
    public function locales(): array
    {
        return Locale::available();
    }
}; ?>

{{-- Theme, language and sound in one place. The header keeps its quick toggles; all of them share the same storage:
     theme = flux.appearance, sound = chessflow-snd (device), language = account preference via LocaleController. --}}
<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Paparan & bahasa') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Paparan & bahasa')" :subheading="__('Tema warna, bahasa dan bunyi ChessFlow.')">
        <section class="space-y-3">
            <flux:heading>{{ __('Tema warna') }}</flux:heading>
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" :label="__('Tema warna')" label:sr-only>
                <flux:radio value="light" icon="sun">{{ __('Cerah') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Gelap') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('Sistem') }}</flux:radio>
            </flux:radio.group>
            <flux:text class="text-sm">{{ __('Disimpan pada peranti ini. "Sistem" ikut tetapan peranti.') }}</flux:text>
        </section>

        <section class="mt-10 space-y-3" data-test="language">
            <flux:heading>{{ __('Bahasa') }}</flux:heading>
            <flux:radio.group variant="segmented" :label="__('Bahasa')" label:sr-only
                x-data="{ lang: '{{ app()->getLocale() }}' }" x-model="lang"
                x-init="$watch('lang', v => document.getElementById('locale-form-' + v)?.submit())">
                @foreach ($this->locales() as $code => $name)
                    <flux:radio :value="$code" :lang="$code">{{ $name }}</flux:radio>
                @endforeach
            </flux:radio.group>
            @foreach (array_keys($this->locales()) as $code)
                <form id="locale-form-{{ $code }}" method="POST" action="{{ route('locale.switch', $code) }}" class="hidden">@csrf</form>
            @endforeach
            <flux:text class="text-sm">{{ __('Disimpan pada akaun anda. Pelajaran ditulis dalam Bahasa Melayu.') }}</flux:text>
        </section>

        <section class="mt-10 space-y-3" data-test="sound">
            <flux:heading>{{ __('Bunyi') }}</flux:heading>
            <flux:radio.group variant="segmented" :label="__('Bunyi')" label:sr-only
                x-data="{ snd: (() => { try { return localStorage.getItem('chessflow-snd') === '0' ? 'off' : 'on' } catch (e) { return 'on' } })() }"
                x-model="snd"
                x-init="$watch('snd', v => { try { localStorage.setItem('chessflow-snd', v === 'on' ? '1' : '0') } catch (e) {} })">
                <flux:radio value="on" icon="speaker-wave">{{ __('Hidup') }}</flux:radio>
                <flux:radio value="off" icon="speaker-x-mark">{{ __('Senyap') }}</flux:radio>
            </flux:radio.group>
            <flux:text class="text-sm">{{ __('Bunyi langkah dan ganjaran semasa belajar dan bermain. Disimpan pada peranti ini.') }}</flux:text>
        </section>
    </x-pages::settings.layout>
</section>
