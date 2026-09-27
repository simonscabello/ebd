<?php

namespace Database\Seeders;

use App\Actions\Classrooms\CreateManagedStudent;
use App\Enums\ClassroomRole;
use App\Enums\Gender;
use App\Models\Classroom;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Database\Seeder;

/**
 * Usuários de desenvolvimento. Senha de todos: "password".
 *
 * Os alunos já vêm com o cadastro completo (WhatsApp, nascimento e gênero);
 * sem isso, cairiam na tela "Completar cadastro" a cada login. A "Aluna do
 * link" é a exceção: sem senha, para testar o link pessoal e o cadastro.
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

        // Um aniversariante hoje, para o Resumo da classe.
        $birthdayToday = ChurchCalendar::today()->subYears(21)->toDateString();

        $this->student('aluno@ebd.test', 'João Pereira', Gender::Male, $birthdayToday, '5527999990001')
            ->classrooms()->syncWithoutDetaching([$jovens->id => ['role' => ClassroomRole::Student]]);

        $this->student('aluna@ebd.test', 'Maria Santos', Gender::Female, '1988-03-12', '5527999990002')
            ->classrooms()->syncWithoutDetaching([$adultos->id => ['role' => ClassroomRole::Student]]);

        // Alguns alunos a mais para a lista da classe não ficar vazia.
        $jovem = [
            ['Lucas Almeida', Gender::Male, '2004-06-02'],
            ['Beatriz Costa', Gender::Female, '2003-11-20'],
            ['Rafael Lima', Gender::Male, '2006-01-15'],
        ];

        foreach ($jovem as $index => [$name, $gender, $birthDate]) {
            $this->student('jovem'.($index + 1).'@ebd.test', $name, $gender, $birthDate, '552799999100'.($index + 1))
                ->classrooms()->syncWithoutDetaching([$jovens->id => ['role' => ClassroomRole::Student]]);
        }

        // Entra só pelo link pessoal (gere o link em Alunos) e completa o cadastro.
        if (! $jovens->students()->where('name', 'Aluna do link')->exists()) {
            app(CreateManagedStudent::class)->createAccount($jovens, 'Aluna do link', '5527999992000');
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

    private function student(string $email, string $name, Gender $gender, string $birthDate, string $phone): User
    {
        $user = $this->user($email, $name);
        $user->forceFill(['gender' => $gender, 'birth_date' => $birthDate, 'phone' => $phone])->save();

        return $user;
    }
}
