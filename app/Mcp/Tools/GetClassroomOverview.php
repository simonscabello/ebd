<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\ClassMeeting;
use App\Queries\ClassroomInsightsQuery;
use App\Queries\CurrentLessonQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class GetClassroomOverview extends EbdTool
{
    protected string $name = 'get_classroom_overview';

    protected string $title = 'Panorama da classe';

    protected string $description = 'Lição da semana, indicadores de presença e estudo em casa, últimos encontros e alunos que precisam de atenção (faltas seguidas, dias sem leitura).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
        ];
    }

    public function handle(Request $request, CurrentLessonQuery $current, ClassroomInsightsQuery $insights): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $this->authorize($request, 'viewInsights', $classroom);

        $week = $current->for($classroom, $user);
        $data = $insights->for($classroom);

        return $this->json([
            'classroom' => Presenter::classroom($classroom),
            'lesson_of_the_week' => $week->meeting ? [
                'meeting' => Presenter::meeting($week->meeting),
                'lesson' => $week->lesson ? Presenter::lessonSummary($week->lesson) : null,
                'meeting_index' => $week->meetingIndex,
                'meeting_total' => $week->meetingTotal,
                'cancelled_before' => $week->cancelledBefore->map(fn (ClassMeeting $m) => $m->held_on->toDateString())->all(),
            ] : null,
            'kpis' => $data['kpis'],
            'recent_meetings' => $data['meetings'],
            'lessons_home_study' => $data['lessons'],
            'students_needing_attention' => collect($data['students'])->where('at_risk', true)->values()->all(),
            'thresholds' => $data['thresholds'],
            'insights_url' => route('admin.classrooms.insights', $classroom),
        ]);
    }
}
