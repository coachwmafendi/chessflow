<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Free analysis board. Links can carry a position and moves: ?fen=<start>&moves=e4 e5 Nf3&ply=2 (all optional);
// the island validates them with chess.js.
new #[Layout('layouts::chessflow')] #[Title('Analisis papan bebas')] class extends Component {}; ?>

<div>
    <p class="no-print" style="margin-block: 8px 0"><a class="ghost" href="{{ route('main') }}">{{ __('← Main') }}</a></p>
    <div wire:ignore data-chessflow="analysis"
        data-fen="{{ request()->string('fen')->limit(100, '') }}"
        data-moves="{{ request()->string('moves')->limit(4000, '') }}"
        data-ply="{{ request()->string('ply')->limit(4, '') }}"></div>
</div>
