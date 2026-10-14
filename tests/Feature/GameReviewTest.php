<?php

use App\Actions\CompleteReview;
use App\Actions\ScheduleGameReview;
use App\Enums\GameResult;
use App\Models\Game;
use App\Models\ReviewItem;
use App\Models\User;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

// 1. e4 e5 2. Qh5 Nc6 3. Bc4 Nf6?? 4. Qxf7#  (Black, the player, missed the defence at ply 5)
const MATE_PGN = "[Event \"?\"]\n\n1. e4 e5 2. Qh5 Nc6 3. Bc4 Nf6 4. Qxf7# 1-0";
const BEFORE_NF6 = 'r1bqkbnr/pppp1ppp/2n5/4p2Q/2B1P3/8/PPPP1PPP/RNB1K1NR b KQkq - 3 3';

function blackGame(User $user): Game
{
    return Game::create([
        'user_id' => $user->id, 'user_color' => 'b', 'level' => 1, 'result' => GameResult::Loss,
        'pgn' => MATE_PGN, 'move_count' => 7, 'finished_at' => now(),
    ]);
}

function nf6Mistake(array $overrides = []): array
{
    return array_merge(['ply' => 5, 'fen' => BEFORE_NF6, 'best' => 'd8e7', 'bestSan' => 'Qe7', 'also' => ['g6'], 'played' => 'Nf6'], $overrides);
}

it('queues the position before a mistake, checked against the stored game', function () {
    $user = User::factory()->student()->create();
    $game = blackGame($user);

    $schedule = fn (array $m) => app(ScheduleGameReview::class)->handle($user, $game, [nf6Mistake($m)]);
    expect($schedule(['ply' => 4, 'played' => 'Bc4']))->toBe(0)   // White's move, not the player's
        ->and($schedule(['ply' => 3, 'played' => 'Nc6']))->toBe(0) // a real move of the player, but the FEN is from move 3
        ->and($schedule(['ply' => 1, 'played' => 'Nf6']))->toBe(0) // not what was played at ply 1
        ->and($schedule(['fen' => 'not a fen']))->toBe(0)
        ->and($schedule(['best' => 'z9z9']))->toBe(0)
        ->and($schedule(['bestSan' => 'Nf6']))->toBe(0);             // the "better" move is the one played

    $n = $schedule([]);
    expect($n)->toBe(1);
    $item = ReviewItem::sole();
    expect($item->game_id)->toBe($game->id)
        ->and($item->ply)->toBe(5)
        ->and($item->lesson_id)->toBeNull()
        ->and($item->position)->toMatchArray(['best' => 'd8e7', 'bestSan' => 'Qe7', 'also' => ['g6'], 'played' => 'Nf6'])
        ->and($item->due_on->toDateString())->toBe(now('Asia/Kuala_Lumpur')->addDay()->toDateString());
});

it('accepts mistakes only for the player\'s own game and caps them per day', function () {
    $user = User::factory()->student()->create();
    $other = User::factory()->student()->create();

    expect(fn () => app(ScheduleGameReview::class)->handle($other, blackGame($user), [nf6Mistake()]))
        ->toThrow(HttpException::class);

    config(['chessflow.review.game_positions_per_day' => 2]);
    foreach (range(1, 3) as $i) {
        app(ScheduleGameReview::class)->handle($user, blackGame($user), [nf6Mistake()]);
    }
    expect($user->reviewItems()->count())->toBe(2);
});

it('reports saved mistakes from the Play page', function () {
    $user = User::factory()->student()->create();
    $game = blackGame($user);

    Livewire::actingAs($user)->test('pages::main')
        ->dispatch('game-mistakes', gameId: $game->id, mistakes: [nf6Mistake()])
        ->assertDispatched('game-mistakes-saved', count: 1);

    // someone else's game id does nothing
    Livewire::actingAs(User::factory()->student()->create())->test('pages::main')
        ->dispatch('game-mistakes', gameId: $game->id, mistakes: [nf6Mistake()])
        ->assertNotDispatched('game-mistakes-saved');
});

it('practises a game position in Latih semula as a one-move puzzle', function () {
    $user = User::factory()->student()->create();
    $game = blackGame($user);
    app(ScheduleGameReview::class)->handle($user, $game, [nf6Mistake()]);
    $item = ReviewItem::sole();
    $item->update(['due_on' => now()->subDay()]);

    $component = Livewire::actingAs($user)->test('pages::latih');
    $step = $component->instance()->payload()['steps'][0];
    expect($step)->toMatchArray(['type' => 'puzzle', 'fen' => BEFORE_NF6, 'orient' => 'b', 'line' => ['d8e7'], 'acceptSan' => ['g6']])
        ->and($step['say'])->toContain('<b>Nf6</b>');

    $r = app(CompleteReview::class)->handle($user, $item->fresh(), true);
    expect($r['counted'])->toBeTrue()->and($item->fresh()->box)->toBe(2);
});
