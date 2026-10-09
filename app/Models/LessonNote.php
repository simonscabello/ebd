<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Anotação pessoal do aluno numa lição. Privada: só a própria pessoa lê.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property string $body
 */
class LessonNote extends Model
{
    /**
     * @var list<string>
     */
    protected $hidden = ['body'];

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'lesson_id', 'body'];
}
