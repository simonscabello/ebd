<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Queries\StudentProgressQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class GetStudentProgress extends EbdTool
{
    protected string $name = 'get_student_progress';

    protected string $title = 'Progresso do aluno';

    protected string $description = 'Progresso de um aluno na classe: presença e dias de leitura em casa por lição, sequência de estudo e selos. Nunca inclui as anotações pessoais do aluno.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'student' => $schema->string()->description('Nome ou id do aluno.')->required(),
        ];
    }

    public function handle(Request $request, StudentProgressQuery $progress): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $student = $this->resolveStudent($classroom, $request->get('student'));
        $this->authorize($request, 'viewStudentProgress', $classroom, $student);

        return $this->json([
            'classroom' => Presenter::classroom($classroom),
            'student' => Presenter::student($student),
            ...$progress->for($student, $classroom),
            'student_url' => route('admin.classrooms.students.show', [$classroom, $student]),
        ]);
    }
}
