<?php

namespace App\Mcp\Tools;

use App\Enums\MeetingStatus;
use App\Mcp\Presenter;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Series;
use App\Support\ChurchCalendar;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class ListClassrooms extends EbdTool
{
    protected string $name = 'list_classrooms';

    protected string $title = 'Listar classes';

    protected string $description = 'Lista as classes que você gerencia, com séries, número de alunos e o próximo encontro. Comece por aqui: o slug da classe é usado pelas outras ferramentas.';

    public function handle(Request $request): Response
    {
        $user = $this->actor($request);
        $today = ChurchCalendar::today();

        $classrooms = $this->manageableClassrooms($user)
            ->withCount('students')
            ->with(['series' => fn ($q) => $q->orderByDesc('starts_on')])
            ->get();

        $nextMeetings = ClassMeeting::query()
            ->whereIn('classroom_id', $classrooms->modelKeys())
            ->where('status', '!=', MeetingStatus::Cancelled)
            ->fromDate($today)
            ->chronological()
            ->with('lesson')
            ->get()
            ->unique('classroom_id')
            ->keyBy('classroom_id');

        return $this->json([
            'you' => ['name' => $user->name, 'is_admin' => $user->isAdmin()],
            'today' => $today->toDateString(),
            'classrooms' => $classrooms->map(fn (Classroom $classroom) => [
                ...Presenter::classroom($classroom),
                'students_count' => (int) $classroom->getAttribute('students_count'),
                'series' => $classroom->series->map(fn (Series $s) => Presenter::series($s))->all(),
                'next_meeting' => ($meeting = $nextMeetings->get($classroom->id)) ? Presenter::meeting($meeting) : null,
            ])->all(),
        ]);
    }
}
