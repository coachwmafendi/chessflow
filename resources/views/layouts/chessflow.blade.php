@php
    $user = auth()->user();
    $pageTitle = isset($title) ? __($title).' · ChessFlow' : 'ChessFlow';
    $description = __('ChessFlow: belajar catur langkah demi langkah dalam Bahasa Melayu untuk kanak-kanak dan pemula. Papan interaktif, teka-teki harian, main lawan Pak Kuda, ujian dan sijil.');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $description }}" />
        <link rel="canonical" href="{{ url()->current() }}" />
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="ChessFlow" />
        <meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_GB' : 'ms_MY' }}" />
        <meta property="og:title" content="{{ $pageTitle }}" />
        <meta property="og:description" content="{{ $description }}" />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta property="og:image" content="{{ asset('images/og-chessflow.png') }}" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta property="og:image:alt" content="{{ __('ChessFlow: belajar catur dalam Bahasa Melayu') }}" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="theme-color" content="#0B8577" />
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @include('partials.theme-init')
        @fonts
        @vite(['resources/css/chessflow.css', 'resources/ts/app.ts'])
    </head>
    <body>
        <div class="app">
            <header class="top">
                <a class="logo" href="{{ $user ? route('peta') : route('home') }}" aria-label="{{ __('ChessFlow, ke peta') }}">
                    <span class="mark"><i class="pc pk"></i></span><b>Chess<span>Flow</span></b>
                </a>
                <div class="stats">
                    @auth
                        <span class="pill" title="{{ __('Jumlah bintang') }}"><i class="star-ico ico"></i><span data-stat="stars">{{ $user->totalStars() }}</span></span>
                        <span class="pill" title="{{ __('Mata pengalaman') }}"><span class="xp-ico">XP</span><span data-stat="xp">{{ $user->xp }}</span></span>
                        <a class="pill btn play" href="{{ route('main') }}">{{ __('Main') }}</a>
                        @if ($user->isStaff())
                            <a class="pill btn" href="{{ route('guru') }}">{{ __('Kelas') }}</a>
                        @endif
                        @can('guardian')
                            <a class="pill btn" href="{{ route('anak') }}">{{ __('Anak') }}</a>
                        @endcan
                        @can('admin')
                            <a class="pill btn" href="{{ url('/admin') }}">Admin</a>
                        @endcan
                    @endauth
                    @guest
                        @unless (request()->routeIs('murid.login'))
                            <a class="pill btn play" href="{{ route('murid.login') }}">{{ __('Masuk murid') }}</a>
                        @endunless
                        @unless (request()->routeIs('login'))
                            <a class="pill btn" href="{{ route('login') }}">{{ __('Log masuk') }}</a>
                        @endunless
                    @endguest
                    <button class="pill btn" type="button" data-theme-toggle title="{{ __('Tukar tema warna') }}">{{ __('Tema: Sistem') }}</button>
                    <button class="pill btn" type="button" data-sound-toggle aria-pressed="true">{{ __('Bunyi: Hidup') }}</button>
                    <x-locale-switch />
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="pill btn" type="submit">{{ __('Keluar') }}</button>
                        </form>
                    @endauth
                </div>
            </header>

            <main>
                {{ $slot }}
            </main>

            <footer class="foot">
                <span>© {{ now()->year }} ChessFlow · WM AFENDI ENTERPRISE · {{ __('Belajar catur dalam Bahasa Melayu untuk kanak-kanak dan pemula') }}</span>
                <span class="foot-r"><a href="{{ route('tentang') }}">{{ __('Tentang') }}</a><a href="{{ route('privasi') }}">{{ __('Privasi') }}</a><a href="{{ route('terma') }}">{{ __('Terma') }}</a></span>
            </footer>
        </div>
    </body>
</html>
