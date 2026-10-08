@php
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="ms">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>{{ isset($title) ? $title.' · ChessFlow' : 'ChessFlow' }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @fonts
        @vite(['resources/css/chessflow.css', 'resources/ts/app.ts'])
    </head>
    <body>
        <div class="app">
            <header class="top">
                <a class="logo" href="{{ $user ? route('peta') : route('home') }}" aria-label="ChessFlow, ke peta">
                    <span class="mark"><i class="pc wN"></i></span><b>Chess<span>Flow</span></b>
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
