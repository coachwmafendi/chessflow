<?php

use App\Enums\GameResult;
use App\Models\Game;
use App\Models\User;
use App\Support\PgnMoves;
use Livewire\Livewire;

function playedGame(User $user, GameResult $result, int $level = 0, string $pgn = "[Event \"?\"]\n\n1. e4 e5 2. Nf3 Nc6 *", string $color = 'w'): Game
{
    return Game::create([
        'user_id' => $user->id, 'user_color' => $color, 'level' => $level, 'result' => $result,
        'pgn' => $pgn, 'move_count' => count(PgnMoves::san($pgn)), 'finished_at' => now(),
    ]);
}

it('reads the moves out of a stored PGN', function () {
    $pgn = "[Event \"?\"]\n[Result \"*\"]\n\n1. a3 e5 2. a4 {note} Bb4 (2... Nf6 (2... d5)) 3. 0-0 c6 4. e8=Q+ Qxe8# 1-0";

    expect(PgnMoves::san($pgn))->toBe(['a3', 'e5', 'a4', 'Bb4', 'O-O', 'c6', 'e8=Q+', 'Qxe8#'])
        ->and(PgnMoves::opening($pgn, 4))->toBe('1. a3 e5 2. a4 Bb4 …')
        ->and(PgnMoves::san("[Event \"?\"]\n\n*"))->toBe([]);
});

it('shows a player only their own games, with totals by level', function () {
    $me = User::factory()->student()->create();
    playedGame($me, GameResult::Win, 0);
    playedGame($me, GameResult::Resign, 1);
    playedGame($me, GameResult::Loss, 1);
    playedGame($me, GameResult::Draw, 2, "[Event \"?\"]\n\n1. d4 d5 *", 'b');
    playedGame(User::factory()->student()->create(), GameResult::Win, 2, "[Event \"?\"]\n\n1. h4 h5 *");
    playedGame($me, GameResult::Resign, 0, "[Event \"?\"]\n\n*"); // resigned before moving: not listed

    $this->get(route('main.sejarah'))->assertRedirect(route('login'));

    Livewire::actingAs($me)->test('pages::sejarah')
        ->assertOk()
        ->assertViewHas('total', 4)
        ->assertViewHas('won', 1)
        ->assertViewHas('lost', 2)
        ->assertViewHas('drawn', 1)
        ->assertViewHas('byLevel', fn ($rows) => $rows[1]['played'] === 2 && $rows[1]['lost'] === 2 && $rows[2]['drawn'] === 1)
        ->assertSee('1. e4 e5 2. Nf3 Nc6')
        ->assertDontSee('1. h4 h5')
        ->assertSee(route('analisis', ['moves' => 'e4 e5 Nf3 Nc6']), false)
        ->assertSee('2 langkah'); // 4 half-moves
});

it('filters by result and shows more on request', function () {
    $me = User::factory()->student()->create();
    foreach (range(1, 16) as $i) {
        playedGame($me, GameResult::Loss);
    }
    playedGame($me, GameResult::Win);

    Livewire::actingAs($me)->test('pages::sejarah')
        ->assertViewHas('games', fn ($games) => $games->count() === 15)
        ->assertViewHas('hasMore', true)
        ->call('more')
        ->assertViewHas('games', fn ($games) => $games->count() === 17)
        ->assertViewHas('hasMore', false)
        ->set('filter', 'menang')
        ->assertViewHas('games', fn ($games) => $games->count() === 1 && $games->first()->result === GameResult::Win)
        ->set('filter', 'seri')
        ->assertSee(__('Tiada permainan untuk pilihan ini.'));
});

it('invites a player with no games to play', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('main.sejarah'))
        ->assertOk()
        ->assertSee(__('Belum ada permainan. Main lawan Pak Kuda, dan setiap permainan akan disimpan di sini.'));
});
