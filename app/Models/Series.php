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
 * @property int|null $source_series_id estudo oficial de onde veio a cópia
 * @property int|null $source_version versão do estudo oficial que a cópia conhece
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
            'source_version' => 'integer',
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

    /**
     * Versões publicadas na biblioteca oficial (só estudos oficiais têm).
     *
     * @return HasMany<SeriesVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SeriesVersion::class);
    }

    /**
     * Cópia de estudo oficial com versão nova publicada: {from, to}. Usa a
     * última versão já carregada em latest_source_version, quando houver
     * (listas), para não consultar uma vez por série.
     *
     * @return array{from: int, to: int}|null
     */
    public function updateAvailable(): ?array
    {
        if ($this->source_series_id === null || $this->source_version === null) {
            return null;
        }

        $latest = $this->hasAttribute('latest_source_version')
            ? $this->getAttribute('latest_source_version')
            : SeriesVersion::query()->where('series_id', $this->source_series_id)->max('version');

        return $latest !== null && (int) $latest > $this->source_version
            ? ['from' => $this->source_version, 'to' => (int) $latest]
            : null;
    }

    public function latestVersion(): ?SeriesVersion
    {
        return $this->versions()->orderByDesc('version')->first();
    }

    /**
     * Estudo oficial de onde a cópia veio.
     *
     * @return BelongsTo<Series, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'source_series_id');
    }
}
