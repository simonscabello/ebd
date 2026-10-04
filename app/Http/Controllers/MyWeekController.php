<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClassroomResource;
use App\Models\User;
use App\Queries\StudyWeekQuery;
use App\Support\ClassroomSelector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Leituras da semana": a lista dos dias da semana de estudo, com a leitura de
 * cada um para ler e marcar. O resto da semana (leitura de hoje, progresso,
 * conteúdo do dia) fica no Início.
 */
class MyWeekController extends Controller
{
    public function __invoke(Request $request, ClassroomSelector $selector, StudyWeekQuery $week): Response
    {
        /** @var User $user */
        $user = $request->user();

        $classrooms = $selector->available($user, onlyMine: true);
        $classroom = $selector->selected($classrooms, $request->query('classe'));

        return Inertia::render('my-week', [
            'classrooms' => ClassroomResource::collection($classrooms),
            'classroom' => $classroom ? ClassroomResource::make($classroom) : null,
            'week' => $classroom ? $week->for($user, $classroom, $request) : null,
        ]);
    }
}
