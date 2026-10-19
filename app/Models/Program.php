<?php

namespace App\Models;

use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Programa de ensino de uma igreja (Escola Bíblica Dominical, discipulado,
 * pequenos grupos): agrupa turmas e pode ter coordenadores.
 *
 * @property int $id
 * @property int $church_id
 * @property string $name
 * @property string $short_name
 * @property string $slug
 * @property int $position
 */
#[Fillable(['name', 'short_name', 'slug', 'position'])]
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Church, $this>
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * @return HasMany<Classroom, $this>
     */
    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function coordinators(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
