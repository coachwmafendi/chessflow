<?php

namespace App\Actions;

use App\Enums\LessonKind;
use App\Models\Lesson;
use App\Models\Level;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Upserts levels and lessons from data/lessons.json, keyed by level number and lesson slug.
 * The JSON file is the source of truth for content; admin-owned fields (is_published, and
 * position once a lesson exists — admins reorder in Filament) are kept.
 */
class ImportLessons
{
    /**
     * @return array{levels: int, created: int, updated: int, unchanged: int, orphaned: list<string>}
     *
     * @throws JsonException
     */
    public function handle(string $path): array
    {
        /** @var array{levels: list<array{n: int, name: string, note?: string}>, lessons: list<array<string, mixed>>} $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($data) {
            $levels = [];
            foreach ($data['levels'] as $i => $lv) {
                $levels[$lv['n']] = Level::updateOrCreate(
                    ['number' => $lv['n']],
                    ['name' => $lv['name'], 'note' => $lv['note'] ?? null, 'position' => $i + 1],
                );
            }

            $stats = ['levels' => count($levels), 'created' => 0, 'updated' => 0, 'unchanged' => 0];
            $slugs = [];

            foreach ($data['lessons'] as $i => $l) {
                $slug = (string) $l['id'];
                $slugs[] = $slug;
                $exam = ! empty($l['exam']);
                /** @var list<array<string, mixed>> $steps */
                $steps = $l['steps'];

                $attrs = [
                    'level_id' => $levels[(int) $l['tahap']]->id,
                    'title' => (string) $l['title'],
                    'icon' => (string) $l['icon'],
                    'kind' => $exam ? LessonKind::Exam : LessonKind::Lesson,
                    'position' => (int) ($l['position'] ?? $i + 1),
                    // Same default as the prototype: exam 80 XP, otherwise 10 XP per step.
                    'xp' => (int) ($l['xp'] ?? ($exam ? 80 : count($steps) * 10)),
                    'tip' => isset($l['tip']) ? (string) $l['tip'] : null,
                    'steps' => $steps,
                ];

                $lesson = Lesson::firstWhere('slug', $slug);

                if (! $lesson) {
                    Lesson::create($attrs + ['slug' => $slug]);
                    $stats['created']++;

                    continue;
                }

                unset($attrs['position']);
                $lesson->fill($attrs);
                if (! $lesson->isDirty()) {
                    $stats['unchanged']++;

                    continue;
                }

                if ($lesson->isDirty(['title', 'icon', 'kind', 'tip', 'steps'])) {
                    $lesson->content_version++;
                }
                $lesson->save();
                $stats['updated']++;
            }

            /** @var list<string> $orphaned */
            $orphaned = Lesson::whereNotIn('slug', $slugs)->pluck('slug')->all();

            return $stats + ['orphaned' => $orphaned];
        });
    }
}
