<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $number
 * @property string $name
 * @property string|null $note
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['number', 'name', 'note', 'position'])]
class Level extends Model
{
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
