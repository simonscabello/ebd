<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\ClassMeeting;
use App\Queries\ClassroomOverviewQuery;
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

    protected string $title = 'Resumo da classe';

    protected string $description = 'O mesmo Resumo da tela da classe: lição da semana, frequência no período (série atual ou últimos 3 meses), últimos domingos com chamada, domingos pendentes de confirmação, estudo em casa da lição atual, alunos que precisam de atenção (faltas seguidas, dias sem leitura) e aniversariantes do mês.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
        ];
    }

    public function handle(Request $request, CurrentLessonQuery $current, ClassroomOverviewQuery $overview): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $this->authorize($request, 'viewInsights', $classroom);

        $week = $current->for($classroom, $user);
        $data = $overview->for($classroom, $user);
        $stats = $data['stats'];

        return $this->json([
            'classroom' => Presenter::classroom($classroom),
            'lesson_of_the_week' => $week->meeting ? [
                'meeting' => Presenter::meeting($week->meeting),
                'lesson' => $week->lesson ? Presenter::lessonSummary($week->lesson) : null,
                'meeting_index' => $week->meetingIndex,
                'meeting_total' => $week->meetingTotal,
                'cancelled_before' => $week->cancelledBefore->map(fn (ClassMeeting $m) => $m->held_on->toDateString())->all(),
            ] : null,
            'kpis' => [
                'students' => $stats['students'],
                'frequency_rate' => $stats['frequency']['rate'],
                'frequency_period' => $stats['period']['label'],
                'home_study_rate' => $data['home_study']['rate'] ?? null,
                'needing_attention' => count($data['attention']),
            ],
            'recent_meetings' => $data['recent'],
            'pending_meetings' => $data['pending'],
            'lessons_home_study' => $data['home_study'] !== null ? [$data['home_study']] : [],
            'students_needing_attention' => $data['attention'],
            'birthdays_this_month' => $data['birthdays'],
            'thresholds' => $data['thresholds'],
            'classroom_url' => route('admin.classrooms.show', $classroom),
        ]);
    }
}
