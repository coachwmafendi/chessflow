@props(['heading' => 'Belajar catur langkah demi langkah', 'text' => 'Bersama Pak Kuda: kenal buah, taktik, strategi dan endgame. Kutip bintang, kumpul XP dan dapatkan sijil setiap tahap.'])
{{-- Left column of the login/register pages: ChessFlow illustration (pieces: rhosgfx, CC0). --}}
<div class="relative hidden h-full flex-col overflow-hidden p-10 text-white lg:flex" style="background: linear-gradient(160deg, #0B8577 0%, #075E54 100%)">
    {{-- faint chessboard pattern --}}
    <div class="pointer-events-none absolute inset-0 opacity-[0.07]"
         style="background-image: conic-gradient(#fff 25%, transparent 0 50%, #fff 0 75%, transparent 0); background-size: 112px 112px"></div>

    <a href="{{ route('home') }}" class="relative z-20 flex items-center gap-2 text-lg font-semibold">
        <span class="flex size-10 items-center justify-center rounded-xl bg-white/15">
            <img src="/images/pieces/wN.svg" alt="" class="size-8">
        </span>
        ChessFlow
    </a>

    <div class="relative z-10 flex flex-1 items-center justify-center">
        <div class="relative size-72">
            <div class="absolute inset-0 rounded-full bg-white/10 ring-1 ring-white/20"></div>
            <img src="/images/pieces/wN.svg" alt="Pak Kuda" class="absolute inset-6 size-60 drop-shadow-2xl">
            <img src="/images/pieces/bK.svg" alt="" class="absolute -top-6 -right-10 size-24 rotate-12 drop-shadow-xl">
            <img src="/images/pieces/wQ.svg" alt="" class="absolute -bottom-4 -left-12 size-24 -rotate-12 drop-shadow-xl">
            <img src="/images/pieces/bP.svg" alt="" class="absolute top-10 -left-14 size-14 -rotate-6 opacity-90">
            <img src="/images/pieces/wR.svg" alt="" class="absolute -right-12 bottom-8 size-16 rotate-6 opacity-90">
        </div>
    </div>

    <div class="relative z-20 space-y-3">
        <h2 class="text-3xl font-bold leading-tight">{{ $heading }}</h2>
        <p class="max-w-md text-white/85">{{ $text }}</p>
        <div class="flex flex-wrap gap-2 pt-1 text-sm">
            <span class="rounded-full bg-white/15 px-3 py-1">32 pelajaran</span>
            <span class="rounded-full bg-white/15 px-3 py-1">Teka-teki harian</span>
            <span class="rounded-full bg-white/15 px-3 py-1">Sijil setiap tahap</span>
        </div>
    </div>
</div>
