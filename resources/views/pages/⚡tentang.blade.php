<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Tentang')] class extends Component {}; ?>

<div class="prose-page">
    <h1>{{ __('Tentang ChessFlow') }}</h1>
    <p>{{ __('ChessFlow ialah laman pembelajaran catur interaktif dalam Bahasa Melayu untuk kanak-kanak dan pemula. Semua kandungan pelajaran ditulis khas untuk ChessFlow.') }}</p>

    <h2 id="privasi">{{ __('Privasi') }}</h2>
    <p>{!! __('Ringkasan di bawah. Notis penuh mengikut Akta Perlindungan Data Peribadi 2010: :privacy. Lihat juga :terms.', ['privacy' => '<a href="'.route('privasi').'">'.e(__('Dasar Privasi')).'</a>', 'terms' => '<a href="'.route('terma').'">'.e(__('Terma Penggunaan')).'</a>']) !!}</p>
    <ul>
        <li>{{ __('Akaun murid tidak memerlukan e-mel. Murid masuk dengan nama pengguna dan PIN yang dicipta oleh guru atau ibu bapa.') }}</li>
        <li>{{ __('Kami simpan nama, kemajuan pelajaran, XP, streak, keputusan ujian dan permainan. Data ini hanya untuk pembelajaran dan dilihat oleh murid, guru kelasnya dan ibu bapa yang dipautkan.') }}</li>
        <li>{{ __('Nama pada sijil ditulis sendiri oleh murid dan boleh diubah bila-bila masa.') }}</li>
        <li>{{ __('Untuk memadam akaun murid, hubungi guru atau ibu bapa yang mencipta akaun itu, atau pentadbir ChessFlow.') }}</li>
    </ul>

    <h2 id="lesen">{{ __('Perisian dan lesen') }}</h2>
    <ul>
        <li>{!! __('<b>Stockfish</b> (enjin catur untuk permainan dan petunjuk) — lesen GNU GPL v3. Kami guna :sfjs, versi WebAssembly; kod sumber Stockfish di :src. Fail enjin dihantar tanpa diubah di :files.', ['sfjs' => '<a href="https://github.com/nmrugg/stockfish.js">Stockfish.js 10</a>', 'src' => '<a href="https://github.com/official-stockfish/Stockfish">github.com/official-stockfish/Stockfish</a>', 'files' => '<a href="/stockfish/stockfish.js">/stockfish/</a>']) !!}</li>
        <li>{!! __('<b>chess.js</b> (peraturan catur) — lesen BSD-2-Clause, oleh Jeff Hlywa.') !!} <a href="https://github.com/jhlywa/chess.js">github.com/jhlywa/chess.js</a></li>
        <li>{!! __('<b>Buah catur di papan</b> — set "cburnett" oleh Colin M.L. Burnett, versi asal dari :wiki, digunakan di bawah lesen BSD 3-klausa (:text).', ['wiki' => '<a href="https://commons.wikimedia.org/wiki/File:Chess_klt45.svg">Wikimedia Commons</a>', 'text' => '<a href="/images/pieces/LICENSE.txt">'.e(__('teks lesen')).'</a>']) !!}</li>
        <li>{!! __('<b>Pak Kuda</b> (logo ChessFlow) — kuda putih daripada set "rhosgfx" oleh RhosGFX, didedikasikan ke domain awam (CC0 1.0). Diambil dari :repo.', ['repo' => '<a href="https://github.com/lichess-org/lila/tree/master/public/piece/rhosgfx">'.e(__('repo Lichess')).'</a>']) !!}</li>
        <li>{!! __('<b>Fon</b> Baloo 2 dan Lexend — lesen SIL Open Font License 1.1, disimpan dan dihantar dari pelayan ChessFlow sendiri.') !!}</li>
        <li>{{ __('Dibina dengan Laravel, Livewire dan Filament (lesen MIT).') }}</li>
    </ul>
</div>
