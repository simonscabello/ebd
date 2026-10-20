<?php

namespace App\Support;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A biblioteca oficial: os estudos do dono do sistema, oferecidos por cópia
 * às igrejas. Para quem usa o app ela é uma área do dono (/dono/biblioteca),
 * não uma turma: fica fora das listas de turmas, do início e da agenda. Por
 * baixo, os estudos moram numa igreja própria (is_official) com uma única
 * turma interna, só para reaproveitar séries, lições e materiais. Essa turma
 * não se reúne (Classroom::holdsMeetings).
 *
 * Criada quando for usada pela primeira vez (inclusive numa página aberta
 * por GET), com os donos do sistema como administradores. Dois acessos ao
 * mesmo tempo não quebram: quem perde a corrida relê o que o outro criou.
 */
final class OfficialLibrary
{
    public static function church(): Church
    {
        $church = Church::query()->where('is_official', true)->first();

        if ($church !== null) {
            return $church;
        }

        try {
            // Transação própria (savepoint, se já houver uma): a violação do
            // índice único não estraga a transação de quem chamou.
            return DB::transaction(function () {
                $name = 'Biblioteca '.config('brand.name');
                $church = Church::query()->forceCreate([
                    'name' => $name,
                    'slug' => self::freeSlug('churches', Str::slug($name)),
                    'timezone' => (string) config('ebd.timezone'),
                    'is_official' => true,
                ]);
                $church->admins()->syncWithoutDetaching(User::query()->where('is_admin', true)->pluck('id')->all());

                return $church;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Outro acesso criou a igreja (churches_one_official) ou pegou o slug antes.
            return Church::query()->where('is_official', true)->first() ?? throw $e;
        }
    }

    /**
     * A turma interna onde ficam as séries oficiais.
     */
    public static function classroom(): Classroom
    {
        $church = self::church();

        $classroom = self::firstClassroom($church);

        if ($classroom !== null) {
            return $classroom;
        }

        return DB::transaction(function () use ($church) {
            // Trava a igreja da biblioteca: dois acessos ao mesmo tempo não
            // criam duas turmas nem dois programas.
            Church::query()->whereKey($church->id)->lockForUpdate()->first();

            $classroom = self::firstClassroom($church);

            if ($classroom !== null) {
                return $classroom;
            }

            $program = $church->programs()->first()
                ?? $church->programs()->create(['name' => 'Acervo', 'short_name' => 'Acervo', 'slug' => 'acervo']);

            $classroom = new Classroom(['name' => 'Estudos oficiais']);
            $classroom->forceFill([
                'church_id' => $church->id,
                'program_id' => $program->id,
                'slug' => self::freeSlug('classrooms', 'estudos-oficiais'),
            ])->save();

            return $classroom;
        });
    }

    public static function isOfficial(Classroom $classroom): bool
    {
        return (bool) $classroom->church->is_official;
    }

    private static function firstClassroom(Church $church): ?Classroom
    {
        return Classroom::query()->where('church_id', $church->id)->orderBy('id')->first();
    }

    private static function freeSlug(string $table, string $base): string
    {
        $slug = $base;

        for ($n = 2; DB::table($table)->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
