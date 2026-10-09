<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Tentang')] class extends Component {}; ?>

<div class="prose-page">
    <h1>Tentang ChessFlow</h1>
    <p>ChessFlow ialah laman pembelajaran catur interaktif dalam Bahasa Melayu untuk kanak-kanak dan pemula. Semua kandungan pelajaran ditulis khas untuk ChessFlow.</p>

    <h2 id="privasi">Privasi</h2>
    <ul>
        <li>Akaun murid tidak memerlukan e-mel. Murid masuk dengan nama pengguna dan PIN yang dicipta oleh guru atau ibu bapa.</li>
        <li>Kami simpan nama, kemajuan pelajaran, XP, streak, keputusan ujian dan permainan. Data ini hanya untuk pembelajaran dan dilihat oleh murid, guru kelasnya dan ibu bapa yang dipautkan.</li>
        <li>Nama pada sijil ditulis sendiri oleh murid dan boleh diubah bila-bila masa.</li>
        <li>Untuk memadam akaun murid, hubungi guru atau ibu bapa yang mencipta akaun itu, atau pentadbir ChessFlow.</li>
    </ul>

    <h2 id="lesen">Perisian dan lesen</h2>
    <ul>
        <li><b>Stockfish</b> (enjin catur untuk permainan dan petunjuk) — lesen GNU GPL v3. Kami guna <a href="https://github.com/nmrugg/stockfish.js">Stockfish.js 10</a>, versi WebAssembly; kod sumber Stockfish di <a href="https://github.com/official-stockfish/Stockfish">github.com/official-stockfish/Stockfish</a>. Fail enjin dihantar tanpa diubah di <a href="/stockfish/stockfish.js">/stockfish/</a>.</li>
        <li><b>chess.js</b> (peraturan catur) — lesen BSD-2-Clause, oleh Jeff Hlywa. <a href="https://github.com/jhlywa/chess.js">github.com/jhlywa/chess.js</a></li>
        <li><b>Buah catur di papan</b> — set "cburnett" oleh Colin M.L. Burnett, versi asal dari <a href="https://commons.wikimedia.org/wiki/File:Chess_klt45.svg">Wikimedia Commons</a>, digunakan di bawah lesen BSD 3-klausa (<a href="/images/pieces/LICENSE.txt">teks lesen</a>).</li>
        <li><b>Pak Kuda</b> (logo ChessFlow) — kuda putih daripada set "rhosgfx" oleh RhosGFX, didedikasikan ke domain awam (CC0 1.0). Diambil dari <a href="https://github.com/lichess-org/lila/tree/master/public/piece/rhosgfx">repo Lichess</a>.</li>
        <li><b>Fon</b> Baloo 2 dan Lexend — lesen SIL Open Font License 1.1, dihantar melalui Bunny Fonts.</li>
        <li>Dibina dengan Laravel, Livewire dan Filament (lesen MIT).</li>
    </ul>
</div>
