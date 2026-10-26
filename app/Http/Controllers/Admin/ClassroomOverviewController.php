<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\FillSundays;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Queries\AttendanceBookQuery;
use App\Queries\ClassroomOverviewQuery;
use App\Queries\Data\Period;
use App\Queries\MeetingRows;
use App\Support\ChurchCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página da classe: a linha do tempo dos domingos, com o próximo em destaque,
 * e os alunos que pedem atenção.
 */
class ClassroomOverviewController extends Controller
{
    /** Quantos domingos à frente a linha do tempo mostra. */
    private const NEXT_SUNDAYS = 8;

    public function __invoke(
        Request $request,
        Classroom $classroom,
        ClassroomOverviewQuery $overview,
        AttendanceBookQuery $books,
        MeetingRows $rows,
        FillSundays $fill,
    ): Response {
        Gate::authorize('viewInsights', $classroom);

        return Inertia::render('admin/classrooms/show', [
            'classroom' => ClassroomResource::make($classroom),
            'overview' => $overview->for($classroom, $request->user()),
            // Lições marcadas, com a mensagem pronta para o grupo.
            'upcoming' => fn () => $overview->upcoming($classroom, $request->user(), self::NEXT_SUNDAYS),
            'sundays' => function () use ($classroom, $books, $rows, $fill) {
                $fill->handle($classroom);
                $today = ChurchCalendar::todayString();
                $meetings = $classroom->meetings()
                    ->whereDate('held_on', '>=', $today)
                    ->chronological()
                    ->with('lesson')
                    ->limit(self::NEXT_SUNDAYS)
                    ->get();

                return $rows->for($classroom, $meetings, $books->for($classroom, new Period($today, $today, '')), $today);
            },
            'canEdit' => $request->user()->can('update', $classroom),
        ]);
    }
}
