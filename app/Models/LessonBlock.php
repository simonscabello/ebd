<?php

namespace App\Models;

use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Enums\Weekday;
use App\Models\Concerns\HasPosition;
use Database\Factories\LessonBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloco de conteúdo da lição (roteiro, contexto, curiosidade, conceito...).
 *
 * @property int $id
 * @property int $lesson_id
 * @property LessonBlockKind $kind
 * @property ContentAudience $audience
 * @property string|null $title
 * @property string $body
 * @property Weekday|null $drip_weekday
 * @property int $position
 */
#[Fillable(['kind', 'audience', 'title', 'body', 'drip_weekday', 'position'])]
class LessonBlock extends Model
{
    /** @use HasFactory<LessonBlockFactory> */
    use HasFactory, HasPosition;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LessonBlockKind::class,
            'audience' => ContentAudience::class,
            'drip_weekday' => Weekday::class,
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

    public function isForTeachers(): bool
    {
        return $this->audience === ContentAudience::Teacher;
    }

    public function displayTitle(): string
    {
        return $this->title ?: $this->kind->label();
    }
}
