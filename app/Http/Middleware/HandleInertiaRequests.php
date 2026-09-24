<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Fortify\Features;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props compartilhadas com todas as páginas. Exponha apenas o necessário:
     * tudo aqui vai para o HTML de qualquer página, inclusive as públicas.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'church' => [
                'name' => config('ebd.church_name'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => strtok($user->name, ' '),
                    'email' => $user->email,
                    'avatar_url' => $user->avatarUrl(),
                    'has_password' => ! $user->isManaged(),
                    'is_admin' => $user->isAdmin(),
                    'can_access_admin' => $user->canAccessAdmin(),
                    // Aluno de ao menos uma classe: mostra "Minha semana" na navegação.
                    'is_student' => array_filter(
                        $user->memberClassroomIds(),
                        fn (int $id) => ! $user->isTeacherOf($id),
                    ) !== [],
                ] : null,
            ],
            'features' => [
                'registration' => Features::enabled(Features::registration()),
            ],
            // Chave pública VAPID: o navegador precisa dela para se inscrever nos lembretes.
            'push' => [
                'public_key' => config('ebd.push.public_key') ?: null,
            ],
        ];
    }
}
