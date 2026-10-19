<?php

namespace App\Models;

use App\Enums\AiUsageKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Um uso da IA por uma igreja: base dos limites e dos números do painel do
 * dono. Não guarda conteúdo (a pergunta fica em study_questions).
 *
 * @property int $id
 * @property int $church_id
 * @property int|null $lesson_id
 * @property AiUsageKind $kind
 * @property Carbon $created_at
 */
class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => AiUsageKind::class];
    }

    public static function record(Church $church, AiUsageKind $kind, ?Lesson $lesson = null): self
    {
        return self::query()->create([
            'church_id' => $church->id,
            'lesson_id' => $lesson?->id,
            'kind' => $kind,
        ]);
    }

    /**
     * @return BelongsTo<Church, $this>
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
