<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;

/**
 * Grava o que um agente de IA fez em nome de alguém (tabela audit_logs).
 *
 * Os argumentos passam por uma limpeza: textos longos viram um resumo com
 * tamanho e hash, para o histórico não duplicar o conteúdo inteiro das lições.
 */
class AuditLogger
{
    private const MAX_ARGUMENT_LENGTH = 2000;

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array{before?: array<string, mixed>, after?: array<string, mixed>}|null  $changes
     */
    public function record(
        ?User $user,
        string $tool,
        array $arguments,
        string $status,
        ?Model $subject = null,
        ?int $classroomId = null,
        ?array $changes = null,
        ?string $error = null,
    ): AuditLog {
        $token = $user?->token();
        $clientId = $token instanceof AccessToken ? $token->oauth_client_id : null;

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'source' => 'mcp',
            'client_id' => $clientId,
            'client_name' => $clientId ? Client::query()->whereKey($clientId)->value('name') : null,
            'tool' => $tool,
            'arguments' => $this->sanitize($arguments),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'classroom_id' => $classroomId,
            'changes' => $changes,
            'status' => $status,
            'error' => $error,
        ]);
    }

    /**
     * Só as chaves que mudaram, com o valor de antes e o de depois.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{before: array<string, mixed>, after: array<string, mixed>}|null
     */
    public function diff(array $before, array $after): ?array
    {
        $ignored = ['updated_at', 'search_vector', 'blocks_text'];
        $keys = array_diff(array_unique([...array_keys($before), ...array_keys($after)]), $ignored);

        $changed = array_values(array_filter($keys, fn (string $key) => ($before[$key] ?? null) != ($after[$key] ?? null)));

        if ($changed === []) {
            return null;
        }

        return [
            'before' => Arr::only($before, $changed),
            'after' => Arr::only($after, $changed),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function sanitize(array $arguments): array
    {
        return Arr::map($arguments, function (mixed $value): mixed {
            if (is_array($value)) {
                return $this->sanitize($value);
            }

            if (is_string($value) && mb_strlen($value) > self::MAX_ARGUMENT_LENGTH) {
                return sprintf('[texto com %d caracteres, sha1 %s]', mb_strlen($value), sha1($value));
            }

            return $value;
        });
    }
}
