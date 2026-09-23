<?php

namespace App\Models;

use App\Enums\Badge;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property Badge $badge
 * @property int|null $series_id
 * @property CarbonImmutable $awarded_at
 * @property-read Series|null $series
 */
class UserBadge extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'badge' => Badge::class,
            'awarded_at' => 'datetime',
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
