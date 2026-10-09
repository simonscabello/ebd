<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dúvida que a pessoa tirou com a IA numa lição, com a resposta dada.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property string $question
 * @property string $answer
 */
class StudyQuestion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'lesson_id', 'question', 'answer'];
}
