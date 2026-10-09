<?php

namespace App\Mcp\Tools;

use App\Enums\LessonStatus;
use App\Mcp\Concerns\ResolvesEbdModels;
use App\Models\AuditLog;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Base das ferramentas do servidor MCP do EBD.
 *
 * O agente age sempre como a pessoa dona do token: as mesmas policies das
 * telas de gestão autorizam cada chamada, e toda escrita fica registrada em
 * audit_logs. As Actions de app/Actions fazem o trabalho; aqui só entram
 * autorização, validação e o formato da resposta.
 */
abstract class EbdTool extends Tool
{
    use ResolvesEbdModels;

    /**
     * Só quem acessa a gestão (professores e administradores) vê as ferramentas.
     */
    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->canAccessAdmin();
    }

    protected function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Entre com sua conta do EBD para usar esta ferramenta.');
        }

        return $user;
    }

    /**
     * Mesma checagem dos controllers (Gate/policies), com mensagem em português.
     * Negativas em ferramentas de escrita ficam no histórico.
     */
    protected function authorize(Request $request, string $ability, mixed ...$arguments): void
    {
        $user = $this->actor($request);

        if (Gate::forUser($user)->allows($ability, $arguments)) {
            return;
        }

        if (! $this->isReadOnly()) {
            $this->recordAudit($request, AuditLog::STATUS_DENIED, null, null, error: $ability);
        }

        throw new AuthorizationException('Você não tem permissão para isso nesta classe.');
    }

    /**
     * Agentes só mexem em rascunhos: publicar e editar lição publicada
     * continuam sendo feitos pela pessoa, no app.
     */
    protected function ensureDraft(Lesson $lesson): void
    {
        if ($lesson->status !== LessonStatus::Draft) {
            throw ValidationException::withMessages([
                'lesson_id' => "A lição \"{$lesson->displayTitle()}\" já foi publicada. Alterações nela são feitas pelo app: ".route('admin.lessons.edit', $lesson),
            ]);
        }
    }

    /**
     * Executa uma alteração em $subject (ou uma ação sem model, como planejar
     * a agenda) e grava no histórico o antes e o depois.
     *
     * O snapshot padrão são os atributos do model; ferramentas que mexem em
     * listas (chamada, turmas do aluno) passam o seu. Sem model, o histórico
     * guarda o resumo que a Action devolveu.
     *
     * @template TModel of Model
     * @template TResult
     *
     * @param  TModel|null  $subject
     * @param  Closure(): TResult  $change
     * @param  (Closure(TModel): array<string, mixed>)|null  $snapshot
     * @return TResult
     */
    protected function audited(Request $request, ?Model $subject, ?int $classroomId, Closure $change, ?Closure $snapshot = null): mixed
    {
        $snapshot ??= fn (Model $model): array => $model->attributesToArray();
        $before = $subject !== null ? $snapshot($subject) : [];

        $result = $this->runAudited($request, $subject, $classroomId, $change);

        $after = $subject !== null && $subject->exists ? $snapshot($subject->fresh() ?? $subject) : [];
        $changes = $subject === null
            ? (is_array($result) ? ['after' => $result] : null)
            : app(AuditLogger::class)->diff($before, $after);

        $this->recordAudit($request, AuditLog::STATUS_OK, $subject, $classroomId, $changes);

        return $result;
    }

    /**
     * Cria ($subject nulo) ou atualiza um model e grava o antes e o depois.
     *
     * @template TModel of Model
     *
     * @param  TModel|null  $subject
     * @param  Closure(): TModel  $save
     * @param  (Closure(TModel): array<string, mixed>)|null  $snapshot
     * @return TModel
     */
    protected function auditedSave(Request $request, ?Model $subject, ?int $classroomId, Closure $save, ?Closure $snapshot = null): Model
    {
        $snapshot ??= fn (Model $model): array => $model->attributesToArray();
        $before = $subject !== null ? $snapshot($subject) : [];

        $model = $this->runAudited($request, $subject, $classroomId, $save);

        $this->recordAudit($request, AuditLog::STATUS_OK, $model, $classroomId, app(AuditLogger::class)->diff($before, $snapshot($model->fresh() ?? $model)));

        return $model;
    }

    /**
     * Falhas inesperadas também vão para o histórico; erros de validação não
     * (nada mudou e o agente recebe a mensagem para corrigir).
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $change
     * @return TResult
     */
    private function runAudited(Request $request, ?Model $subject, ?int $classroomId, Closure $change): mixed
    {
        try {
            return $change();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->recordAudit($request, AuditLog::STATUS_FAILED, $subject, $classroomId, error: $e->getMessage());

            throw $e;
        }
    }

    /**
     * @param  array{before?: array<string, mixed>, after?: array<string, mixed>}|null  $changes
     */
    private function recordAudit(Request $request, string $status, ?Model $subject, ?int $classroomId, ?array $changes = null, ?string $error = null): void
    {
        app(AuditLogger::class)->record($this->actor($request), $this->name(), $request->all(), $status, $subject, $classroomId, $changes, $error);
    }

    /**
     * Na edição, o que o agente não mandar continua como está.
     *
     * @param  array<string, mixed>  $current
     */
    protected function keepCurrentValues(Request $request, array $current): void
    {
        $request->setArguments([...$current, ...$request->all()]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function json(array $data): Response
    {
        return Response::json($data);
    }

    private function isReadOnly(): bool
    {
        return ($this->annotations()['readOnlyHint'] ?? false) === true;
    }
}
