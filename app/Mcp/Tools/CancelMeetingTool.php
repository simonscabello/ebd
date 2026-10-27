<?php

namespace App\Mcp\Tools;

use App\Actions\Meetings\CancelMeeting;
use App\Concerns\MeetingValidationRules;
use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class CancelMeetingTool extends EbdTool
{
    use MeetingValidationRules;

    protected string $name = 'cancel_meeting';

    protected string $title = 'Marcar "sem aula"';

    protected string $description = 'Marca um encontro como sem aula ("sem EBD", "sem Discipulado"): feriado, evento da igreja. A lição atual da classe segue para o próximo encontro. Não vale para encontro que já tem chamada. Confirme com a pessoa antes.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'meeting' => $schema->string()->description('Id do encontro ou a data (AAAA-MM-DD).')->required(),
            'reason' => $schema->string()->description('Motivo, ex.: "Feriado", "Congresso".'),
        ];
    }

    public function handle(Request $request, CancelMeeting $cancel): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $meeting = $this->resolveMeeting($user, $request->get('meeting'), $classroom);
        $this->authorize($request, 'update', $meeting);

        $data = $request->validate($this->cancelMeetingRules(), [], $this->meetingAttributes());

        $this->audited(
            $request,
            $meeting,
            $classroom->id,
            fn () => $cancel->handle($meeting, $data['reason'] ?? null),
        );

        return $this->json([
            'meeting' => Presenter::meeting($meeting->refresh()->load('lesson')),
        ]);
    }
}
