<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Bantuan')] class extends Component {
    public const EMAIL = 'wmafendi@gmail.com';
}; ?>

{{-- Help docs: prose lives in help/ms and help/en (like the legal pages), picked by the current locale. --}}
<div class="prose-page help">
    @include('help.'.(app()->getLocale() === 'en' ? 'en' : 'ms'), ['email' => self::EMAIL])
</div>
