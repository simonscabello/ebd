<?php

namespace App\Http\Middleware;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\User;
use App\Support\ChurchCalendar;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Hoje" no fuso da igreja certa: a da classe, lição, encontro ou série da
 * rota; sem nenhum deles, a igreja atual de quem está logado. Visitante sem
 * contexto fica no fuso da configuração. Precisa rodar depois do route model
 * binding (SubstituteBindings).
 */
class UseChurchTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        return ChurchCalendar::using(self::churchFor($request)?->timezone, fn () => $next($request));
    }

    public static function churchFor(Request $request): ?Church
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($church = self::churchOf($parameter)) {
                return $church;
            }
        }

        $user = $request->user();

        return $user instanceof User ? $user->currentChurch() : null;
    }

    private static function churchOf(mixed $model): ?Church
    {
        return match (true) {
            $model instanceof Church => $model,
            $model instanceof Classroom, $model instanceof Program => $model->church,
            $model instanceof Model && method_exists($model, 'classroom') => $model->getRelationValue('classroom')?->church,
            $model instanceof Model && method_exists($model, 'lesson') => $model->getRelationValue('lesson')?->classroom?->church,
            default => null,
        };
    }
}
