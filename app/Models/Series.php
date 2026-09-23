<?php

namespace App\Models;

use Database\Factories\SeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Série de estudos de uma classe (ex.: "Jornada dos Milagres de Jesus").
 *
 * @property int $id
 * @property int $classroom_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 */
#[Fillable(['title', 'slug', 'description', 'starts_on', 'ends_on'])]
class Series extends Model
{
    /** @use HasFactory<SeriesFactory> */
    use HasFactory;

    protected $table = 'series';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
