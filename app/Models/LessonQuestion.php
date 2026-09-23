<?php

namespace App\Models;

use App\Models\Concerns\HasPosition;
use Database\Factories\LessonQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pergunta para reflexão durante a semana e discussão no domingo.
 *
 * @property int $id
 * @property int $lesson_id
 * @property string $body
 * @property int $position
 */
#[Fillable(['body', 'position'])]
class LessonQuestion extends Model
{
    /** @use HasFactory<LessonQuestionFactory> */
    use HasFactory, HasPosition;

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
