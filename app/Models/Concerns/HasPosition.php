<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Itens ordenáveis dentro de uma lição (materiais, perguntas, leituras).
 * Novos itens entram no fim da lista quando nenhuma posição é informada.
 *
 * @mixin Model
 */
trait HasPosition
{
    public static function bootHasPosition(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->getAttribute('position')) {
                $max = static::query()
                    ->where('lesson_id', $model->getAttribute('lesson_id'))
                    ->max('position');

                $model->setAttribute('position', ((int) $max) + 1);
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }
}
