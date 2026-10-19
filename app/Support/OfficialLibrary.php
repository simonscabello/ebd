<?php

namespace App\Support;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A biblioteca oficial: a organização do dono do sistema onde ficam os
 * estudos oficiais (turma "Estudos oficiais"). Criada quando for usada pela
 * primeira vez, com os donos do sistema como administradores.
 */
final class OfficialLibrary
{
    public static function church(): Church
    {
        return DB::transaction(function () {
            $church = Church::query()->where('is_official', true)->first();

            if ($church === null) {
                $name = 'Biblioteca '.config('brand.name');
                $church = Church::query()->forceCreate([
                    'name' => $name,
                    'slug' => self::freeSlug('churches', Str::slug($name)),
                    'timezone' => (string) config('ebd.timezone'),
                    'is_official' => true,
                ]);
                $church->admins()->syncWithoutDetaching(User::query()->where('is_admin', true)->pluck('id')->all());
            }

            return $church;
        });
    }

    public static function classroom(): Classroom
    {
        $church = self::church();

        $classroom = Classroom::query()->where('church_id', $church->id)->orderBy('id')->first();

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
    }

    public static function isOfficial(Classroom $classroom): bool
    {
        return (bool) $classroom->church->is_official;
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
