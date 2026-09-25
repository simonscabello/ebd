<?php

namespace App\Mcp\Tools;

use App\Actions\Meetings\RecordAttendance;
use App\Concerns\MeetingValidationRules;
use App\Mcp\Presenter;
use App\Models\ClassMeeting;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class RecordAttendanceTool extends EbdTool
{
    use MeetingValidationRules;

    protected string $name = 'record_attendance';

    protected string $title = 'Registrar presença';

    protected string $description = <<<'TEXT'
        Registra a chamada de um encontro (hoje ou já passado) e o número de visitantes.
        mode "add" soma os nomes informados aos que já estão presentes; mode "set" troca a lista inteira
        (quem não estiver na lista fica como ausente). Na dúvida use "add".
        Alunos podem ser informados pelo nome (ou parte dele) ou pelo id. A resposta traz presentes e ausentes:
        mostre à pessoa para conferir.
        TEXT;

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'meeting' => $schema->string()->description('Id do encontro ou a data (AAAA-MM-DD).')->required(),
            'mode' => $schema->string()->enum(['add', 'set'])->description('add = acrescenta aos presentes; set = substitui a lista inteira.')->required(),
            'present' => $schema->array()->items($schema->string())->description('Nomes ou ids dos alunos presentes.')->required(),
            'visitors' => $schema->integer()->description('Número de visitantes. Omitido, mantém o atual.'),
        ];
    }

    public function handle(Request $request, RecordAttendance $record): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $meeting = $this->resolveMeeting($user, $request->get('meeting'), $classroom);
        $this->authorize($request, 'takeAttendance', $meeting);

        $data = $request->validate([
            'mode' => ['required', 'in:add,set'],
            'present' => ['present', 'array', 'max:500'],
            'visitors' => $this->attendanceRules()['visitors'],
        ], [], $this->meetingAttributes());

        $ids = $this->resolveStudents($classroom, $data['present'], 'present')->map(fn (User $student): int => $student->id)->all();

        if ($data['mode'] === 'add') {
            $ids = [...$meeting->attendances()->pluck('user_id')->map(fn ($id): int => (int) $id)->all(), ...$ids];
        }

        $ids = array_values(array_unique($ids));

        $visitors = (int) ($data['visitors'] ?? $meeting->visitors_count);

        $this->audited(
            $request,
            $meeting,
            $classroom->id,
            fn () => $record->handle($meeting, $ids, $visitors, $user),
            fn (ClassMeeting $m) => $this->snapshot($m),
        );

        $meeting->refresh()->load('lesson');
        $presentIds = $meeting->attendances()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        [$present, $absent] = $classroom->students()->orderBy('name')->get()->partition(fn (User $s) => in_array($s->id, $presentIds, true));

        return $this->json([
            'meeting' => Presenter::meeting($meeting),
            'present' => $present->pluck('name')->values()->all(),
            'absent' => $absent->pluck('name')->values()->all(),
            'visitors' => $meeting->visitors_count,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ClassMeeting $meeting): array
    {
        return [
            'present' => User::query()->whereIn('id', $meeting->attendances()->pluck('user_id'))->orderBy('name')->pluck('name')->all(),
            'visitors_count' => $meeting->visitors_count,
            'status' => $meeting->status->value,
        ];
    }
}
