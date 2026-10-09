<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class GetLesson extends EbdTool
{
    protected string $name = 'get_lesson';

    protected string $title = 'Ver lição';

    protected string $description = 'Mostra uma lição inteira: dados, conteúdo em Markdown, leituras da semana, blocos (inclusive os do professor), materiais e os encontros em que ela cai.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Id da lição (veja list_lessons).')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->actor($request);
        $request->validate(['lesson_id' => ['required', 'integer']]);

        $lesson = $this->resolveLesson($user, $request->get('lesson_id'));
        $this->authorize($request, 'viewTeacherContent', $lesson);

        $lesson->load(['classroom', 'series', 'authors', 'readings', 'blocks', 'materials', 'meetings']);

        return $this->json(Presenter::lesson($lesson));
    }
}
