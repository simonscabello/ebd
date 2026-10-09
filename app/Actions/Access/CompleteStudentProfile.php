<?php

namespace App\Actions\Access;

use App\Actions\Classrooms\CreateManagedStudent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Salva o cadastro que o aluno completa no primeiro acesso. Com senha própria,
 * o link pessoal deixa de valer: daqui em diante a pessoa entra com e-mail e
 * senha, e quem esquecer a senha pede um link novo ao professor.
 */
class CompleteStudentProfile
{
    /**
     * @param  array{name: string, email: string, phone: string, birth_date: string, gender: string, password?: string}  $data
     */
    public function handle(User $user, array $data): void
    {
        $createsPassword = isset($data['password']);

        DB::transaction(function () use ($user, $data, $createsPassword) {
            $user->fill([
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'phone' => CreateManagedStudent::normalizePhone($data['phone']),
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
            ]);

            if ($createsPassword) {
                $user->password = $data['password'];
            }

            $user->save();

            if (! $user->isManaged()) {
                $user->accessLinks()->active()->update(['revoked_at' => now()]);
            }
        });

        // O cookie "lembrar de mim" guarda um resumo da senha: com a senha nova,
        // o login é refeito para o aparelho continuar lembrando da pessoa.
        if ($createsPassword) {
            $guard = Auth::guard('web');

            if (method_exists($guard, 'setRememberDuration')) {
                $guard->setRememberDuration((int) config('ebd.access_links.remember_days') * 24 * 60);
            }

            $guard->login($user, remember: true);
            session()->regenerate();
        }
    }
}
