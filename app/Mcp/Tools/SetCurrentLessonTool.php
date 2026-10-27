<?php

namespace App\Mcp\Tools;

use App\Actions\Teaching\SetCurrentLesson;
use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class SetCurrentLessonTool extends EbdTool
{
    protected string $name = 'set_current_lesson';

    protected string $title = 'Escolher a lição atual';

    protected string $description = 'Define a lição que a classe estuda agora ("estudar esta agora"). Ela vai para o próximo encontro e fica quantos encontros precisar, até ser concluída (advance_lesson ou finish_meeting com continues=false). A série dela vira a série atual. Confirme com a pessoa antes.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'lesson_id' => $schema->integer()->description('Id da lição (list_lessons).')->required(),
        ];
    }

    public function handle(Request $request, SetCurrentLesson $set): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $this->authorize($request, 'manageContent', $classroom);
        $lesson = $this->resolveLesson($user, $request->get('lesson_id'));

        $this->audited($request, $classroom, $classroom->id, fn () => $set->handle($classroom, $lesson));

        return $this->json(['current_lesson' => Presenter::lessonSummary($lesson)]);
    }
}
