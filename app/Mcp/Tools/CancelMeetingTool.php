<?php

namespace App\Mcp\Tools;

use App\Actions\Meetings\CancelMeeting;
use App\Concerns\MeetingValidationRules;
use App\Mcp\Presenter;
use App\Models\Lesson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class CancelMeetingTool extends EbdTool
{
    use MeetingValidationRules;

    protected string $name = 'cancel_meeting';

    protected string $title = 'Marcar "sem EBD"';

    protected string $description = 'Marca um domingo como "sem EBD" (feriado, evento da igreja). Com shift_lessons (padrão), a lição do dia e as seguintes são empurradas para os próximos encontros. Não vale para encontro que já tem chamada. Confirme com a pessoa antes.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'meeting' => $schema->string()->description('Id do encontro ou a data (AAAA-MM-DD).')->required(),
            'reason' => $schema->string()->description('Motivo, ex.: "Feriado", "Congresso".'),
            'shift_lessons' => $schema->boolean()->description('Empurrar as lições para os próximos domingos (padrão: true).'),
        ];
    }

    public function handle(Request $request, CancelMeeting $cancel): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $meeting = $this->resolveMeeting($user, $request->get('meeting'), $classroom);
        $this->authorize($request, 'update', $meeting);

        $request->merge(['shift' => $request->get('shift_lessons', true)]);
        $data = $request->validate($this->cancelMeetingRules(), [], $this->meetingAttributes());

        $leftover = $this->audited(
            $request,
            $meeting,
            $classroom->id,
            fn () => $cancel->handle($meeting, $data['reason'] ?? null, (bool) $data['shift']),
        );

        $lesson = $leftover !== null ? Lesson::query()->find($leftover) : null;

        return $this->json([
            'meeting' => Presenter::meeting($meeting->refresh()->load('lesson')),
            'warning' => $lesson ? "\"{$lesson->displayTitle()}\" ficou sem data. Adicione um domingo na agenda (save_meeting)." : null,
        ]);
    }
}
