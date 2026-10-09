<?php

use App\Actions\CompleteReview;
use App\Actions\ScheduleReview;
use App\Enums\Role;
use App\Models\Lesson;
use App\Models\ReviewItem;
use App\Models\User;
use App\Support\Chessflow;
use Database\Seeders\LessonSeeder;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(LessonSeeder::class);
    $this->student = User::factory()->student()->create();
    $this->lesson = Lesson::where('slug', 'fork')->firstOrFail();
    $this->puzzleIndex = collect($this->lesson->steps)->search(fn (array $s) => $s['type'] === 'puzzle');
    $this->explainIndex = collect($this->lesson->steps)->search(fn (array $s) => $s['type'] === 'explain');
});

it('queues only real, reviewable steps for tomorrow', function () {
    $n = app(ScheduleReview::class)->handle($this->student, $this->lesson, [$this->puzzleIndex, $this->puzzleIndex, $this->explainIndex, 99, '1', -1]);

    expect($n)->toBe(1);
    $item = $this->student->reviewItems()->sole();
    expect($item->step_index)->toBe($this->puzzleIndex)
        ->and($item->box)->toBe(1)
        ->and($item->due_on->toDateString())->toBe(Chessflow::now()->addDay()->toDateString())
        ->and($item->isDue())->toBeFalse();
});

it('moves a right answer through the boxes and retires it after the last one', function () {
    $item = ReviewItem::create(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'step_index' => $this->puzzleIndex, 'box' => 1, 'due_on' => Chessflow::today()]);
    $review = app(CompleteReview::class);

    foreach ([3, 7, 14] as $i => $days) {
        $r = $review->handle($this->student, $item->fresh(), true);
        expect($r)->toMatchArray(['counted' => true, 'mastered' => false, 'xp' => 5])
            ->and($item->fresh()->box)->toBe($i + 2)
            ->and($item->fresh()->due_on->toDateString())->toBe(Chessflow::now()->addDays($days)->toDateString());
        $item->fresh()->update(['due_on' => Chessflow::today()]);
    }

    expect($review->handle($this->student, $item->fresh(), true))->toMatchArray(['mastered' => true, 'xp' => 5])
        ->and(ReviewItem::count())->toBe(0)
        ->and($this->student->fresh()->xp)->toBe(20);
});

it('sends a wrong answer back to the first box for tomorrow, without XP', function () {
    $item = ReviewItem::create(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'step_index' => $this->puzzleIndex, 'box' => 3, 'due_on' => Chessflow::today()]);

    expect(app(CompleteReview::class)->handle($this->student, $item, false))->toMatchArray(['counted' => true, 'correct' => false, 'xp' => 0])
        ->and($item->fresh()->box)->toBe(1)
        ->and($item->fresh()->due_on->toDateString())->toBe(Chessflow::now()->addDay()->toDateString());
});

it('ignores a step that is not due, so replays earn nothing', function () {
    $item = ReviewItem::create(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'step_index' => $this->puzzleIndex, 'box' => 2, 'due_on' => Chessflow::now()->addDays(2)->toDateString()]);

    expect(app(CompleteReview::class)->handle($this->student, $item, true))->toMatchArray(['counted' => false, 'xp' => 0])
        ->and($item->fresh()->box)->toBe(2);
});

it('queues wrong steps reported by a finished lesson', function () {
    $this->student->forceFill(['role' => Role::Guru])->save(); // staff: every lesson is open

    $page = Livewire::actingAs($this->student)->test('pages::pelajaran', ['lesson' => $this->lesson]);
    $this->travel(5)->minutes();
    $page->dispatch('lesson-completed', slug: 'fork', mistakes: 2, failedSteps: [$this->puzzleIndex], durationMs: 1000);

    expect($this->student->reviewItems()->pluck('step_index')->all())->toBe([$this->puzzleIndex]);
});

it('serves the due step on /latih and records the answer', function () {
    ReviewItem::create(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'step_index' => $this->puzzleIndex, 'box' => 1, 'due_on' => Chessflow::today()]);

    $this->actingAs($this->student)->get(route('latih'))
        ->assertOk()
        ->assertSee('data-chessflow="lesson"', false)
        ->assertSee($this->lesson->title);

    $page = Livewire::actingAs($this->student)->test('pages::latih');
    $this->travel(1)->minutes();
    $page->dispatch('lesson-completed', mistakes: 0, failedSteps: [])
        ->assertDispatched('lesson-result', fn (string $name, array $p) => $p['xp'] === 5 && $p['review']['correct'] && $p['review']['remaining'] === 0);
});

it('shows an empty state and hides the map card when there is nothing to practise', function () {
    $this->actingAs($this->student)->get(route('latih'))->assertOk()->assertSee('Tiada latihan hari ini');
    $this->actingAs($this->student)->get(route('peta'))->assertDontSee('Latih semula');

    ReviewItem::create(['user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'step_index' => $this->puzzleIndex, 'box' => 1, 'due_on' => Chessflow::today()]);
    $this->actingAs($this->student)->get(route('peta'))->assertSee('Latih semula');
});

it('never serves or scores another student\'s step', function () {
    $other = User::factory()->student()->create();
    $item = ReviewItem::create(['user_id' => $other->id, 'lesson_id' => $this->lesson->id, 'step_index' => $this->puzzleIndex, 'box' => 1, 'due_on' => Chessflow::today()]);

    $this->actingAs($this->student)->get(route('latih'))->assertSee('Tiada latihan hari ini');
    expect(fn () => app(CompleteReview::class)->handle($this->student, $item, true))->toThrow(HttpException::class);
});
