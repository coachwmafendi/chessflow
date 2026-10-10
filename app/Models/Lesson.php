<?php

namespace App\Models;

use App\Enums\LessonKind;
use App\Support\LessonText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property array<string, array<string, string>>|null $i18n
 * @property bool $is_published
 * @property int $content_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['level_id', 'slug', 'title', 'icon', 'kind', 'position', 'xp', 'tip', 'i18n', 'steps', 'is_published', 'content_version'])]
class Lesson extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => LessonKind::class,
            'steps' => 'array',
            'i18n' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Title in the page language (Malay unless data/lessons.json has a translation).
     *
     * @return Attribute<string|null, never>
     */
    protected function title(): Attribute
    {
        return Attribute::get(fn (?string $value) => LessonText::field($this->i18n, 'title', $value));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function tip(): Attribute
    {
        return Attribute::get(fn (?string $value) => LessonText::field($this->i18n, 'tip', $value));
    }

    /**
     * Steps with their translations merged in for the page language (what the TS island gets).
     *
     * @return list<array<string, mixed>>
     */
    public function localizedSteps(): array
    {
        return LessonText::steps($this->steps);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function localizedStep(int $index): ?array
    {
        return isset($this->steps[$index]) ? LessonText::step($this->steps[$index]) : null;
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
