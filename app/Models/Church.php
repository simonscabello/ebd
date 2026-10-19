<?php

namespace App\Models;

use Database\Factories\ChurchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Uma igreja (ou grupo) que usa o app: tem as suas classes e os seus
 * administradores. Professor e aluno continuam definidos por classe.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 */
#[Fillable(['name', 'slug', 'timezone'])]
class Church extends Model
{
    /** @use HasFactory<ChurchFactory> */
    use HasFactory;

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
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * A igreja mais antiga, sem criar nada. Enquanto só existe a igreja que
     * veio da configuração, é ela; quando houver cadastro de igrejas, a igreja
     * passa a vir de quem está logado ou da classe.
     */
    public static function primary(): ?self
    {
        return self::query()->orderBy('id')->first();
    }

    /**
     * Nome mostrado no app e nos e-mails. Só leitura: sem igreja cadastrada,
     * vale o da configuração.
     */
    public static function displayName(): string
    {
        return self::primary()->name ?? (string) config('ebd.church_name');
    }

    /**
     * Igual a primary(), mas cria a igreja da configuração se ainda não
     * existir nenhuma (instalação nova). Só para caminhos de escrita.
     */
    public static function primaryOrCreate(): self
    {
        if ($church = self::primary()) {
            return $church;
        }

        $name = (string) config('ebd.church_name');

        return self::query()->create([
            'name' => $name,
            'slug' => Str::slug($name) ?: 'igreja',
            'timezone' => (string) config('ebd.timezone'),
        ]);
    }
}
