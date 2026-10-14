{{-- Help docs, Bahasa Melayu. Keep in step with help/en.blade.php and with what the app really does. --}}
<h1>Bantuan</h1>
<p class="help-lede">Panduan menggunakan ChessFlow untuk murid, ibu bapa dan guru. Tak jumpa jawapan? E-mel <a href="mailto:{{ $email }}?subject=Bantuan%20ChessFlow">{{ $email }}</a>.</p>

<nav class="help-toc" aria-label="Kandungan">
    <b>Kandungan</b>
    <ol>
        <li><a href="#mula">Bermula</a></li>
        <li><a href="#murid">Untuk murid</a></li>
        <li><a href="#main">Main lawan Pak Kuda</a></li>
        <li><a href="#ibu-bapa">Untuk ibu bapa</a></li>
        <li><a href="#guru">Untuk guru</a></li>
        <li><a href="#tetapan">Tetapan</a></li>
        <li><a href="#privasi">Privasi dan data</a></li>
        <li><a href="#masalah">Masalah lazim</a></li>
        <li><a href="#pentadbir">Untuk pentadbir</a></li>
    </ol>
</nav>

<h2 id="mula">1. Bermula</h2>
<ul>
    <li><b>Murid</b> tidak perlu e-mel. Guru atau ibu bapa mencipta akaun dan memberi <b>nama pengguna</b> dan <b>PIN</b>. Masuk di <a href="{{ route('murid.login') }}">Masuk murid</a>.</li>
    <li><b>Ibu bapa dan guru</b> <a href="{{ route('register') }}">mendaftar</a> dengan e-mel, kemudian <a href="{{ route('login') }}">log masuk</a>. Akaun baharu ialah akaun ibu bapa; akaun guru diaktifkan oleh pentadbir (lihat <a href="#guru">Untuk guru</a>).</li>
    <li>ChessFlow berjalan terus dalam pelayar web, di komputer atau tablet. Tiada aplikasi perlu dipasang.</li>
</ul>

<h2 id="murid">2. Untuk murid</h2>
<h3>Peta</h3>
<p>Selepas masuk, awak nampak <b>peta</b> dengan 5 tahap. Setiap pelajaran membuka pelajaran seterusnya. Tekan butang <b>Sambung</b> di atas untuk terus ke pelajaran awak. Tahap yang sudah siap atau belum dibuka boleh dilipat: tekan tajuk tahap untuk buka atau tutup. Peta ingat pilihan awak.</p>
<h3>Pelajaran dan papan</h3>
<ul>
    <li>Pak Kuda menerangkan setiap langkah. Baca <b>Tugasan</b>, kemudian jawab di papan atau pilih jawapan.</li>
    <li>Gerakkan buah dengan <b>ketik</b> (ketik buah, kemudian ketik petak) atau <b>seret</b> buah ke petak baharu.</li>
    <li>Bila bidak sampai ke baris terakhir, pilih buah promosi: Menteri, Tir, Gajah atau Kuda.</li>
    <li>Petak kuning menunjukkan langkah terakhir; raja yang kena sah menyala merah; gelang di sekeliling buah bermaksud buah itu boleh dimakan.</li>
    <li>Tersekat? Tekan <b>Petunjuk</b>. Petunjuk dikira sebagai satu kesilapan.</li>
</ul>
<h3>Bintang dan XP</h3>
<ul>
    <li>Setiap pelajaran memberi sehingga <b>3 bintang</b>: 3 bintang jika paling banyak 1 kesilapan, 2 bintang jika paling banyak 4 kesilapan, selain itu 1 bintang.</li>
    <li><b>XP</b> dikira oleh pelayan: XP penuh kali pertama sesuatu pelajaran siap, dan sebahagian kecil jika diulang. Teka-teki harian memberi 15 XP, jawapan betul dalam Latih semula 5 XP, dan menang lawan Pak Kuda 20, 50 atau 100 XP mengikut tahap.</li>
    <li>Jika keputusan dihantar terlalu cepat, awak akan lihat "Perlahan sikit!" dan keputusan itu tidak direkodkan. Cuba lagi dengan tenang.</li>
</ul>
<h3>Teka-teki harian dan streak</h3>
<p>Satu teka-teki baharu setiap hari (waktu Malaysia). Selesaikan setiap hari untuk menambah <b>streak</b>; jika terlepas sehari, streak bermula semula.</p>
<h3>Latih semula</h3>
<p>Soalan yang awak tersilap disimpan dan ditanya semula: esok, kemudian 3, 7 dan 14 hari selepas jawapan betul. Selepas itu soalan itu "dah mahir" dan tidak ditanya lagi. Lihat kad <b>Latih semula</b> di peta.</p>
<h3>Lencana</h3>
<p>Ada 14 lencana untuk pelajaran, bintang, ujian, streak, teka-teki dan permainan. Lihat semuanya, termasuk berapa lagi yang perlu, di halaman <b>Lencana</b> (kad di peta).</p>
<h3>Ujian dan sijil</h3>
<p>Setiap tahap berakhir dengan ujian. Jawab sekurang-kurangnya <b>70%</b> dengan betul untuk lulus dan dapat <b>sijil</b>. Di halaman sijil, tulis nama awak sendiri, kemudian tekan <b>Cetak / Simpan PDF</b> (dalam dialog cetak, pilih "Simpan sebagai PDF" untuk fail PDF). Cari semula sijil awak di bahagian <b>Sijil saya</b> di halaman Lencana, atau pada nod ujian di peta. Setiap sijil ada kod pengesahan; sesiapa boleh menyemaknya di laman utama (Semak sijil).</p>
<h3>Tugasan guru dan kelas</h3>
<p>Jika guru memberi tugasan, ia muncul di atas peta bersama tarikh akhir. Pelajaran yang ditugaskan terus dibuka untuk awak. Ada kod kelas daripada guru? Tekan <b>Sertai kelas</b> di peta.</p>
<h3>Istilah dalam bahasa Inggeris</h3>
<p>Nama buah dan istilah catur dalam bahasa Inggeris ada di halaman <a href="{{ route('istilah') }}">Istilah catur</a>.</p>

<h2 id="main">3. Main lawan Pak Kuda</h2>
<ul>
    <li>Tekan <b>Main</b> di atas. Pilih warna (Putih, Hitam atau Rawak) dan tahap (Mudah, Sederhana atau Sukar), kemudian <b>Permainan baru</b>.</li>
    <li><b>Papan analisis</b> (butang di atas halaman Main, atau <b>Buka di papan analisis</b> selepas permainan): gerakkan buah untuk kedua-dua pihak dan Pak Kuda nilai kedudukan, tunjuk 3 barisan terbaik dan bertulis siapa lebih baik. Boleh tampal FEN atau PGN, dan <b>Salin pautan</b> untuk kongsi kedudukan berserta langkah.</li>
    <li>Nak papan lebih besar? Tekan <b>Papan besar</b> (komputer) untuk papan ikut tinggi skrin, atau <b>Skrin penuh</b> untuk sorok semua yang lain. Tekan <b>Esc</b> untuk keluar. Sesuai untuk projektor di kelas.</li>
    <li><b>Undur</b> membatalkan langkah terakhir awak. <b>Petunjuk</b> menunjukkan langkah yang baik dengan anak panah.</li>
    <li><b>Minta seri</b>: Pak Kuda hanya setuju selepas sekurang-kurangnya 20 langkah setiap pemain, dan jika dia tidak ada lebih buah. Jika dia tolak, tunggu 10 langkah sebelum minta lagi.</li>
    <li><b>Mengaku kalah</b> meminta pengesahan dahulu, supaya tidak tertekan secara tidak sengaja.</li>
    <li>Selepas permainan tamat, tekan <b>Semak permainan dengan Pak Kuda</b>. Pak Kuda tunjuk sehingga 3 langkah penting: anak panah merah ialah langkah awak, hijau ialah cadangannya.</li>
    <li>Huruf dalam senarai langkah: K Raja, Q Menteri, R Tir, B Gajah, N Kuda, x makan, + sah, # sah mati.</li>
</ul>

<h2 id="ibu-bapa">4. Untuk ibu bapa</h2>
<ul>
    <li>Selepas log masuk, tekan <b>Anak</b> di atas.</li>
    <li><b>Cipta akaun anak</b>: tulis nama panggilan. Kami jana nama pengguna dan PIN. Simpan atau cetak segera, kerana PIN tidak dipaparkan lagi.</li>
    <li>Anak sudah ada akaun (contohnya dicipta oleh guru)? <b>Pautkan</b> dengan nama pengguna dan PIN anak.</li>
    <li>Jadual kemajuan menunjukkan pelajaran, bintang, XP dan streak setiap anak. Tanda ✓ pada ujian yang lulus membuka sijil anak.</li>
    <li>Lupa PIN? Tekan <b>PIN baru</b>. <b>Buang pautan</b> hanya memutuskan pautan; akaun anak kekal.</li>
    <li><b>Muat turun data</b> memberi salinan data anak (JSON). <b>Padam akaun</b> memadam akaun anak dan semua datanya selama-lamanya (taip PADAM untuk sahkan).</li>
</ul>

<h2 id="guru">5. Untuk guru</h2>
<ul>
    <li><b>Akaun guru</b>: daftar akaun biasa, kemudian e-mel <a href="mailto:{{ $email }}?subject=Permohonan%20akaun%20guru%20ChessFlow">{{ $email }}</a> dengan nama sekolah atau kelab. Pentadbir akan aktifkan peranan guru.</li>
    <li>Tekan <b>Kelas</b> di atas, kemudian <b>Cipta kelas</b>. Setiap kelas ada <b>kod sertai</b>.</li>
    <li><b>Tambah murid</b>: tulis satu nama setiap baris (sehingga 40 sekali gus). Nama pengguna dan PIN dipaparkan sekali sahaja; cetak untuk diedarkan. Murid yang sudah ada akaun boleh sertai dengan kod kelas.</li>
    <li><b>Tugasan</b>: pilih pelajaran atau ujian, tarikh akhir dan nota (pilihan), kemudian <b>Beri tugasan</b>. Pelajaran itu terus dibuka untuk semua murid kelas. Lihat berapa murid sudah siap dan siapa yang belum.</li>
    <li>Jadual kemajuan menunjukkan bintang setiap murid untuk setiap pelajaran; tanda ✓ pada ujian yang lulus membuka sijil murid itu. Untuk setiap murid: <b>PIN baru</b>, <b>Keluarkan</b> daripada kelas, atau <b>Padam akaun</b> (hanya jika murid itu tidak dipautkan dengan ibu bapa atau kelas guru lain).</li>
</ul>

<h2 id="tetapan">6. Tetapan</h2>
<ul>
    <li>Butang di atas kanan: <b>tema warna</b> (Sistem, Cerah, Gelap), <b>bunyi</b> (hidup atau tutup) dan <b>bahasa</b> (BM atau EN). Tetapan ini disimpan pada peranti.</li>
    <li>Ibu bapa dan guru: ikon gear membuka <b>Tetapan akaun</b>. Tab <b>Profil</b> (nama, e-mel, jenis akaun dan permohonan akaun guru), <b>Keselamatan</b> (kata laluan, pengesahan dua faktor, passkey), <b>Paparan &amp; bahasa</b> (tema, bahasa, bunyi) dan <b>Data &amp; privasi</b> (muat turun atau padam akaun).</li>
</ul>

<h2 id="privasi">7. Privasi dan data</h2>
<p>Kami simpan data sesedikit yang boleh dan tidak meminta e-mel murid. Baca <a href="{{ route('privasi') }}">Dasar Privasi</a> (Akta Perlindungan Data Peribadi 2010) dan <a href="{{ route('terma') }}">Terma Penggunaan</a>. Muat turun data anda sendiri di Tetapan akaun → Data &amp; privasi, atau data anak di halaman Anak.</p>

<h2 id="masalah">8. Masalah lazim</h2>
<details><summary>Saya lupa PIN.</summary><p>Minta guru atau ibu bapa yang mencipta akaun awak tekan <b>PIN baru</b>.</p></details>
<details><summary>"Terlalu banyak cubaan" semasa masuk.</summary><p>Tunggu kira-kira seminit, kemudian cuba lagi dengan nama pengguna dan PIN yang betul.</p></details>
<details><summary>Pelajaran berkunci.</summary><p>Siapkan pelajaran sebelumnya di peta. Guru juga boleh membuka pelajaran untuk kelas dengan memberi tugasan.</p></details>
<details><summary>Papan atau Pak Kuda tidak bergerak.</summary><p>Muat semula halaman. Jika mesej "enjin tidak dapat dimuatkan" muncul, Pak Kuda masih boleh bermain (tahap Mudah) tetapi petunjuk dan analisis memerlukan enjin; cuba pelayar lain atau kemas kini pelayar.</p></details>
<details><summary>Tiada bunyi.</summary><p>Pastikan butang bunyi di atas tidak bertanda pangkah, dan bunyi peranti tidak dimatikan.</p></details>
<details><summary>Saya mahu bahasa lain.</summary><p>Tekan <b>EN</b> atau <b>BM</b> di atas. Pelajaran ditulis dalam Bahasa Melayu; nama buah dalam bahasa Inggeris ada dalam pelajaran dan di halaman Istilah catur.</p></details>

<h2 id="pentadbir">9. Untuk pentadbir</h2>
<ul>
    <li>Panel pentadbir di <b>/admin</b> (pautan <b>Admin</b> di atas): tukar peranan pengguna (ibu bapa, guru, pentadbir), <b>Eksport data</b> dan padam akaun, serta terbitkan atau susun pelajaran.</li>
    <li>Kandungan pelajaran datang daripada <code>data/lessons.json</code> dan diimport dengan <code>php artisan chessflow:import-lessons</code>. Jangan sunting pelajaran terus dalam pangkalan data.</li>
    <li>Arahan berguna: <code>chessflow:set-role &lt;emel&gt; guru|admin</code>, <code>chessflow:create-student</code>, <code>chessflow:pick-daily</code>, <code>chessflow:doctor</code>.</li>
</ul>

<h2 id="hubungi">Hubungi kami</h2>
<p>E-mel <a href="mailto:{{ $email }}?subject=Bantuan%20ChessFlow">{{ $email }}</a>. ChessFlow dikendalikan oleh WM AFENDI ENTERPRISE, Ketereh, Kelantan.</p>
