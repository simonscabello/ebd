<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dúvida que a pessoa tirou com a IA numa lição, com a resposta dada.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property string $question
 * @property string $answer
 * @property-read User $user
 */
class StudyQuestion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'lesson_id', 'question', 'answer'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
