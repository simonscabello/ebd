<?php

namespace App\Mcp\Tools;

use App\Actions\Classrooms\MoveStudentToClassroom;
use App\Mcp\Presenter;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class MoveStudent extends EbdTool
{
    protected string $name = 'move_student';

    protected string $title = 'Mudar aluno de classe';

    protected string $description = 'Passa um aluno de uma classe para outra. A presença e as leituras antigas ficam no histórico da classe de origem. É preciso gerenciar as duas classes. Confirme com a pessoa antes.';

    /**
     * Só aparece para quem gerencia mais de uma classe.
     */
    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && ($user->isAdmin() || count($user->manageableClassroomIds() ?? []) > 1);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student' => $schema->string()->description('Nome ou id do aluno.')->required(),
            'from_classroom' => $schema->string()->description('Classe atual (slug, nome ou id).')->required(),
            'to_classroom' => $schema->string()->description('Nova classe (slug, nome ou id).')->required(),
        ];
    }

    public function handle(Request $request, MoveStudentToClassroom $move): Response
    {
        $user = $this->actor($request);
        $from = $this->resolveClassroom($user, $request->get('from_classroom'), 'from_classroom');
        $to = $this->resolveClassroom($user, $request->get('to_classroom'), 'to_classroom');
        $this->authorize($request, 'manageMembers', $from);
        $this->authorize($request, 'manageMembers', $to);
        $student = $this->resolveStudent($from, $request->get('student'));

        $this->audited(
            $request,
            $student,
            $from->id,
            fn () => $move->handle($student, $from, $to),
            fn (User $u) => ['classrooms' => $u->classrooms()->orderBy('slug')->pluck('slug')->all()],
        );

        return $this->json([
            'student' => Presenter::student($student),
            'from' => Presenter::classroom($from),
            'to' => Presenter::classroom($to),
        ]);
    }
}
