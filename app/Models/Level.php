<?php

namespace App\Models;

use App\Support\LessonText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $number
 * @property string $name
 * @property string|null $note
 * @property int $position
 * @property array<string, array<string, string>>|null $i18n
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['number', 'name', 'note', 'i18n', 'position'])]
class Level extends Model
{
    protected function casts(): array
    {
        return ['i18n' => 'array'];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (?string $value) => LessonText::field($this->i18n, 'name', $value));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function note(): Attribute
    {
        return Attribute::get(fn (?string $value) => LessonText::field($this->i18n, 'note', $value));
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
