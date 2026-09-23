<?php

namespace Database\Seeders;

use App\Enums\ClassroomRole;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuários de desenvolvimento. Senha de todos: "password".
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $jovens = Classroom::query()->where('slug', 'jovens')->firstOrFail();
        $adultos = Classroom::query()->where('slug', 'adultos')->firstOrFail();

        $admin = $this->user('admin@ebd.test', 'Coordenação da EBD');
        $admin->forceFill(['is_admin' => true])->save();

        $this->user('professor@ebd.test', 'Marcos Oliveira')
            ->classrooms()->syncWithoutDetaching([$jovens->id => ['role' => ClassroomRole::Teacher]]);

        $this->user('professora@ebd.test', 'Ana Souza')
            ->classrooms()->syncWithoutDetaching([$adultos->id => ['role' => ClassroomRole::Teacher]]);

        $this->user('aluno@ebd.test', 'João Pereira')
            ->classrooms()->syncWithoutDetaching([$jovens->id => ['role' => ClassroomRole::Student]]);

        $this->user('aluna@ebd.test', 'Maria Santos')
            ->classrooms()->syncWithoutDetaching([$adultos->id => ['role' => ClassroomRole::Student]]);

        // Alguns alunos a mais para a tela de membros não ficar vazia.
        foreach (['Lucas Almeida', 'Beatriz Costa', 'Rafael Lima'] as $index => $name) {
            $this->user('jovem'.($index + 1).'@ebd.test', $name)
                ->classrooms()->syncWithoutDetaching([$jovens->id => ['role' => ClassroomRole::Student]]);
        }
    }

    private function user(string $email, string $name): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
