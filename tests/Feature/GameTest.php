<?php

use App\Actions\RecordGame;
use App\Enums\GameResult;
use App\Models\User;

beforeEach(fn () => $this->student = User::factory()->student()->create());

it('awards XP by level only for wins', function () {
    $record = app(RecordGame::class);

    expect($record->handle($this->student, 'w', 2, GameResult::Win, '1. e4 e5', 2))->toMatchArray(['xp' => 100, 'wins' => 1])
        ->and($record->handle($this->student, 'b', 0, GameResult::Loss, '1. e4 e5', 2)['xp'])->toBe(0)
        ->and($record->handle($this->student, 'w', 1, GameResult::Resign, '', 0)['xp'])->toBe(0)
        ->and($this->student->games()->count())->toBe(3)
        ->and($this->student->fresh()->xp)->toBe(100);
});

it('caps rewarded wins per day', function () {
    config(['chessflow.game.rewarded_wins_per_day' => 2]);
    $record = app(RecordGame::class);

    $xp = collect(range(1, 3))->map(fn () => $record->handle($this->student, 'w', 0, GameResult::Win, '', 1)['xp']);

    expect($xp->all())->toBe([20, 20, 0]);
});

it('clamps an unknown level', function () {
    $result = app(RecordGame::class)->handle($this->student, 'w', 9, GameResult::Win, '', 1);

    expect($result['xp'])->toBe(100)
        ->and($this->student->games()->first()->level)->toBe(2);
});

it('records an agreed draw without XP', function () {
    $result = app(RecordGame::class)->handle($this->student, 'w', 1, GameResult::Draw, '1. Nf3 Nf6', 40);

    expect($result['xp'])->toBe(0)
        ->and($this->student->games()->first()->result)->toBe(GameResult::Draw)
        ->and($this->student->fresh()->xp)->toBe(0);
});
