<?php

namespace App\Models;

use App\Enums\LessonKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $level_id
 * @property string $slug
 * @property string $title
 * @property string $icon
 * @property LessonKind $kind
 * @property int $position
 * @property int $xp
 * @property string|null $tip
 * @property list<array<string, mixed>> $steps
 * @property bool $is_published
 * @property int $content_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['level_id', 'slug', 'title', 'icon', 'kind', 'position', 'xp', 'tip', 'steps', 'is_published', 'content_version'])]
class Lesson extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => LessonKind::class,
            'steps' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * @return HasMany<ExamAttempt, $this>
     */
    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function isExam(): bool
    {
        return $this->kind === LessonKind::Exam;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
