<?php

use App\Actions\AwardBadges;
use App\Actions\CompleteLesson;
use App\Actions\RecordGame;
use App\Enums\GameResult;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Chessflow;
use App\Support\UserDataExport;
use Database\Seeders\LessonSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->parent = User::factory()->create(['name' => 'Puan Siti']);
    $this->child = User::factory()->student()->create(['name' => 'Aina', 'username' => 'aina2']);
    $this->parent->students()->attach($this->child->id);
});

it('puts a student\'s learning data in the export, without any secret', function () {
    app(CompleteLesson::class)->handle($this->child, Lesson::where('slug', 'papan')->firstOrFail(), 0);
    app(RecordGame::class)->handle($this->child, 'w', 0, GameResult::Win, '1. e4 e5', 2);
    app(AwardBadges::class)->handle($this->child);

    $data = app(UserDataExport::class)->for($this->child->fresh());
    $json = json_encode($data);

    expect($data['account']['username'])->toBe('aina2')
        ->and($data['lessons'][0]['lesson'])->toBe('Papan Catur')
        ->and($data['games'][0]['result'])->toBe('win')
        ->and(collect($data['badges'])->pluck('badge'))->toContain('Langkah Pertama')
        ->and($data['guardians'])->toBe(['Puan Siti'])
        ->and($json)->not->toContain('$2y$') // no password/PIN hashes
        ->and($data['account'])->not->toHaveKeys(['pin', 'password', 'remember_token', 'two_factor_secret']);
});

it('lets a parent download their child\'s data, and nobody else', function () {
    Livewire::actingAs($this->parent)->test('pages::anak')
        ->call('exportChild', $this->child->id)
        ->assertFileDownloaded('chessflow-data-aina2-'.Chessflow::today().'.json');

    Livewire::actingAs(User::factory()->create())->test('pages::anak')
        ->call('exportChild', $this->child->id)
        ->assertNotFound();
});

it('lets an adult download their own data from the profile settings', function () {
    Livewire::actingAs($this->parent)->test('pages::settings.profile')
        ->assertSee('Muat turun data saya')
        ->call('downloadMyData')
        ->assertFileDownloaded();

    $data = app(UserDataExport::class)->for($this->parent);
    expect($data['account']['email'])->toBe($this->parent->email)
        ->and($data['linked_children'][0]['name'])->toBe('Aina');
});
