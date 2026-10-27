<?php

namespace App\Mcp\Tools;

use App\Actions\Meetings\FinishMeeting;
use App\Concerns\MeetingValidationRules;
use App\Mcp\Presenter;
use App\Models\Lesson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class FinishMeetingTool extends EbdTool
{
    use MeetingValidationRules;

    protected string $name = 'finish_meeting';

    protected string $title = 'Encerrar aula';

    protected string $description = 'Encerra a aula de um encontro (hoje ou passado): marca como realizado, guarda onde a turma parou (notes) e diz se a lição continua no próximo encontro (continues=true) ou terminou (continues=false: a classe passa para a próxima lição da série, como advance_lesson). Confirme com a pessoa se a lição terminou.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'meeting' => $schema->string()->description('Id do encontro ou a data (AAAA-MM-DD).')->required(),
            'continues' => $schema->boolean()->description('true se a lição continua no próximo encontro; false se terminou (passa para a próxima).')->required(),
            'notes' => $schema->string()->description('Onde a turma parou, observações da aula.'),
        ];
    }

    public function handle(Request $request, FinishMeeting $finish): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $meeting = $this->resolveMeeting($user, $request->get('meeting'), $classroom);
        $this->authorize($request, 'update', $meeting);

        $data = $request->validate($this->finishMeetingRules(), [], $this->meetingAttributes());

        /** @var Lesson|null $next */
        $next = $this->audited(
            $request,
            $meeting,
            $classroom->id,
            fn () => $finish->handle($meeting, (bool) $data['continues'], $data['notes'] ?? null),
        );

        return $this->json([
            'meeting' => Presenter::meeting($meeting->refresh()->load('lesson')),
            'current_lesson' => $next !== null ? Presenter::lessonSummary($next) : null,
        ]);
    }
}
