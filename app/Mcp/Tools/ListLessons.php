<?php

namespace App\Mcp\Tools;

use App\Enums\LessonStatus;
use App\Mcp\Presenter;
use App\Models\Lesson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class ListLessons extends EbdTool
{
    protected string $name = 'list_lessons';

    protected string $title = 'Listar lições';

    protected string $description = 'Lista as lições de uma classe (rascunhos e publicadas), com filtro por série, situação e busca no título. Para ver o conteúdo completo use get_lesson.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'series' => $schema->string()->description('Slug ou id da série (opcional).'),
            'status' => $schema->string()->enum(LessonStatus::class)->description('draft (rascunho) ou published (publicada).'),
            'q' => $schema->string()->description('Parte do título.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->actor($request);
        $filters = $request->validate([
            'classroom' => ['required'],
            'series' => ['nullable'],
            'status' => ['nullable', Rule::enum(LessonStatus::class)],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $classroom = $this->resolveClassroom($user, $filters['classroom']);
        $this->authorize($request, 'manageContent', $classroom);
        $series = isset($filters['series']) ? $this->resolveSeries($classroom, $filters['series']) : null;

        $lessons = Lesson::query()
            ->whereBelongsTo($classroom)
            ->when($series, fn ($q) => $q->whereBelongsTo($series))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereRaw('unaccent(title) ILIKE unaccent(?)', ['%'.addcslashes($term, '%_\\').'%']))
            ->with(['classroom', 'series'])
            ->orderByRaw('scheduled_for IS NULL DESC, scheduled_for DESC')
            ->orderByRaw('number IS NULL, number')
            ->limit(100)
            ->get();

        return $this->json([
            'classroom' => Presenter::classroom($classroom),
            'count' => $lessons->count(),
            'lessons' => $lessons->map(fn (Lesson $l) => Presenter::lessonSummary($l))->all(),
        ]);
    }
}
