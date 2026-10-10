<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Terma Penggunaan')] class extends Component {
    public const EMAIL = 'wmafendi@gmail.com';
}; ?>

<div class="prose-page legal">
    @include('legal.terma-'.(app()->getLocale() === 'en' ? 'en' : 'ms'), ['email' => self::EMAIL])
</div>
