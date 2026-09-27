<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Anotação do professor sobre um aluno de uma classe. Privada: só
 * professores da classe e a administração veem.
 *
 * @property int $id
 * @property int $classroom_id
 * @property int $user_id
 * @property int|null $author_id
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Classroom $classroom
 * @property-read User $student
 * @property-read User|null $author
 */
#[Fillable(['body'])]
class StudentNote extends Model
{
    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
