<?php

use App\Enums\LessonKind;
use App\Models\Lesson;
use App\Models\Level;

function lessonsFixture(callable $mutate): string
{
    $data = json_decode((string) file_get_contents(base_path('data/lessons.json')), true);
    $data = $mutate($data);
    $path = (string) tempnam(sys_get_temp_dir(), 'lessons');
    file_put_contents($path, json_encode($data));
    register_shutdown_function(fn () => @unlink($path));

    return $path;
}

it('imports every level, lesson and step from data/lessons.json', function () {
    $this->artisan('chessflow:import-lessons')->assertSuccessful();

    expect(Level::count())->toBe(5)
        ->and(Lesson::count())->toBe(32)
        ->and(Lesson::all()->sum(fn (Lesson $l) => count($l->steps)))->toBe(159)
        ->and(Lesson::where('kind', LessonKind::Exam)->count())->toBe(5);

    $kuda = Lesson::where('slug', 'kuda')->firstOrFail();
    expect($kuda->level->number)->toBe(1)
        ->and($kuda->icon)->toBe('wN')
        ->and($kuda->xp)->toBe(count($kuda->steps) * 10)
        ->and($kuda->is_published)->toBeTrue();

    expect(Lesson::where('slug', 'ujian1')->firstOrFail()->xp)->toBe(80);
});

it('seeds the full curriculum via DatabaseSeeder', function () {
    $this->seed();

    expect(Level::count())->toBe(5)
        ->and(Lesson::count())->toBe(32)
        ->and(Lesson::all()->sum(fn (Lesson $l) => count($l->steps)))->toBe(159);
});

it('is idempotent by slug', function () {
    $this->artisan('chessflow:import-lessons')->assertSuccessful();
    $ids = Lesson::orderBy('slug')->pluck('id', 'slug');

    $this->artisan('chessflow:import-lessons')->assertSuccessful();

    expect(Level::count())->toBe(5)
        ->and(Lesson::count())->toBe(32)
        ->and(Lesson::orderBy('slug')->pluck('id', 'slug'))->toEqual($ids)
        ->and(Lesson::where('content_version', '>', 1)->count())->toBe(0);
});

it('bumps content_version only for lessons whose content changed', function () {
    $this->artisan('chessflow:import-lessons')->assertSuccessful();

    $path = lessonsFixture(function (array $data) {
        $data['lessons'][1]['title'] = 'Kuda Baharu';

        return $data;
    });
    $this->artisan('chessflow:import-lessons', ['--path' => $path])->assertSuccessful();

    expect(Lesson::where('slug', 'kuda')->value('title'))->toBe('Kuda Baharu')
        ->and(Lesson::where('slug', 'kuda')->value('content_version'))->toBe(2)
        ->and(Lesson::where('content_version', 2)->count())->toBe(1);
});

it('keeps is_published set by admins on re-import', function () {
    $this->artisan('chessflow:import-lessons')->assertSuccessful();
    Lesson::where('slug', 'kuda')->update(['is_published' => false]);

    $this->artisan('chessflow:import-lessons')->assertSuccessful();

    expect(Lesson::where('slug', 'kuda')->value('is_published'))->toBeFalse();
});

it('refuses to import lessons that fail validation', function () {
    $path = lessonsFixture(function (array $data) {
        $data['lessons'][0]['steps'][0]['type'] = 'quiz';
        $data['lessons'][0]['steps'][0]['options'] = [['t' => 'Salah']];

        return $data;
    });

    $this->artisan('chessflow:import-lessons', ['--path' => $path])->assertFailed();

    expect(Lesson::count())->toBe(0);
});
