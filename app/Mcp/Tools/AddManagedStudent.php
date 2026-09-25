<?php

namespace App\Mcp\Tools;

use App\Actions\Classrooms\CreateManagedStudent;
use App\Concerns\StudentValidationRules;
use App\Mcp\Presenter;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class AddManagedStudent extends EbdTool
{
    use StudentValidationRules;

    protected string $name = 'add_managed_student';

    protected string $title = 'Cadastrar aluno';

    protected string $description = 'Cadastra um aluno na classe, sem e-mail nem senha (conta gerenciada pelo professor). O link pessoal de acesso é gerado e enviado pela pessoa na tela de Membros (members_url). Se já houver aluno com nome parecido, a ferramenta recusa: confirme com a pessoa e repita com confirm_duplicate.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'name' => $schema->string()->description('Nome completo do aluno.')->required(),
            'phone' => $schema->string()->description('WhatsApp com DDD, para enviar o link de acesso.'),
            'confirm_duplicate' => $schema->boolean()->description('true para cadastrar mesmo havendo aluno com nome parecido.'),
        ];
    }

    public function handle(Request $request, CreateManagedStudent $create): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $this->authorize($request, 'manageMembers', $classroom);

        $data = $request->validate([...$this->studentRules(), 'confirm_duplicate' => ['nullable', 'boolean']], $this->studentMessages(), $this->studentAttributes());

        if (! ($data['confirm_duplicate'] ?? false)) {
            $needle = Str::lower(Str::ascii(Str::squish($data['name'])));
            $similar = $classroom->students()->get()->filter(function (User $s) use ($needle) {
                $name = Str::lower(Str::ascii($s->name));

                return str_contains($name, $needle) || str_contains($needle, $name);
            });

            if ($similar->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'name' => 'Já existe aluno com nome parecido nesta classe: '.$similar->map(fn (User $s) => "{$s->id} — {$s->name}")->implode('; ').'. Se for outra pessoa, repita com confirm_duplicate: true.',
                ]);
            }
        }

        $student = $this->auditedSave(
            $request,
            null,
            $classroom->id,
            fn () => $create->createAccount($classroom, $data['name'], $data['phone'] ?? null),
            fn (User $u) => Arr::only($u->attributesToArray(), ['name', 'phone']),
        );

        return $this->json([
            'student' => Presenter::student($student),
            'message' => 'Aluno cadastrado. Para ele entrar no app, gere e envie o link pessoal na tela de Membros: '.route('admin.classrooms.members.index', $classroom),
            'members_url' => route('admin.classrooms.members.index', $classroom),
        ]);
    }
}
