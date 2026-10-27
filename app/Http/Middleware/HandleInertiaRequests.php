<?php

namespace App\Http\Middleware;

use App\Models\Church;
use App\Models\Classroom;
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
            'name' => config('brand.name'),
            'church' => [
                'name' => Church::displayName($user),
                // Quem administra a igreja atual edita os dados dela; com duas
                // ou mais, escolhe entre elas no cabeçalho da gestão.
                'can_edit' => $user !== null && $user->churchChoices()->isNotEmpty(),
                'current_id' => $user?->currentChurch()?->id,
                'choices' => $user !== null && $user->churchChoices()->count() > 1
                    ? $user->churchChoices()->map(fn (Church $church) => ['id' => $church->id, 'name' => $church->name])->values()->all()
                    : [],
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => strtok($user->name, ' '),
                    'email' => $user->email,
                    'avatar_url' => $user->avatarUrl(),
                    'has_password' => ! $user->isManaged(),
                    // "Admin" na interface = administra a igreja.
                    'is_admin' => $user->administersAnyChurch(),
                    'can_access_admin' => $user->canAccessAdmin(),
                    // Dono do sistema: vê o painel das igrejas.
                    'is_owner' => $user->isAdmin(),
                    'is_student' => $user->isStudentAnywhere(),
                    // Aluno ou professor de ao menos uma classe: tem semana de estudo e "Meu progresso".
                    'is_member' => $user->memberClassroomIds() !== [],
                ] : null,
            ],
            // Menu lateral da gestão: as classes que a pessoa gerencia.
            'adminClassrooms' => fn () => $user !== null && $request->routeIs('admin.*', 'owner.*')
                ? Classroom::query()->whereIn('id', $user->listedClassroomIds())->active()->ordered()
                    ->get(['id', 'slug', 'name'])
                    ->map(fn (Classroom $classroom) => ['slug' => $classroom->slug, 'name' => $classroom->name])
                    ->all()
                : [],
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
