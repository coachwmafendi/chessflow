@php
    $user = auth()->user();
    $pageTitle = isset($title) ? $title.' · ChessFlow' : 'ChessFlow';
    $description = 'ChessFlow: belajar catur langkah demi langkah dalam Bahasa Melayu untuk kanak-kanak dan pemula. Papan interaktif, teka-teki harian, main lawan Pak Kuda, ujian dan sijil.';
@endphp
<!DOCTYPE html>
<html lang="ms">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $description }}" />
        <link rel="canonical" href="{{ url()->current() }}" />
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="ChessFlow" />
        <meta property="og:locale" content="ms_MY" />
        <meta property="og:title" content="{{ $pageTitle }}" />
        <meta property="og:description" content="{{ $description }}" />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta property="og:image" content="{{ asset('images/og-chessflow.png') }}" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta property="og:image:alt" content="ChessFlow: belajar catur dalam Bahasa Melayu" />
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
                <a class="logo" href="{{ $user ? route('peta') : route('home') }}" aria-label="ChessFlow, ke peta">
                    <span class="mark"><i class="pc pk"></i></span><b>Chess<span>Flow</span></b>
                </a>
                <div class="stats">
                    @auth
                        <span class="pill" title="Jumlah bintang"><i class="star-ico ico"></i><span data-stat="stars">{{ $user->totalStars() }}</span></span>
                        <span class="pill" title="Mata pengalaman"><span class="xp-ico">XP</span><span data-stat="xp">{{ $user->xp }}</span></span>
                        <a class="pill btn play" href="{{ route('main') }}">Main</a>
                        @if ($user->isStaff())
                            <a class="pill btn" href="{{ route('guru') }}">Kelas</a>
                        @endif
                        @can('guardian')
                            <a class="pill btn" href="{{ route('anak') }}">Anak</a>
                        @endcan
                        @can('admin')
                            <a class="pill btn" href="{{ url('/admin') }}">Admin</a>
                        @endcan
                    @endauth
                    @guest
                        @unless (request()->routeIs('murid.login'))
                            <a class="pill btn play" href="{{ route('murid.login') }}">Masuk murid</a>
                        @endunless
                        @unless (request()->routeIs('login'))
                            <a class="pill btn" href="{{ route('login') }}">Log masuk</a>
                        @endunless
                    @endguest
                    <button class="pill btn" type="button" data-theme-toggle title="Tukar tema warna">Tema: Sistem</button>
                    <button class="pill btn" type="button" data-sound-toggle aria-pressed="true">Bunyi: Hidup</button>
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="pill btn" type="submit">Keluar</button>
                        </form>
                    @endauth
                </div>
            </header>

            <main>
                {{ $slot }}
            </main>

            <footer class="foot">
                <span>ChessFlow · Belajar catur dalam Bahasa Melayu untuk kanak-kanak dan pemula</span>
                <span class="foot-r"><a href="{{ route('tentang') }}">Tentang, privasi dan lesen</a></span>
            </footer>
        </div>
    </body>
</html>
