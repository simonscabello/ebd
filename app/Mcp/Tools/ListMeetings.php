<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\ClassMeeting;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class ListMeetings extends EbdTool
{
    protected string $name = 'list_meetings';

    protected string $title = 'Agenda da classe';

    protected string $description = 'Mostra a agenda da classe: cada domingo, a lição que cai nele, situação (planned, held, cancelled), visitantes e, se pedido, quem esteve presente. Sem datas, mostra das últimas 6 semanas em diante.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'from' => $schema->string()->format('date')->description('Data inicial (AAAA-MM-DD).'),
            'to' => $schema->string()->format('date')->description('Data final (AAAA-MM-DD).'),
            'with_attendance' => $schema->boolean()->description('Incluir os nomes dos presentes em cada encontro.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->actor($request);
        $data = $request->validate([
            'classroom' => ['required'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'with_attendance' => ['nullable', 'boolean'],
        ]);

        $classroom = $this->resolveClassroom($user, $data['classroom']);
        $this->authorize($request, 'manageContent', $classroom);

        $from = $data['from'] ?? ChurchCalendar::today()->subWeeks(6)->toDateString();
        $withAttendance = (bool) ($data['with_attendance'] ?? false);

        $meetings = $classroom->meetings()
            ->whereDate('held_on', '>=', $from)
            ->when($data['to'] ?? null, fn ($q, $to) => $q->whereDate('held_on', '<=', $to))
            ->chronological()
            ->with(['lesson', ...($withAttendance ? ['attendances'] : [])])
            ->limit(60)
            ->get();

        $names = $withAttendance
            ? $classroom->members()->get()->mapWithKeys(fn (User $member) => [$member->id => $member->name])
            : collect();

        return $this->json([
            'classroom' => Presenter::classroom($classroom),
            'today' => ChurchCalendar::today()->toDateString(),
            'meetings' => $meetings->map(fn (ClassMeeting $m) => Presenter::meeting(
                $m,
                $withAttendance && $m->hasAttendance()
                    ? $m->attendances->map(fn ($a) => $names->get($a->user_id, "#{$a->user_id}"))->sort()->values()->all()
                    : null,
            ))->all(),
            'agenda_url' => route('admin.classrooms.meetings.index', $classroom),
        ]);
    }
}
