<?php

namespace App\Mcp\Tools;

use App\Actions\Meetings\PlanMeetings;
use App\Concerns\MeetingValidationRules;
use App\Mcp\Presenter;
use App\Models\ClassMeeting;
use App\Models\Series;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class PlanMeetingsTool extends EbdTool
{
    use MeetingValidationRules;

    protected string $name = 'plan_meetings';

    protected string $title = 'Planejar a agenda';

    protected string $description = 'Cria um encontro para cada domingo do período (os que já existem ficam como estão) e, se informar a série, distribui nos domingos livres as lições da série que ainda não têm data, na ordem do número. Até um ano por vez.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'from' => $schema->string()->format('date')->description('Primeiro dia do período (AAAA-MM-DD).')->required(),
            'to' => $schema->string()->format('date')->description('Último dia do período (AAAA-MM-DD).')->required(),
            'series' => $schema->string()->description('Slug ou id da série cujas lições serão distribuídas (opcional).'),
        ];
    }

    public function handle(Request $request, PlanMeetings $plan): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $this->authorize($request, 'manageContent', $classroom);

        $series = filled($request->get('series')) ? $this->resolveSeries($classroom, $request->get('series')) : null;
        $request->merge(['series_id' => $series?->id]);

        $data = $request->validate([...$this->meetingPlanRules($classroom, $request->get('from')), 'classroom' => ['required'], 'series' => ['nullable']], [], $this->meetingAttributes());
        $from = CarbonImmutable::parse($data['from']);
        $to = CarbonImmutable::parse($data['to']);

        $result = $this->audited($request, null, $classroom->id, fn () => $plan->handle($classroom, $from, $to, $series));

        $meetings = $classroom->meetings()
            ->whereBetween('held_on', [$from->toDateString(), $to->toDateString()])
            ->chronological()
            ->with('lesson')
            ->get();

        return $this->json([
            'created' => $result['created'],
            'lessons_assigned' => $result['assigned'],
            'series' => $series instanceof Series ? Presenter::series($series) : null,
            'meetings' => $meetings->map(fn (ClassMeeting $m) => Presenter::meeting($m))->all(),
        ]);
    }
}
