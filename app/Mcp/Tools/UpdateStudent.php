<?php

namespace App\Mcp\Tools;

use App\Actions\Classrooms\UpdateManagedStudent;
use App\Concerns\StudentValidationRules;
use App\Mcp\Presenter;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateStudent extends EbdTool
{
    use StudentValidationRules;

    protected string $name = 'update_student';

    protected string $title = 'Corrigir dados do aluno';

    protected string $description = 'Corrige o nome ou o telefone de um aluno da classe. Professores só alteram contas criadas por eles (sem senha); quem tem senha própria atualiza o perfil sozinho.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'student' => $schema->string()->description('Nome atual ou id do aluno.')->required(),
            'name' => $schema->string()->description('Nome corrigido.'),
            'phone' => $schema->string()->nullable()->description('Telefone com DDD (null apaga).'),
        ];
    }

    public function handle(Request $request, UpdateManagedStudent $update): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $this->authorize($request, 'manageMembers', $classroom);
        $student = $this->resolveStudent($classroom, $request->get('student'));

        $rules = array_map(fn (array $rules) => ['sometimes', ...$rules], $this->studentRules());
        $data = Arr::only($request->validate([...$rules, 'classroom' => ['required'], 'student' => ['required']], $this->studentMessages(), $this->studentAttributes()), ['name', 'phone']);

        $this->audited(
            $request,
            $student,
            $classroom->id,
            fn () => $update->handle($student, $data, $user),
            fn (User $u) => Arr::only($u->attributesToArray(), ['name', 'phone']),
        );

        return $this->json(['student' => Presenter::student($student->refresh())]);
    }
}
