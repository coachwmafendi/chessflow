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
            ['certCode.regex' => 'Kod sijil hanya mengandungi huruf dan nombor.'],
            ['certCode' => 'kod sijil'],
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
            <p class="lp-eyebrow"><span class="dot"></span> Catur untuk kanak-kanak dan pemula, dalam Bahasa Melayu</p>
            <h1>Dari kenal buah<br>ke <em>sah mati</em> pertama.</h1>
            <p class="lp-lede">ChessFlow mengajar catur langkah demi langkah di papan interaktif. Pak Kuda membimbing setiap gerakan, murid kutip bintang dan XP, dan setiap tahap berakhir dengan ujian dan sijil.</p>
            <div class="lp-actions">
                <a class="cta" href="{{ route('murid.login') }}">Masuk murid <span aria-hidden="true">→</span></a>
                <a class="lp-btn" href="{{ route('login') }}">Guru atau ibu bapa</a>
            </div>
            <ul class="lp-trust">
                <li><i class="lp-tick" aria-hidden="true"></i>Murid tidak perlu e-mel</li>
                <li><i class="lp-tick" aria-hidden="true"></i>Terus dalam pelayar web</li>
                <li><i class="lp-tick" aria-hidden="true"></i>100% Bahasa Melayu</li>
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
                <span><b>Fork!</b> Kuda serang raja dan tir serentak.</span>
            </div>
            <div class="lp-chip lp-chip-xp"><span class="xp-ico">XP</span> +20 XP</div>
            <div class="lp-chip lp-chip-star"><x-chessflow.stars :n="3" /></div>
            <div class="lp-chip lp-chip-flame"><x-chessflow.flame /> Streak 7 hari</div>
        </div>
    </section>

    {{-- STATS --}}
    <section class="lp-stats" aria-label="ChessFlow dalam angka">
        <div><b>{{ $lessonCount }}</b><span>pelajaran interaktif</span></div>
        <div><b>{{ $levels->count() }}</b><span>tahap, asas hingga endgame</span></div>
        <div><b>{{ $examCount }}</b><span>ujian bersijil</span></div>
        <div><b>3</b><span>tahap lawan Pak Kuda</span></div>
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
            <small>Lihat di dalam</small>
            <h2>Peta yang jelas. Pelajaran yang hidup.</h2>
            <p>Murid nampak di mana mereka berada, apa seterusnya, dan Pak Kuda menerangkan setiap langkah di papan.</p>
        </header>
        <div class="lp-shots">
            <figure class="lp-shot lp-shot-back">
                <div class="lp-shot-bar" aria-hidden="true"><i></i><i></i><i></i><span>Peta pelajaran</span></div>
                <img src="{{ asset('images/landing/peta.jpg') }}" width="1650" height="1080" loading="lazy" decoding="async"
                     alt="Peta pelajaran ChessFlow: sambung ke pelajaran seterusnya, streak teka-teki harian dan pelajaran yang sudah dapat bintang.">
            </figure>
            <figure class="lp-shot lp-shot-front">
                <div class="lp-shot-bar" aria-hidden="true"><i></i><i></i><i></i><span>Pelajaran: Fork Kuda</span></div>
                <img src="{{ asset('images/landing/pelajaran.jpg') }}" width="1650" height="1080" loading="lazy" decoding="async"
                     alt="Skrin pelajaran Fork Kuda: papan catur dengan anak panah serangan dan penerangan Pak Kuda di sebelah.">
            </figure>
        </div>
    </section>

    {{-- PATH --}}
    <section class="lp-section" id="laluan">
        <header class="lp-head">
            <small>Laluan pembelajaran</small>
            <h2>Satu peta, {{ $levels->count() }} tahap, satu langkah pada satu masa.</h2>
            <p>Setiap pelajaran membuka pelajaran seterusnya. Tiada lompatan yang mengelirukan, cuma kemajuan yang boleh dilihat.</p>
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
                        <small>Tahap {{ $level->number }}</small>
                        <h3>{{ $level->name }}</h3>
                        <p>{{ $level->note }}</p>
                        <ul>
                            @foreach ($regular->take(3) as $lesson)
                                <li>{{ $lesson->title }}</li>
                            @endforeach
                            @if ($regular->count() > 3)
                                <li class="more">+{{ $regular->count() - 3 }} lagi</li>
                            @endif
                        </ul>
                        <span class="lp-step-foot">{{ $level->lessons->count() }} pelajaran · berakhir dengan ujian</span>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- FEATURES --}}
    <section class="lp-section" id="ciri">
        <header class="lp-head">
            <small>Ciri-ciri</small>
            <h2>Semua yang pemain catur kecil perlukan.</h2>
        </header>
        <div class="lp-bento">
            <article class="lp-tile lp-tile-big">
                <div class="lp-tile-text">
                    <span class="lp-ico"><i class="pc pk"></i></span>
                    <h3>Papan interaktif dengan jurulatih sendiri</h3>
                    <p>Murid gerakkan buah sendiri, bukan sekadar membaca. Pak Kuda menerangkan setiap konsep, memberi petunjuk bila tersekat dan membetulkan langkah yang salah dengan lembut.</p>
                </div>
                <div class="lp-chat" aria-hidden="true">
                    <p class="me">Kenapa langkah ini tak boleh?</p>
                    <p class="pk"><span class="lp-av"><i class="pc pk"></i></span>Raja awak akan kena sah. Cuba lindungi dia dulu!</p>
                    <p class="pk ok"><span class="lp-av"><i class="pc pk"></i></span>Bagus! Itu langkah terbaik. +3 bintang</p>
                </div>
            </article>

            <article class="lp-tile lp-tile-sun">
                <span class="lp-ico flame"><x-chessflow.flame /></span>
                <h3>Teka-teki harian</h3>
                <p>Satu teka-teki baharu setiap hari. Selesaikan untuk kekalkan streak dan bina tabiat berlatih.</p>
            </article>

            <article class="lp-tile lp-tile-dark">
                <span class="lp-ico"><i class="pc bK"></i></span>
                <h3>Main lawan Pak Kuda</h3>
                <p>Perlawanan penuh menentang enjin Stockfish dalam tiga tahap.</p>
                <div class="lp-levels" aria-label="Tahap lawan">
                    <span>Mudah</span><span>Sederhana</span><span>Sukar</span>
                </div>
            </article>

            <article class="lp-tile">
                <span class="lp-ico star"><i class="star-ico"></i></span>
                <h3>Bintang dan XP</h3>
                <p>Sehingga tiga bintang setiap pelajaran. XP dikira oleh pelayan, jadi markah sentiasa adil.</p>
            </article>

            <article class="lp-tile">
                <span class="lp-ico"><x-chessflow.medal /></span>
                <h3>Ujian dan sijil</h3>
                <p>Lulus ujian tahap (70% ke atas) untuk dapat sijil yang boleh dicetak, lengkap dengan kod pengesahan.</p>
            </article>

            <article class="lp-tile">
                <span class="lp-ico lang">BM</span>
                <h3>Bahasa Melayu sepenuhnya</h3>
                <p>Istilah, arahan dan penerangan ditulis khas dalam Bahasa Melayu yang mudah difahami kanak-kanak.</p>
            </article>
        </div>
    </section>

    {{-- AUDIENCE --}}
    <section class="lp-section" id="manfaat">
        <header class="lp-head">
            <small>Manfaat</small>
            <h2>Dibina untuk murid, ibu bapa dan guru.</h2>
        </header>
        <div class="lp-aud">
            <article>
                <span class="lp-aud-tag"><i class="pc wP"></i> Murid</span>
                <h3>Belajar sambil bermain</h3>
                <ul>
                    <li>Langkah kecil yang jelas, ikut rentak sendiri</li>
                    <li>Ganjaran segera: bintang, XP dan streak</li>
                    <li>Masuk dengan nama pengguna dan PIN sahaja</li>
                    <li>Sijil untuk setiap tahap yang lulus</li>
                </ul>
            </article>
            <article>
                <span class="lp-aud-tag"><i class="pc wQ"></i> Ibu bapa</span>
                <h3>Tahu kemajuan anak</h3>
                <ul>
                    <li>Cipta akaun anak tanpa e-mel</li>
                    <li>Pautkan akaun anak yang sedia ada</li>
                    <li>Lihat pelajaran, bintang dan XP anak</li>
                    <li>Tetapkan PIN baharu bila anak terlupa</li>
                </ul>
            </article>
            <article>
                <span class="lp-aud-tag"><i class="pc wK"></i> Guru</span>
                <h3>Urus kelas dengan mudah</h3>
                <ul>
                    <li>Cipta kelas dan kongsi kod sertai</li>
                    <li>Cipta akaun ramai murid sekali gus</li>
                    <li>Jadual kemajuan setiap murid, setiap pelajaran</li>
                    <li>Sesuai untuk kelab catur dan kokurikulum</li>
                </ul>
            </article>
        </div>
    </section>

    {{-- HOW --}}
    <section class="lp-section" id="cara">
        <header class="lp-head">
            <small>Cara mula</small>
            <h2>Tiga langkah ke papan catur.</h2>
        </header>
        <ol class="lp-how">
            <li>
                <span class="n">1</span>
                <h3>Daftar akaun dewasa</h3>
                <p>Ibu bapa atau guru daftar dengan e-mel. Akaun guru diaktifkan oleh pentadbir.</p>
            </li>
            <li>
                <span class="n">2</span>
                <h3>Cipta akaun murid</h3>
                <p>Dapatkan nama pengguna dan PIN untuk setiap murid. Tiada e-mel kanak-kanak diperlukan.</p>
            </li>
            <li>
                <span class="n">3</span>
                <h3>Murid mula belajar</h3>
                <p>Murid masuk, buka peta dan mula pelajaran pertama: Papan Catur.</p>
            </li>
        </ol>
    </section>

    {{-- PRIVACY --}}
    <section class="lp-privacy" id="privasi">
        <div class="lp-privacy-in">
            <div>
                <small>Privasi kanak-kanak</small>
                <h2>Data minimum. Tiada e-mel. Tiada iklan.</h2>
                <p>Kami hanya simpan apa yang perlu untuk pembelajaran. Kemajuan murid hanya dilihat oleh murid itu, guru kelasnya dan ibu bapa yang dipautkan.</p>
                <a class="lp-link" href="{{ route('tentang') }}#privasi">Baca dasar privasi →</a>
            </div>
            <ul>
                <li><b>Tanpa e-mel</b><span>Murid masuk dengan nama pengguna dan PIN.</span></li>
                <li><b>Nama pilihan sendiri</b><span>Nama pada sijil ditulis dan diubah oleh murid.</span></li>
                <li><b>Akses terhad</b><span>Hanya guru dan ibu bapa yang dipautkan.</span></li>
            </ul>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="lp-section lp-faq-wrap" id="soalan">
        <header class="lp-head">
            <small>Soalan lazim</small>
            <h2>Ada soalan?</h2>
            <p>Tak jumpa jawapan? E-mel kami di <a href="mailto:{{ $email }}">{{ $email }}</a>.</p>
        </header>
        <div class="lp-faq">
            <details>
                <summary>Siapa yang sesuai guna ChessFlow?</summary>
                <p>Kanak-kanak sekolah rendah dan sesiapa sahaja yang baru belajar catur. Tahap 1 bermula dari kenal papan, jadi tiada pengalaman diperlukan.</p>
            </details>
            <details>
                <summary>Perlu pasang aplikasi?</summary>
                <p>Tidak. ChessFlow berjalan terus dalam pelayar web di komputer atau tablet.</p>
            </details>
            <details>
                <summary>Anak saya lupa PIN. Bagaimana?</summary>
                <p>Guru atau ibu bapa yang dipautkan boleh tetapkan PIN baharu dari halaman Anak atau Kelas.</p>
            </details>
            <details>
                <summary>Bagaimana mendapatkan akaun guru?</summary>
                <p>Daftar akaun biasa, kemudian e-mel <a href="mailto:{{ $email }}?subject=Permohonan%20akaun%20guru%20ChessFlow">{{ $email }}</a> dengan nama sekolah atau kelab anda. Pentadbir akan aktifkan peranan guru.</p>
            </details>
            <details>
                <summary>Bagaimana menyemak sijil?</summary>
                <p>Setiap sijil ada kod pengesahan 10 aksara. Masukkan kod itu di bahagian <a href="#semak-sijil">Semak sijil</a> di bawah.</p>
            </details>
        </div>
    </section>

    {{-- CTA --}}
    <section class="lp-cta">
        <div class="lp-cta-in">
            <span class="lp-cta-piece" aria-hidden="true"><i class="pc pk"></i></span>
            <h2>Papan sudah tersedia. Giliran awak.</h2>
            <p>Murid masuk dengan nama pengguna dan PIN. Ibu bapa dan guru boleh daftar dalam seminit.</p>
            <div class="lp-actions">
                <a class="lp-btn lp-btn-sun" href="{{ route('murid.login') }}">Masuk murid</a>
                @if (Route::has('register'))
                    <a class="lp-btn lp-btn-line" href="{{ route('register') }}">Daftar ibu bapa / guru</a>
                @endif
            </div>
        </div>
    </section>

    {{-- LINKS --}}
    <section class="lp-links" aria-label="Pautan">
        <div>
            <h3>Mula</h3>
            <ul>
                <li><a href="{{ route('murid.login') }}">Masuk murid</a></li>
                <li><a href="{{ route('login') }}">Log masuk guru / ibu bapa</a></li>
                @if (Route::has('register'))
                    <li><a href="{{ route('register') }}">Daftar akaun</a></li>
                @endif
            </ul>
        </div>
        <div>
            <h3>Maklumat</h3>
            <ul>
                <li><a href="#laluan">Laluan pembelajaran</a></li>
                <li><a href="#ciri">Ciri-ciri</a></li>
                <li><a href="{{ route('tentang') }}">Tentang ChessFlow</a></li>
                <li><a href="{{ route('tentang') }}#privasi">Privasi</a></li>
                <li><a href="{{ route('tentang') }}#lesen">Perisian dan lesen</a></li>
            </ul>
        </div>
        <div>
            <h3>Sokongan</h3>
            <ul>
                <li><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                <li><a href="mailto:{{ $email }}?subject=Permohonan%20akaun%20guru%20ChessFlow">Mohon akaun guru</a></li>
                <li><a href="mailto:{{ $email }}?subject=Laporan%20masalah%20ChessFlow">Lapor masalah</a></li>
            </ul>
        </div>
        <div id="semak-sijil">
            <h3>Semak sijil</h3>
            <form wire:submit="checkCertificate" class="lp-cert">
                <label class="sr-only" for="cert-code">Kod sijil</label>
                <input id="cert-code" type="text" wire:model="certCode" maxlength="20" placeholder="Kod sijil" autocapitalize="characters" spellcheck="false" required>
                <button type="submit">Semak</button>
            </form>
            @error('certCode') <p class="lp-err">{{ $message }}</p> @enderror
        </div>
        <p class="lp-legal">© {{ now()->year }} ChessFlow · Belajar catur dalam Bahasa Melayu untuk kanak-kanak dan pemula</p>
    </section>
</div>
