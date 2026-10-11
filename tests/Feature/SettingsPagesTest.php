<?php

use App\Models\User;
use Database\Seeders\LessonSeeder;

beforeEach(fn () => $this->seed(LessonSeeder::class));

it('shows the account type, with a teacher request for parents', function () {
    $parent = User::factory()->create();
    $this->actingAs($parent)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Jenis akaun')
        ->assertSee('Ibu bapa')
        ->assertSee('Mohon akaun guru')
        ->assertSee(rawurlencode('E-mel akaun: '.$parent->email), false);

    $this->actingAs(User::factory()->teacher()->create())->get(route('profile.edit'))
        ->assertSee('Guru')
        ->assertDontSee('Mohon akaun guru');
});

it('keeps data download and account deletion on the Data & privacy tab', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)->get(route('data.edit'))
        ->assertOk()
        ->assertSee('Muat turun data saya')
        ->assertSee('data-test="delete-user-button"', false)
        ->assertSee('Ke halaman Anak')
        ->assertSee('Kelas yang anda cipta akan dipadam');

    $this->actingAs($parent)->get(route('profile.edit'))->assertDontSee('data-test="delete-user-button"', false);
});

it('puts theme, language and sound on one tab', function () {
    $this->actingAs(User::factory()->create())->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('Paparan &amp; bahasa', false)
        ->assertSee('data-test="language"', false)
        ->assertSee('action="'.route('locale.switch', 'en').'"', false)
        ->assertSee('data-test="sound"', false)
        ->assertSee('chessflow-snd', false);
});

it('lists the same destinations as the header in the settings sidebar, by role', function () {
    $html = fn (User $u) => $this->actingAs($u)->get(route('profile.edit'))->getContent();

    $parent = $html(User::factory()->create());
    expect($parent)->toContain('href="'.route('main').'"')
        ->toContain('href="'.route('lencana').'"')
        ->toContain('href="'.route('anak').'"')
        ->not->toContain('href="'.route('guru').'"');

    expect($html(User::factory()->teacher()->create()))->toContain('href="'.route('guru').'"');
});

it('names roles in Malay as well as English', function () {
    app()->setLocale('ms');
    expect(__('roles.ibubapa'))->toBe('Ibu bapa')->and(__('roles.admin'))->toBe('Pentadbir');
    app()->setLocale('en');
    expect(__('roles.ibubapa'))->toBe('Parent');
});
