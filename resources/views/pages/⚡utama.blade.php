<?php

use App\Models\Level;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Belajar catur dalam Bahasa Melayu')] class extends Component {
    public const SUPPORT_EMAIL = 'wmafendi@gmail.com';

    public string $certCode = '';

    public function mount(): void
    {
        if (auth()->check()) {
            $this->redirectRoute('peta');
        }
    }

    public function checkCertificate(): void
    {
        $this->certCode = strtoupper(trim($this->certCode));

        $this->validate(
            ['certCode' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/']],
            ['certCode.regex' => __('Kod sijil hanya mengandungi huruf dan nombor.')],
            ['certCode' => __('kod sijil')],
        );

        $this->redirectRoute('sijil', $this->certCode);
    }

    public function with(): array
    {
        $levels = Level::query()
            ->orderBy('position')
            ->with(['lessons' => fn ($q) => $q->where('is_published', true)])
            ->get();

        $lessons = $levels->flatMap->lessons;

        return [
            'levels' => $levels,
            'lessonCount' => $lessons->count(),
            'examCount' => $lessons->filter->isExam()->count(),
            'marquee' => $lessons->reject->isExam()->pluck('title'),
            'email' => self::SUPPORT_EMAIL,
        ];
    }
}; ?>

<div class="lp">
    {{-- HERO --}}
    <section class="lp-hero">
        <div class="lp-hero-copy">
            <p class="lp-eyebrow"><span class="dot"></span> {{ __('Catur untuk kanak-kanak dan pemula, dalam Bahasa Melayu') }}</p>
            <h1>{!! __('Dari kenal buah<br>ke <em>sah mati</em> pertama.') !!}</h1>
            <p class="lp-lede">{{ __('ChessFlow mengajar catur langkah demi langkah di papan interaktif. Pak Kuda membimbing setiap gerakan, murid kutip bintang dan XP, dan setiap tahap berakhir dengan ujian dan sijil.') }}</p>
            <div class="lp-actions">
                <a class="cta" href="{{ route('murid.login') }}">{{ __('Masuk murid') }} <span aria-hidden="true">→</span></a>
                <a class="lp-btn" href="{{ route('login') }}">{{ __('Guru atau ibu bapa') }}</a>
            </div>
            <ul class="lp-trust">
                <li><i class="lp-tick" aria-hidden="true"></i>{{ __('Percuma untuk tempoh terhad') }}</li>
                <li><i class="lp-tick" aria-hidden="true"></i>{{ __('Murid tidak perlu e-mel') }}</li>
                <li><i class="lp-tick" aria-hidden="true"></i>{{ __('Terus dalam pelayar web') }}</li>
                <li><i class="lp-tick" aria-hidden="true"></i>{{ __('100% Bahasa Melayu') }}</li>
            </ul>
        </div>

        <div class="lp-stage" aria-hidden="true">
            <div class="lp-board">
                @for ($i = 0; $i < 64; $i++)
                    <span class="{{ (intdiv($i, 8) + $i) % 2 ? 'd' : 'l' }}"></span>
                @endfor
                <i class="pc bR" style="--x:0;--y:0"></i>
                <i class="pc bK" style="--x:4;--y:0"></i>
                <i class="pc bP" style="--x:5;--y:1"></i>
                <i class="pc bP" style="--x:6;--y:1"></i>
                <i class="pc bP" style="--x:7;--y:1"></i>
                <i class="pc wP" style="--x:5;--y:6"></i>
                <i class="pc wP" style="--x:6;--y:6"></i>
                <i class="pc wP" style="--x:7;--y:6"></i>
                <i class="pc wK" style="--x:6;--y:7"></i>
                <b class="lp-ring" style="--x:0;--y:0"></b>
                <b class="lp-ring" style="--x:4;--y:0"></b>
                <i class="pc wN lp-knight"></i>
            </div>
            <div class="lp-bubble">
                <span class="lp-av"><i class="pc pk"></i></span>
                <span>{!! __('<b>Fork!</b> Kuda serang raja dan tir serentak.') !!}</span>
            </div>
            <div class="lp-chip lp-chip-xp"><span class="xp-ico">XP</span> +20 XP</div>
            <div class="lp-chip lp-chip-star"><x-chessflow.stars :n="3" /></div>
            <div class="lp-chip lp-chip-flame"><x-chessflow.flame /> {{ __('Streak 7 hari') }}</div>
        </div>
    </section>

    {{-- STATS --}}
    <section class="lp-stats" aria-label="{{ __('ChessFlow dalam angka') }}">
        <div><b>{{ $lessonCount }}</b><span>{{ __('pelajaran interaktif') }}</span></div>
        <div><b>{{ $levels->count() }}</b><span>{{ __('tahap, asas hingga endgame') }}</span></div>
        <div><b>{{ $examCount }}</b><span>{{ __('ujian bersijil') }}</span></div>
        <div><b>3</b><span>{{ __('tahap lawan Pak Kuda') }}</span></div>
    </section>

    {{-- MARQUEE --}}
    @if ($marquee->isNotEmpty())
        <div class="lp-marquee" aria-hidden="true">
            <div class="lp-marquee-track">
                @foreach ([1, 2] as $copy)
                    @foreach ($marquee as $title)
                        <span>{{ $title }}</span>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    {{-- SHOTS --}}
    <section class="lp-section" id="lihat">
        <header class="lp-head">
            <small>{{ __('Lihat di dalam') }}</small>
            <h2>{{ __('Peta yang jelas. Pelajaran yang hidup.') }}</h2>
            <p>{{ __('Murid nampak di mana mereka berada, apa seterusnya, dan Pak Kuda menerangkan setiap langkah di papan.') }}</p>
        </header>
        <div class="lp-shots">
            <figure class="lp-shot lp-shot-back">
                <div class="lp-shot-bar" aria-hidden="true"><i></i><i></i><i></i><span>{{ __('Peta pelajaran') }}</span></div>
                <img src="{{ asset('images/landing/peta.jpg') }}" width="1650" height="1080" loading="lazy" decoding="async"
                     alt="{{ __('Peta pelajaran ChessFlow: sambung ke pelajaran seterusnya, streak teka-teki harian dan pelajaran yang sudah dapat bintang.') }}">
            </figure>
            <figure class="lp-shot lp-shot-front">
                <div class="lp-shot-bar" aria-hidden="true"><i></i><i></i><i></i><span>{{ __('Pelajaran: Fork Kuda') }}</span></div>
                <img src="{{ asset('images/landing/pelajaran.jpg') }}" width="1650" height="1080" loading="lazy" decoding="async"
                     alt="{{ __('Skrin pelajaran Fork Kuda: papan catur dengan anak panah serangan dan penerangan Pak Kuda di sebelah.') }}">
            </figure>
        </div>
    </section>

    {{-- PATH --}}
    <section class="lp-section" id="laluan">
        <header class="lp-head">
            <small>{{ __('Laluan pembelajaran') }}</small>
            <h2>{{ __('Satu peta, :n tahap, satu langkah pada satu masa.', ['n' => $levels->count()]) }}</h2>
            <p>{{ __('Setiap pelajaran membuka pelajaran seterusnya. Tiada lompatan yang mengelirukan, cuma kemajuan yang boleh dilihat.') }}</p>
        </header>
        <ol class="lp-path">
            @foreach ($levels as $level)
                @php
                    $regular = $level->lessons->reject->isExam();
                    // One distinct piece per level; fall back to the first lesson's icon for new levels.
                    $icon = [1 => 'wR', 2 => 'wB', 3 => 'wN', 4 => 'wP', 5 => 'wK'][$level->number] ?? $regular->first()?->icon ?? 'wP';
                @endphp
                <li class="lp-step">
                    <span class="lp-step-node"><i class="pc {{ $icon }}"></i></span>
                    <div class="lp-step-body">
                        <small>{{ __('Tahap :n', ['n' => $level->number]) }}</small>
                        <h3>{{ $level->name }}</h3>
                        <p>{{ $level->note }}</p>
                        <ul>
                            @foreach ($regular->take(3) as $lesson)
                                <li>{{ $lesson->title }}</li>
                            @endforeach
                            @if ($regular->count() > 3)
                                <li class="more">{{ __('+:n lagi', ['n' => $regular->count() - 3]) }}</li>
                            @endif
                        </ul>
                        <span class="lp-step-foot">{{ __(':n pelajaran · berakhir dengan ujian', ['n' => $level->lessons->count()]) }}</span>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- FEATURES --}}
    <section class="lp-section" id="ciri">
        <header class="lp-head">
            <small>{{ __('Ciri-ciri') }}</small>
            <h2>{{ __('Semua yang pemain catur kecil perlukan.') }}</h2>
        </header>
        <div class="lp-bento">
            <article class="lp-tile lp-tile-big">
                <div class="lp-tile-text">
                    <span class="lp-ico"><i class="pc pk"></i></span>
                    <h3>{{ __('Papan interaktif dengan jurulatih sendiri') }}</h3>
                    <p>{{ __('Murid gerakkan buah sendiri, bukan sekadar membaca. Pak Kuda menerangkan setiap konsep, memberi petunjuk bila tersekat dan membetulkan langkah yang salah dengan lembut.') }}</p>
                </div>
                <div class="lp-chat" aria-hidden="true">
                    <p class="me">{{ __('Kenapa langkah ini tak boleh?') }}</p>
                    <p class="pk"><span class="lp-av"><i class="pc pk"></i></span>{{ __('Raja awak akan kena sah. Cuba lindungi dia dulu!') }}</p>
                    <p class="pk ok"><span class="lp-av"><i class="pc pk"></i></span>{{ __('Bagus! Itu langkah terbaik. +3 bintang') }}</p>
                </div>
            </article>

            <article class="lp-tile lp-tile-sun">
                <span class="lp-ico flame"><x-chessflow.flame /></span>
                <h3>{{ __('Teka-teki harian') }}</h3>
                <p>{{ __('Satu teka-teki baharu setiap hari. Selesaikan untuk kekalkan streak dan bina tabiat berlatih.') }}</p>
            </article>

            <article class="lp-tile lp-tile-dark">
                <span class="lp-ico"><i class="pc bK"></i></span>
                <h3>{{ __('Main lawan Pak Kuda') }}</h3>
                <p>{{ __('Perlawanan penuh menentang enjin Stockfish dalam tiga tahap.') }}</p>
                <div class="lp-levels" aria-label="{{ __('Tahap lawan') }}">
                    <span>{{ __('Mudah') }}</span><span>{{ __('Sederhana') }}</span><span>{{ __('Sukar') }}</span>
                </div>
            </article>

            <article class="lp-tile">
                <span class="lp-ico star"><i class="star-ico"></i></span>
                <h3>{{ __('Bintang dan XP') }}</h3>
                <p>{{ __('Sehingga tiga bintang setiap pelajaran. XP dikira oleh pelayan, jadi markah sentiasa adil.') }}</p>
            </article>

            <article class="lp-tile">
                <span class="lp-ico"><x-chessflow.medal /></span>
                <h3>{{ __('Ujian dan sijil') }}</h3>
                <p>{{ __('Lulus ujian tahap (70% ke atas) untuk dapat sijil yang boleh dicetak, lengkap dengan kod pengesahan.') }}</p>
            </article>

            <article class="lp-tile">
                <span class="lp-ico lang">{{ __('BM') }}</span>
                <h3>{{ __('Bahasa Melayu sepenuhnya') }}</h3>
                <p>{{ __('Istilah, arahan dan penerangan ditulis khas dalam Bahasa Melayu yang mudah difahami kanak-kanak.') }}</p>
            </article>
        </div>
    </section>

    {{-- AUDIENCE --}}
    <section class="lp-section" id="manfaat">
        <header class="lp-head">
            <small>{{ __('Manfaat') }}</small>
            <h2>{{ __('Dibina untuk murid, ibu bapa dan guru.') }}</h2>
        </header>
        <div class="lp-aud">
            <article>
                <span class="lp-aud-tag"><i class="pc wP"></i> {{ __('Murid') }}</span>
                <h3>{{ __('Belajar sambil bermain') }}</h3>
                <ul>
                    <li>{{ __('Langkah kecil yang jelas, ikut rentak sendiri') }}</li>
                    <li>{{ __('Ganjaran segera: bintang, XP dan streak') }}</li>
                    <li>{{ __('Masuk dengan nama pengguna dan PIN sahaja') }}</li>
                    <li>{{ __('Sijil untuk setiap tahap yang lulus') }}</li>
                </ul>
            </article>
            <article>
                <span class="lp-aud-tag"><i class="pc wQ"></i> {{ __('Ibu bapa') }}</span>
                <h3>{{ __('Tahu kemajuan anak') }}</h3>
                <ul>
                    <li>{{ __('Cipta akaun anak tanpa e-mel') }}</li>
                    <li>{{ __('Pautkan akaun anak yang sedia ada') }}</li>
                    <li>{{ __('Lihat pelajaran, bintang dan XP anak') }}</li>
                    <li>{{ __('Tetapkan PIN baharu bila anak terlupa') }}</li>
                </ul>
            </article>
            <article>
                <span class="lp-aud-tag"><i class="pc wK"></i> {{ __('Guru') }}</span>
                <h3>{{ __('Urus kelas dengan mudah') }}</h3>
                <ul>
                    <li>{{ __('Cipta kelas dan kongsi kod sertai') }}</li>
                    <li>{{ __('Cipta akaun ramai murid sekali gus') }}</li>
                    <li>{{ __('Jadual kemajuan setiap murid, setiap pelajaran') }}</li>
                    <li>{{ __('Sesuai untuk kelab catur dan kokurikulum') }}</li>
                </ul>
            </article>
        </div>
    </section>

    {{-- HOW --}}
    <section class="lp-section" id="cara">
        <header class="lp-head">
            <small>{{ __('Cara mula') }}</small>
            <h2>{{ __('Tiga langkah ke papan catur.') }}</h2>
        </header>
        <ol class="lp-how">
            <li>
                <span class="n">1</span>
                <h3>{{ __('Daftar akaun dewasa') }}</h3>
                <p>{{ __('Ibu bapa atau guru daftar dengan e-mel. Akaun guru diaktifkan oleh pentadbir.') }}</p>
            </li>
            <li>
                <span class="n">2</span>
                <h3>{{ __('Cipta akaun murid') }}</h3>
                <p>{{ __('Dapatkan nama pengguna dan PIN untuk setiap murid. Tiada e-mel kanak-kanak diperlukan.') }}</p>
            </li>
            <li>
                <span class="n">3</span>
                <h3>{{ __('Murid mula belajar') }}</h3>
                <p>{{ __('Murid masuk, buka peta dan mula pelajaran pertama: Papan Catur.') }}</p>
            </li>
        </ol>
    </section>

    {{-- PRIVACY --}}
    <section class="lp-privacy" id="privasi">
        <div class="lp-privacy-in">
            <div>
                <small>{{ __('Privasi kanak-kanak') }}</small>
                <h2>{{ __('Data minimum. Tiada e-mel murid. Tiada iklan.') }}</h2>
                <p class="lp-fine">{{ __('Tiada iklan untuk tempoh terhad.') }}</p>
                <p>{{ __('Kami hanya simpan apa yang perlu untuk pembelajaran. Kemajuan murid hanya dilihat oleh murid itu, guru kelasnya dan ibu bapa yang dipautkan.') }}</p>
                <a class="lp-link" href="{{ route('privasi') }}">{{ __('Baca dasar privasi (PDPA 2010) →') }}</a>
            </div>
            <ul>
                <li><b>{{ __('Tanpa e-mel') }}</b><span>{{ __('Murid masuk dengan nama pengguna dan PIN.') }}</span></li>
                <li><b>{{ __('Nama pilihan sendiri') }}</b><span>{{ __('Nama pada sijil ditulis dan diubah oleh murid.') }}</span></li>
                <li><b>{{ __('Akses terhad') }}</b><span>{{ __('Hanya guru dan ibu bapa yang dipautkan.') }}</span></li>
            </ul>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="lp-section lp-faq-wrap" id="soalan">
        <header class="lp-head">
            <small>{{ __('Soalan lazim') }}</small>
            <h2>{{ __('Ada soalan?') }}</h2>
            <p>{!! __('Tak jumpa jawapan? E-mel kami di :email.', ['email' => '<a href="mailto:'.e($email).'">'.e($email).'</a>']) !!}</p>
        </header>
        <div class="lp-faq">
            <details>
                <summary>{{ __('Berapa kos?') }}</summary>
                <p>{!! __('ChessFlow <b>percuma untuk tempoh terhad</b>. Jika kami memperkenalkan bayaran, ibu bapa dan guru akan dimaklumkan terlebih dahulu, dan tiada caj tanpa persetujuan anda. Lihat :terms.', ['terms' => '<a href="'.route('terma').'#harga">'.e(__('Terma Penggunaan')).'</a>']) !!}</p>
            </details>
            <details>
                <summary>{{ __('Siapa yang sesuai guna ChessFlow?') }}</summary>
                <p>{{ __('Kanak-kanak sekolah rendah dan sesiapa sahaja yang baru belajar catur. Tahap 1 bermula dari kenal papan, jadi tiada pengalaman diperlukan.') }}</p>
            </details>
            <details>
                <summary>{{ __('Perlu pasang aplikasi?') }}</summary>
                <p>{{ __('Tidak. ChessFlow berjalan terus dalam pelayar web di komputer atau tablet.') }}</p>
            </details>
            <details>
                <summary>{{ __('Anak saya lupa PIN. Bagaimana?') }}</summary>
                <p>{{ __('Guru atau ibu bapa yang dipautkan boleh tetapkan PIN baharu dari halaman Anak atau Kelas.') }}</p>
            </details>
            <details>
                <summary>{{ __('Bagaimana mendapatkan akaun guru?') }}</summary>
                <p>{!! __('Daftar akaun biasa, kemudian e-mel :email dengan nama sekolah atau kelab anda. Pentadbir akan aktifkan peranan guru.', ['email' => '<a href="mailto:'.e($email).'?subject=Permohonan%20akaun%20guru%20ChessFlow">'.e($email).'</a>']) !!}</p>
            </details>
            <details>
                <summary>{{ __('Bagaimana menyemak sijil?') }}</summary>
                <p>{!! __('Setiap sijil ada kod pengesahan 10 aksara. Masukkan kod itu di bahagian :check di bawah.', ['check' => '<a href="#semak-sijil">'.e(__('Semak sijil')).'</a>']) !!}</p>
            </details>
        </div>
    </section>

    {{-- CTA --}}
    <section class="lp-cta">
        <div class="lp-cta-in">
            <span class="lp-cta-piece" aria-hidden="true"><i class="pc pk"></i></span>
            <h2>{{ __('Papan sudah tersedia. Giliran awak.') }}</h2>
            <p>{{ __('Murid masuk dengan nama pengguna dan PIN. Ibu bapa dan guru boleh daftar dalam seminit.') }}</p>
            <div class="lp-actions">
                <a class="lp-btn lp-btn-sun" href="{{ route('murid.login') }}">{{ __('Masuk murid') }}</a>
                @if (Route::has('register'))
                    <a class="lp-btn lp-btn-line" href="{{ route('register') }}">{{ __('Daftar ibu bapa / guru') }}</a>
                @endif
            </div>
        </div>
    </section>

    {{-- LINKS --}}
    <section class="lp-links" aria-label="{{ __('Pautan') }}">
        <div>
            <h3>{{ __('Mula') }}</h3>
            <ul>
                <li><a href="{{ route('murid.login') }}">{{ __('Masuk murid') }}</a></li>
                <li><a href="{{ route('login') }}">{{ __('Log masuk guru / ibu bapa') }}</a></li>
                @if (Route::has('register'))
                    <li><a href="{{ route('register') }}">{{ __('Daftar akaun') }}</a></li>
                @endif
            </ul>
        </div>
        <div>
            <h3>{{ __('Maklumat') }}</h3>
            <ul>
                <li><a href="#laluan">{{ __('Laluan pembelajaran') }}</a></li>
                <li><a href="#ciri">{{ __('Ciri-ciri') }}</a></li>
                <li><a href="{{ route('tentang') }}">{{ __('Tentang ChessFlow') }}</a></li>
                <li><a href="{{ route('privasi') }}">{{ __('Dasar privasi') }}</a></li>
                <li><a href="{{ route('terma') }}">{{ __('Terma penggunaan') }}</a></li>
                <li><a href="{{ route('tentang') }}#lesen">{{ __('Perisian dan lesen') }}</a></li>
            </ul>
        </div>
        <div>
            <h3>{{ __('Sokongan') }}</h3>
            <ul>
                <li><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                <li><a href="mailto:{{ $email }}?subject=Permohonan%20akaun%20guru%20ChessFlow">{{ __('Mohon akaun guru') }}</a></li>
                <li><a href="mailto:{{ $email }}?subject=Laporan%20masalah%20ChessFlow">{{ __('Lapor masalah') }}</a></li>
            </ul>
        </div>
        <div id="semak-sijil">
            <h3>{{ __('Semak sijil') }}</h3>
            <form wire:submit="checkCertificate" class="lp-cert">
                <label class="sr-only" for="cert-code">{{ __('Kod sijil') }}</label>
                <input id="cert-code" type="text" wire:model="certCode" maxlength="20" placeholder="{{ __('Kod sijil') }}" autocapitalize="characters" spellcheck="false" required>
                <button type="submit">{{ __('Semak') }}</button>
            </form>
            @error('certCode') <p class="lp-err">{{ $message }}</p> @enderror
        </div>
        <p class="lp-legal">© {{ now()->year }} ChessFlow · WM AFENDI ENTERPRISE · {{ __('Belajar catur dalam Bahasa Melayu untuk kanak-kanak dan pemula') }}</p>
    </section>
</div>
