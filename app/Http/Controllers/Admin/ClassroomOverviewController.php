<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\FillSundays;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\SeriesResource;
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
 * Página da classe: a agenda. Os próximos domingos e os que já foram, cada um
 * com a ação que falta (apresentar, terminar a lição, fazer a chamada, falar
 * com quem faltou).
 */
class ClassroomOverviewController extends Controller
{
    /** Quantos domingos à frente a linha do tempo mostra. */
    private const NEXT_SUNDAYS = 8;

    /** Quantas semanas para trás a agenda mostra; o resto fica em "Datas anteriores". */
    private const PAST_WEEKS = 6;

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
            'past' => function () use ($classroom, $books, $rows) {
                $today = ChurchCalendar::today();
                $from = $today->subWeeks(self::PAST_WEEKS)->toDateString();
                $meetings = $classroom->meetings()
                    ->whereDate('held_on', '<', $today->toDateString())
                    ->whereDate('held_on', '>=', $from)
                    ->orderByDesc('held_on')
                    ->with('lesson')
                    ->get();

                return $rows->for($classroom, $meetings, $books->for($classroom, new Period($from, $today->toDateString(), '')), $today->toDateString());
            },
            'nextSunday' => ChurchCalendar::nextMeetingDay($classroom->meeting_weekday)->toDateString(),
            'series' => fn () => SeriesResource::collection($classroom->series()->orderByDesc('starts_on')->get()),
            'canEdit' => $request->user()->can('update', $classroom),
        ]);
    }
}
