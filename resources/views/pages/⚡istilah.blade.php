<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Istilah catur BM–Inggeris')] class extends Component {
    /**
     * Chess terms used in the lessons, with the English word players meet in books, online and in
     * tournaments, and the notation symbol where there is one. Malay words follow the lesson titles.
     *
     * @return array<string, list<array{0: string, 1: string, 2?: string, 3?: string}>> [Malay, English, notation, piece icon]
     */
    public function groups(): array
    {
        return [
            'Buah catur' => [
                ['Raja', 'King', 'K', 'wK'],
                ['Menteri', 'Queen', 'Q', 'wQ'],
                ['Tir', 'Rook', 'R', 'wR'],
                ['Gajah', 'Bishop', 'B', 'wB'],
                ['Kuda', 'Knight', 'N', 'wN'],
                ['Bidak', 'Pawn', 'tiada huruf', 'wP'],
            ],
            'Papan dan langkah' => [
                ['Papan catur', 'Chessboard'],
                ['Petak', 'Square', 'e4'],
                ['Lajur (a–h)', 'File'],
                ['Baris (1–8)', 'Rank'],
                ['Serong', 'Diagonal'],
                ['Langkah', 'Move'],
                ['Makan', 'Capture', 'x'],
                ['Giliran', 'Turn'],
                ['Notasi', 'Notation'],
                ['Nilai buah (mata)', 'Piece value (points)'],
            ],
            'Peraturan' => [
                ['Sah', 'Check', '+'],
                ['Sah mati', 'Checkmate', '#'],
                ['Seri', 'Draw', '½–½'],
                ['Stalemate', 'Stalemate'],
                ['Promosi', 'Promotion', '=Q'],
                ['Castling', 'Castling', 'O-O, O-O-O'],
                ['En passant', 'En passant', 'e.p.'],
                ['Minta seri', 'Offer a draw'],
                ['Mengaku kalah', 'Resign'],
            ],
            'Taktik dan strategi' => [
                ['Fork', 'Fork'],
                ['Pin', 'Pin'],
                ['Skewer', 'Skewer'],
                ['Serangan terbuka', 'Discovered attack'],
                ['CCT: sah, makan, ancaman', 'CCT: checks, captures, threats'],
                ['Gambit', 'Gambit'],
                ['Pembukaan', 'Opening'],
                ['Permainan tengah', 'Middlegame'],
                ['Endgame', 'Endgame'],
                ['Struktur bidak', 'Pawn structure'],
                ['Opposition', 'Opposition'],
            ],
        ];
    }
}; ?>

<div class="prose-page glossary">
    <h1>Istilah catur BM–Inggeris</h1>
    <p>ChessFlow mengajar dalam Bahasa Melayu. Bila awak baca buku catur, main di laman antarabangsa atau masuk pertandingan, awak akan jumpa istilah dalam bahasa Inggeris. Senarai ini membantu awak kenal kedua-duanya. Huruf notasi pun datang daripada nama Inggeris: <b>N</b> untuk <i>kNight</i> (Kuda), kerana <b>K</b> sudah dipakai untuk <i>King</i> (Raja).</p>

    @foreach ($this->groups() as $title => $rows)
        <table class="glossary-table">
            <caption>{{ $title }}</caption>
            <thead>
                <tr><th scope="col">Bahasa Melayu</th><th scope="col" lang="en">English</th><th scope="col">Notasi</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <th scope="row">
                            @isset($r[3])<i class="pc {{ $r[3] }}" aria-hidden="true"></i>@endisset
                            {{ $r[0] }}
                        </th>
                        <td lang="en">{{ $r[1] }}</td>
                        <td>
                            @if (($r[2] ?? null) === 'tiada huruf')
                                <span class="glossary-none">tiada huruf</span>
                            @elseif (isset($r[2]))
                                <code>{{ $r[2] }}</code>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <p class="glossary-note">Cara membaca langkah seperti <code>Nf3</code> dan <code>Qxf7#</code> diajar dalam pelajaran <b>Notasi Catur</b> (Tahap 2).</p>
</div>
