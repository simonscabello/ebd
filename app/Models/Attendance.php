<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Presença de um aluno em um encontro.
 *
 * @property int $id
 * @property int $class_meeting_id
 * @property int $user_id
 * @property int|null $recorded_by
 */
class Attendance extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<ClassMeeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ClassMeeting::class, 'class_meeting_id');
    }
}
