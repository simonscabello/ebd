<?php

namespace App\Models;

use App\Enums\QuestionKind;
use App\Models\Concerns\HasPosition;
use Database\Factories\LessonQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pergunta de reflexão (discussão no domingo) ou de revisão (com gabarito).
 *
 * @property int $id
 * @property int $lesson_id
 * @property QuestionKind $kind
 * @property string $body
 * @property string|null $answer
 * @property int $position
 */
#[Fillable(['kind', 'body', 'answer', 'position'])]
class LessonQuestion extends Model
{
    /** @use HasFactory<LessonQuestionFactory> */
    use HasFactory, HasPosition;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'reflection',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => QuestionKind::class,
            'position' => 'integer',
        ];
    }

    public function isReview(): bool
    {
        return $this->kind === QuestionKind::Review;
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
