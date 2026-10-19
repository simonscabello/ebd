<?php

namespace App\Actions\Churches;

use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria uma igreja (ou grupo) com quem a criou como administrador e o
 * primeiro programa de ensino. Quem cria administra só esta igreja; o dono
 * do sistema continua sendo só o Simon.
 */
class CreateChurchWithAdmin
{
    public const DEFAULT_PROGRAM = 'Escola Bíblica Dominical';

    public function handle(User $user, string $churchName, ?string $programName = null): Church
    {
        return DB::transaction(function () use ($user, $churchName, $programName) {
            $name = trim($churchName);

            $church = Church::query()->create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'timezone' => (string) config('ebd.timezone'),
            ]);

            $church->admins()->attach($user->id);

            $program = trim((string) $programName) ?: self::DEFAULT_PROGRAM;
            $church->programs()->create([
                'name' => $program,
                // "EBD" é o nome que todo mundo usa; os outros, o próprio nome.
                'short_name' => $program === self::DEFAULT_PROGRAM ? 'EBD' : Str::limit($program, 30, ''),
                'slug' => Str::slug($program) ?: 'programa',
            ]);

            $user->flushClassroomRoles();

            return $church;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'igreja';
        $slug = $base;

        for ($suffix = 2; Church::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
