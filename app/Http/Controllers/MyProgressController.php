<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClassroomResource;
use App\Models\User;
use App\Queries\StudentProgressQuery;
use App\Support\ClassroomSelector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Meu progresso": sequência, selos e histórico de estudo, só da própria pessoa.
 */
class MyProgressController extends Controller
{
    public function __invoke(Request $request, ClassroomSelector $selector, StudentProgressQuery $progress): Response
    {
        /** @var User $user */
        $user = $request->user();

        $classrooms = $selector->available($user, onlyMine: true);
        $classroom = $selector->selected($classrooms, $request->query('classe'));

        return Inertia::render('my-progress', [
            'classrooms' => ClassroomResource::collection($classrooms),
            'classroom' => $classroom ? ClassroomResource::make($classroom) : null,
            'progress' => $classroom ? $progress->for($user, $classroom) : null,
        ]);
    }
}
