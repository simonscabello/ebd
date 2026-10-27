<?php

namespace App\Mcp\Tools;

use App\Actions\Teaching\AdvanceLesson;
use App\Mcp\Presenter;
use App\Models\Lesson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class AdvanceLessonTool extends EbdTool
{
    protected string $name = 'advance_lesson';

    protected string $title = 'Concluir a lição e passar para a próxima';

    protected string $description = 'Conclui a lição atual da classe e passa para a próxima não estudada da série. Informe o id da lição atual (get_classroom_overview): se ela já não for a atual, nada muda. Sem próxima, a classe fica sem lição atual. Confirme com a pessoa antes.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'lesson_id' => $schema->integer()->description('Id da lição atual, a que terminou.')->required(),
        ];
    }

    public function handle(Request $request, AdvanceLesson $advance): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $this->authorize($request, 'manageContent', $classroom);
        $lesson = $this->resolveLesson($user, $request->get('lesson_id'));

        /** @var Lesson|null $next */
        $next = $this->audited($request, $classroom, $classroom->id, fn () => $advance->handle($classroom, $lesson->id));

        return $this->json(['current_lesson' => $next !== null ? Presenter::lessonSummary($next) : null]);
    }
}
