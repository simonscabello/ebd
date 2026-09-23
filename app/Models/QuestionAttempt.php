<?php

namespace App\Models;

use App\Enums\SelfAssessment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $lesson_question_id
 * @property SelfAssessment $self_assessment
 */
class QuestionAttempt extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'lesson_question_id', 'self_assessment'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['self_assessment' => SelfAssessment::class];
    }

    /**
     * @return BelongsTo<LessonQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(LessonQuestion::class, 'lesson_question_id');
    }
}
