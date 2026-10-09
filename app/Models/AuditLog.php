<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Uma alteração feita por um agente de IA (servidor MCP) em nome de alguém.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $source
 * @property string|null $client_id
 * @property string|null $client_name
 * @property string $tool
 * @property array<string, mixed>|null $arguments
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $classroom_id
 * @property array{before?: array<string, mixed>, after?: array<string, mixed>}|null $changes
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'source',
    'client_id',
    'client_name',
    'tool',
    'arguments',
    'subject_type',
    'subject_id',
    'classroom_id',
    'changes',
    'status',
    'error',
])]
class AuditLog extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    public const STATUS_OK = 'ok';

    public const STATUS_DENIED = 'denied';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'changes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Um ano de histórico basta para conferir e desfazer.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subYear());
    }
}
