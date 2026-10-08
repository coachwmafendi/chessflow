<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Belajar catur')] class extends Component {
    public function mount(): void
    {
        if (auth()->check()) {
            $this->redirectRoute('peta');
        }
    }
}; ?>

<div>
    <section class="hero">
        <div class="avatar"><i class="pc wN"></i></div>
        <div>
            <h1>Jom belajar catur!</h1>
            <p>Saya Pak Kuda. Di ChessFlow, kanak-kanak dan pemula belajar catur langkah demi langkah dalam Bahasa Melayu: kenal buah, taktik, strategi dan endgame. Kutip bintang, kumpul XP dan dapatkan sijil setiap tahap.</p>
            <div class="hero-row">
                <a class="cta" href="{{ route('murid.login') }}">Masuk murid</a>
                <a class="ghost" href="{{ route('login') }}">Guru atau ibu bapa</a>
                @if (Route::has('register'))
                    <a class="ghost" href="{{ route('register') }}">Daftar</a>
                @endif
            </div>
        </div>
    </section>

    <section class="quick">
        <div class="qcard">
            <span class="qico"><i class="pc wP"></i></span>
            <span><b>32 pelajaran, 5 tahap</b><small>Dari papan catur hingga kedudukan Lucena</small></span>
        </div>
        <div class="qcard">
            <span class="qico flame"><x-chessflow.flame /></span>
            <span><b>Teka-teki setiap hari</b><small>Bina tabiat dengan streak harian</small></span>
        </div>
        <div class="qcard">
            <span class="qico"><i class="pc bK"></i></span>
            <span><b>Main lawan Pak Kuda</b><small>Tiga tahap: mudah, sederhana, sukar</small></span>
        </div>
        <div class="qcard">
            <span class="qico"><i class="pc wQ"></i></span>
            <span><b>Untuk guru dan ibu bapa</b><small>Cipta akaun murid tanpa e-mel dan pantau kemajuan</small></span>
        </div>
    </section>
</div>
