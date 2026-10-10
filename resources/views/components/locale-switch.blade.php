@props(['class' => 'pill btn'])
{{-- One-click language switch. The label is written in the target language on purpose. --}}
@php
    $target = App\Support\Locale::other();
    $labels = ['ms' => ['BM', 'Tukar ke Bahasa Melayu'], 'en' => ['EN', 'Switch to English']];
    [$short, $long] = $labels[$target] ?? [strtoupper($target), $target];
@endphp
<form method="POST" action="{{ route('locale.switch', $target) }}" {{ $attributes->merge(['class' => 'locale-switch']) }}>
    @csrf
    <button class="{{ $class }}" type="submit" lang="{{ $target }}" aria-label="{{ $long }}" title="{{ $long }}">{{ $short }}</button>
</form>
