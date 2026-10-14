<?php

use App\Enums\GameResult;
use App\Enums\Role;
use App\Models\Classroom;
use App\Models\Game;
use App\Models\ReviewItem;
use App\Models\User;
use App\Support\PlayReport;
use Database\Seeders\LessonSeeder;

function game(User $u, GameResult $r, int $level = 0, ?string $opening = null, int $moves = 10): Game
{
    return Game::create(['user_id' => $u->id, 'user_color' => 'w', 'level' => $level, 'result' => $r, 'pgn' => '1. e4 *', 'opening' => $opening, 'move_count' => $moves, 'finished_at' => now()]);
}

function gamePosition(User $u, Game $g, int $ply, ?string $lesson, string $due): ReviewItem
{
    return ReviewItem::create(['user_id' => $u->id, 'game_id' => $g->id, 'ply' => $ply, 'box' => 1, 'due_on' => $due,
        'position' => ['fen' => 'x', 'best' => 'e2e4', 'bestSan' => 'e4', 'also' => [], 'played' => 'a3', 'lesson' => $lesson]]);
}

beforeEach(fn () => $this->seed(LessonSeeder::class));

it('sums up games and practice per student', function () {
    $aina = User::factory()->student()->create(['name' => 'Aina']);
    $badrul = User::factory()->student()->create(['name' => 'Badrul']);
    $g = game($aina, GameResult::Win, 1, 'Italian Game');
    game($aina, GameResult::Win, 0, 'Italian Game');
    game($aina, GameResult::Resign, 2, 'Sicilian Defense');
    game($aina, GameResult::Draw);
    game($aina, GameResult::Resign, 0, null, 0); // resigned before moving: left out
    gamePosition($aina, $g, 2, 'fork', now()->subDay()->toDateString());
    gamePosition($aina, $g, 4, 'fork', now()->addDays(3)->toDateString());
    gamePosition($aina, $g, 6, 'cct', now()->addDays(3)->toDateString());

    $rows = app(PlayReport::class)->forStudents(collect([$aina, $badrul]))->keyBy(fn ($r) => $r['student']->name);

    expect($rows['Aina'])->toMatchArray(['played' => 4, 'won' => 2, 'lost' => 1, 'drawn' => 1, 'bestWin' => 1, 'opening' => 'Italian Game',
        'mistake' => ['title' => 'Fork Kuda', 'count' => 2], 'reviewDue' => 1, 'reviewTotal' => 3])
        ->and($rows['Badrul'])->toMatchArray(['played' => 0, 'bestWin' => null, 'opening' => null, 'mistake' => null, 'reviewTotal' => 0]);
});

it('shows the summary to parents and teachers', function () {
    $child = User::factory()->student()->create(['name' => 'Iman']);
    game($child, GameResult::Win, 2, 'Caro-Kann Defense');

    $parent = User::factory()->create(['role' => Role::IbuBapa]);
    $parent->students()->attach($child->id);
    $this->actingAs($parent)->get(route('anak'))->assertOk()
        ->assertSee(__('Permainan & latihan'))->assertSee('Caro-Kann Defense')->assertSee(__('Sukar'));

    $teacher = User::factory()->teacher()->create();
    $class = Classroom::create(['teacher_id' => $teacher->id, 'name' => '4 Bestari', 'join_code' => Classroom::newJoinCode()]);
    $class->students()->attach($child->id);
    $this->actingAs($teacher)->get(route('guru.kelas', $class))->assertOk()
        ->assertSee(__('Permainan & latihan'))->assertSee('Caro-Kann Defense');
});
