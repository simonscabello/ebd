<?php

namespace App\Mcp\Tools;

use App\Actions\Meetings\SaveMeeting;
use App\Concerns\MeetingValidationRules;
use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class SaveMeetingTool extends EbdTool
{
    use MeetingValidationRules;

    protected string $name = 'save_meeting';

    protected string $title = 'Salvar encontro';

    protected string $description = 'Adiciona um domingo à agenda ou edita um encontro (meeting): data, lição do dia, título (ex.: "Culto de missões") e anotações. Na edição, campos omitidos ficam como estão; lesson_id null tira a lição do encontro. Para marcar "sem EBD" use cancel_meeting.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'meeting' => $schema->string()->description('Encontro a editar: id ou data atual (AAAA-MM-DD). Omita para adicionar.'),
            'held_on' => $schema->string()->format('date')->description('Data do encontro (AAAA-MM-DD). Obrigatória ao adicionar.'),
            'lesson_id' => $schema->integer()->nullable()->description('Id da lição deste encontro (list_lessons), ou null para deixar sem lição.'),
            'title' => $schema->string()->description('Título do encontro, quando não é uma lição comum.'),
            'notes' => $schema->string()->description('Anotações do professor sobre o encontro.'),
        ];
    }

    public function handle(Request $request, SaveMeeting $save): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $meeting = filled($request->get('meeting')) ? $this->resolveMeeting($user, $request->get('meeting'), $classroom) : null;

        if ($meeting !== null) {
            $this->authorize($request, 'update', $meeting);
            $this->keepCurrentValues($request, [
                'held_on' => $meeting->held_on->toDateString(),
                'lesson_id' => $meeting->lesson_id,
                'title' => $meeting->title,
                'notes' => $meeting->notes,
            ]);
        } else {
            $this->authorize($request, 'manageContent', $classroom);
        }

        $rules = $this->meetingRules($classroom, $meeting);
        $data = $request->validate([...$rules, 'classroom' => ['required'], 'meeting' => ['nullable']], $this->meetingMessages(), $this->meetingAttributes());

        $meeting = $this->auditedSave(
            $request,
            $meeting,
            $classroom->id,
            fn () => $save->handle($classroom, Arr::only($data, array_keys($rules)), $meeting),
        );

        return $this->json([
            'saved' => filled($request->get('meeting')) ? 'updated' : 'created',
            'meeting' => Presenter::meeting($meeting->fresh()->load('lesson')),
        ]);
    }
}
