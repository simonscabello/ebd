<?php

namespace App\Models;

use App\Enums\Weekday;
use App\Models\Concerns\HasPosition;
use Database\Factories\LessonReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leitura bíblica ou orientação de leitura da semana.
 *
 * @property int $id
 * @property int $lesson_id
 * @property Weekday|null $weekday
 * @property string $reference
 * @property string|null $notes
 * @property int $position
 */
#[Fillable(['weekday', 'reference', 'notes', 'position'])]
class LessonReading extends Model
{
    /** @use HasFactory<LessonReadingFactory> */
    use HasFactory, HasPosition;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
