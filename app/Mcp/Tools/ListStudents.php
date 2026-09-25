<?php

namespace App\Mcp\Tools;

use App\Enums\ClassroomRole;
use App\Mcp\Presenter;
use App\Models\ClassroomMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class ListStudents extends EbdTool
{
    protected string $name = 'list_students';

    protected string $title = 'Alunos da classe';

    protected string $description = 'Lista os alunos e professores da classe, com id, telefone e se a conta é gerenciada (sem senha, entra por link pessoal). Use os ids para registrar presença sem ambiguidade.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'classroom' => $schema->string()->description('Slug, nome ou id da classe.')->required(),
            'q' => $schema->string()->description('Parte do nome, para filtrar.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->actor($request);
        $classroom = $this->resolveClassroom($user, $request->get('classroom'));
        $this->authorize($request, 'manageMembers', $classroom);

        $needle = Str::lower(Str::ascii((string) $request->get('q', '')));

        $members = ClassroomMember::query()
            ->whereBelongsTo($classroom)
            ->with(['user.accessLinks' => fn ($q) => $q->active()])
            ->get()
            ->filter(fn (ClassroomMember $m) => $needle === '' || str_contains(Str::lower(Str::ascii($m->user->name)), $needle))
            ->sortBy(fn (ClassroomMember $m) => Str::lower(Str::ascii($m->user->name)))
            ->values();

        [$teachers, $students] = $members->partition(fn (ClassroomMember $m) => $m->role === ClassroomRole::Teacher);

        return $this->json([
            'classroom' => Presenter::classroom($classroom),
            'students' => $students->map(fn (ClassroomMember $m) => [
                ...Presenter::student($m->user),
                'joined_on' => $m->created_at?->toDateString(),
                'has_access_link' => $m->user->accessLinks->isNotEmpty(),
            ])->values()->all(),
            'teachers' => $teachers->map(fn (ClassroomMember $m) => ['id' => $m->user->id, 'name' => $m->user->name])->values()->all(),
            'members_url' => route('admin.classrooms.members.index', $classroom),
        ]);
    }
}
