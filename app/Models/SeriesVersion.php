<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Versão publicada de um estudo oficial (ver SeriesSnapshot para o formato
 * do retrato). Imutável depois de criada.
 *
 * @property int $id
 * @property int $series_id
 * @property int $version
 * @property string|null $notes
 * @property array{title: string, description: string|null, lessons: list<array<string, mixed>>} $snapshot
 * @property int|null $published_by
 * @property Carbon $created_at
 * @property-read Series $series
 */
class SeriesVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Series, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }
}
