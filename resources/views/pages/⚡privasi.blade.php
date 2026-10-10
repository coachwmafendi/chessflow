<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Dasar Privasi')] class extends Component {
    public const EMAIL = 'wmafendi@gmail.com';

    public const EFFECTIVE = '10 Oktober 2026';
}; ?>

{{-- Notice under the Personal Data Protection Act 2010 (Act 709). Section 7(3) asks for the notice in both
     Bahasa Melayu and English, hence the English version below. Keep both in sync with what the app stores. --}}
<div class="prose-page legal">
    <h1>Dasar Privasi</h1>
    <p class="legal-meta">Berkuat kuasa: {{ self::EFFECTIVE }} · <a href="#english">English version</a></p>

    <p>ChessFlow ialah laman pembelajaran catur untuk kanak-kanak dan pemula. Notis ini menerangkan data peribadi yang kami kumpul, sebabnya, dan hak anda di bawah <b>Akta Perlindungan Data Peribadi 2010 (Akta 709)</b>. Kebanyakan pengguna kami kanak-kanak, jadi kami simpan data sesedikit yang boleh.</p>

    <h2 id="siapa">1. Siapa kami</h2>
    <p>Pengguna data (data user) bagi ChessFlow ialah:</p>
    <address class="legal-address"><b>WM AFENDI ENTERPRISE</b><br>Lot 13347, Kampung Chengal<br>16450 Ketereh, Kelantan, Malaysia</address>
    <p>Untuk sebarang soalan, permintaan atau aduan tentang data peribadi, hubungi <a href="mailto:{{ self::EMAIL }}?subject=Privasi%20ChessFlow">{{ self::EMAIL }}</a>.</p>

    <h2 id="data">2. Data yang kami kumpul</h2>
    <h3>Akaun murid (tanpa e-mel)</h3>
    <ul>
        <li><b>Nama</b>, <b>nama pengguna</b> dan <b>PIN</b>. PIN disimpan dalam bentuk yang disulitkan (hash); tiada sesiapa, termasuk kami, boleh membacanya.</li>
        <li><b>Kemajuan pembelajaran</b>: pelajaran yang siap, bintang, bilangan kesilapan, keputusan ujian, sijil, teka-teki harian dan streak, XP, lencana, soalan "Latih semula" dan tugasan kelas.</li>
        <li><b>Permainan lawan Pak Kuda</b>: warna, tahap, keputusan dan senarai langkah.</li>
        <li><b>Nama pada sijil</b>, yang ditulis dan boleh diubah oleh murid sendiri.</li>
    </ul>
    <h3>Akaun ibu bapa dan guru</h3>
    <ul>
        <li><b>Nama</b>, <b>alamat e-mel</b> dan <b>kata laluan</b> (disimpan dalam bentuk hash), serta tetapan keselamatan pilihan (pengesahan dua faktor, passkey).</li>
        <li>Untuk guru: kelas, kod sertai dan tugasan yang dicipta. Untuk ibu bapa: pautan kepada akaun anak.</li>
    </ul>
    <h3>Data teknikal</h3>
    <ul>
        <li><b>Kuki sesi</b> yang perlu untuk log masuk dan keselamatan borang. Kami tidak guna kuki pengiklanan atau penjejakan.</li>
        <li>Tetapan seperti tema warna, bunyi dan pilihan permainan disimpan <b>dalam pelayar pada peranti anda sahaja</b>, tidak dihantar kepada kami.</li>
        <li>Alamat IP digunakan seketika untuk menghadkan cubaan log masuk yang berlebihan.</li>
    </ul>
    <p><b>Tiada iklan untuk tempoh terhad:</b> buat masa ini ChessFlow tidak memaparkan iklan. Jika iklan diperkenalkan kemudian, kami akan mengemas kini notis ini dan memaklumkan pemilik akaun dewasa terlebih dahulu. Kami tidak menjual data, dan tidak menggunakan alat analitik pihak ketiga. Enjin catur Stockfish berjalan dalam pelayar anda.</p>

    <h2 id="tujuan">3. Tujuan</h2>
    <p>Data digunakan hanya untuk: menyediakan pelajaran dan menyimpan kemajuan; mengira XP, bintang, lencana dan sijil; membolehkan guru dan ibu bapa memantau kemajuan murid mereka; keselamatan akaun; dan menghubungi pemilik akaun dewasa tentang akaun mereka (contohnya tetapan semula kata laluan).</p>

    <h2 id="kanak-kanak">4. Kanak-kanak dan kebenaran ibu bapa atau guru</h2>
    <p>Akaun murid dicipta oleh ibu bapa atau guru, atau murid menyertai kelas dengan kod daripada guru. Dengan mencipta akaun murid, ibu bapa atau guru itu mengesahkan bahawa mereka berhak memberi kebenaran bagi pihak kanak-kanak tersebut untuk pemprosesan data seperti dalam notis ini. Kami tidak meminta e-mel, nombor telefon, alamat atau gambar kanak-kanak.</p>

    <h2 id="siapa-nampak">5. Siapa boleh melihat data</h2>
    <ul>
        <li><b>Murid</b> melihat kemajuan sendiri.</li>
        <li><b>Guru</b> melihat kemajuan murid dalam kelas mereka. <b>Ibu bapa</b> melihat kemajuan anak yang dipautkan.</li>
        <li><b>Pentadbir ChessFlow</b> untuk sokongan dan penyelenggaraan.</li>
        <li><b>Sijil</b> boleh dilihat oleh sesiapa yang mempunyai kod pengesahannya (untuk menyemak kesahihan). Sijil hanya memaparkan nama yang ditulis oleh murid, tahap dan markah.</li>
        <li><b>Pembekal perkhidmatan</b> yang memproses data bagi pihak kami: penyedia pelayan dan Cloudflare (rangkaian dan keselamatan). Mereka tidak dibenarkan menggunakan data untuk tujuan sendiri.</li>
    </ul>
    <p>Kami tidak mendedahkan data kepada pihak lain kecuali jika dikehendaki oleh undang-undang.</p>

    <h2 id="simpan">6. Tempoh penyimpanan</h2>
    <p>Data disimpan selagi akaun aktif. Apabila akaun dipadam, data dibuang daripada pangkalan data utama; salinan sandaran dipadam secara automatik dalam tempoh 14 hari.</p>

    <h2 id="keselamatan">7. Keselamatan</h2>
    <p>Sambungan disulitkan (HTTPS), kata laluan dan PIN disimpan dalam bentuk hash, cubaan log masuk dihadkan, dan akses pentadbir terhad. Jika berlaku pelanggaran data yang menjejaskan anda, kami akan memaklumkan pihak berkuasa dan pengguna yang terlibat seperti yang dikehendaki undang-undang.</p>

    <h2 id="hak">8. Hak anda</h2>
    <p>Di bawah Akta 709 (termasuk pindaannya), anda berhak untuk:</p>
    <ul>
        <li><b>mendapatkan salinan</b> data peribadi anda atau anak anda: muat turun sendiri dalam format JSON (ibu bapa di halaman <i>Anak</i>, pemilik akaun dewasa di tetapan profil), atau minta kami;</li>
        <li><b>membetulkan</b> data yang tidak tepat (murid boleh mengubah nama pada sijil sendiri; untuk data lain, hubungi kami);</li>
        <li><b>menarik balik kebenaran</b> dan <b>memadam</b> akaun: ibu bapa boleh memadam akaun anak di halaman <i>Anak</i>; guru boleh memadam akaun murid yang hanya diurus oleh mereka di halaman kelas; pemilik akaun dewasa boleh memadam akaun sendiri di tetapan akaun; atau minta kami;</li>
        <li>menerima data dalam format yang boleh dibaca mesin (fail JSON di atas).</li>
    </ul>
    <p>Hantar permintaan ke <a href="mailto:{{ self::EMAIL }}?subject=Permintaan%20data%20ChessFlow">{{ self::EMAIL }}</a>. Untuk akaun murid, permintaan perlu datang daripada ibu bapa atau guru yang mencipta akaun itu. Kami akan membalas dalam tempoh 21 hari. Jika anda tidak berpuas hati, anda boleh membuat aduan kepada Jabatan Perlindungan Data Peribadi Malaysia.</p>

    <h2 id="perubahan">9. Perubahan pada notis ini</h2>
    <p>Jika notis ini berubah dengan ketara, kami akan memaparkan tarikh baharu di atas dan memaklumkan pemilik akaun dewasa.</p>

    <hr>

    <section id="english" lang="en">
        <h2>Privacy Notice (English)</h2>
        <p class="legal-meta">Effective: 10 October 2026. This is the English version of the notice above, provided under section 7(3) of the Personal Data Protection Act 2010 (Act 709).</p>
        <p><b>Who we are.</b> The data user for ChessFlow, a chess-learning site for children and beginners, is WM AFENDI ENTERPRISE, Lot 13347, Kampung Chengal, 16450 Ketereh, Kelantan, Malaysia. Contact: <a href="mailto:{{ self::EMAIL }}?subject=ChessFlow%20privacy">{{ self::EMAIL }}</a>.</p>
        <p><b>What we collect.</b> Student accounts have no email: name, username and a PIN (stored hashed); learning progress (lessons, stars, mistakes, exam results, certificates, daily puzzles and streaks, XP, badges, review items, class assignments); games against Pak Kuda (colour, level, result, moves); and the name the student writes on certificates. Parent and teacher accounts: name, email, password (hashed) and optional security settings; teachers' classes, join codes and assignments; parents' links to their children. Technical: session cookies needed to sign in and protect forms (no advertising or tracking cookies); theme, sound and game preferences stay in your browser on your device; IP addresses are used briefly to limit repeated sign-in attempts. No ads for a limited time: ChessFlow currently shows no advertising; if ads are introduced later we will update this notice and tell adult account holders first. We sell no data and use no third-party analytics. The Stockfish chess engine runs in your browser.</p>
        <p><b>Why.</b> To provide lessons and keep progress; to work out XP, stars, badges and certificates; to let teachers and parents follow their students' progress; for account security; and to contact adult account holders about their account.</p>
        <p><b>Children.</b> Student accounts are created by a parent or teacher, or join a class with a teacher's code. By creating a student account, the parent or teacher confirms they may consent on the child's behalf to the processing described here. We never ask for a child's email, phone number, address or photo.</p>
        <p><b>Who can see the data.</b> The student; teachers for students in their classes; parents for linked children; ChessFlow administrators for support. Certificates can be viewed by anyone with their verification code and show only the name the student wrote, the level and the score. Service providers processing data on our behalf: our server host and Cloudflare (network and security). We disclose data to others only when the law requires it.</p>
        <p><b>Retention.</b> Data is kept while the account is active. When an account is deleted, its data is removed from the main database; backup copies are deleted automatically within 14 days.</p>
        <p><b>Security.</b> Encrypted connections (HTTPS), hashed passwords and PINs, limited sign-in attempts and restricted administrator access. If a data breach affects you, we will notify the authorities and affected users as the law requires.</p>
        <p><b>Your rights.</b> Under Act 709 (as amended) you may get a copy of your or your child's personal data (download it yourself as JSON: parents on the Anak page, adults in profile settings; or ask us), correct inaccurate data (students can change the name on their certificates themselves; for anything else, contact us), withdraw consent and delete the account (parents can delete a child's account on the Anak page; teachers can delete accounts of students only they look after on the class page; adults can delete their own account in account settings; or ask us), and receive your data in a machine-readable format (that JSON file). Email <a href="mailto:{{ self::EMAIL }}?subject=ChessFlow%20data%20request">{{ self::EMAIL }}</a>; for student accounts the request should come from the parent or teacher who created the account. We reply within 21 days. You may also complain to the Personal Data Protection Department of Malaysia.</p>
        <p><b>Changes.</b> If this notice changes materially, we will show the new date above and inform adult account holders.</p>
    </section>

    <p class="legal-meta"><a href="{{ route('terma') }}">Terma Penggunaan</a> · <a href="{{ route('tentang') }}">Tentang ChessFlow</a></p>
</div>
