<?php

namespace App\Actions\Series;

use App\Models\Series;
use Illuminate\Validation\ValidationException;

class DeleteSeries
{
    /**
     * Séries com lições (inclusive excluídas) não podem ser removidas: o acervo
     * da biblioteca depende delas.
     */
    public function handle(Series $series): void
    {
        if ($series->lessons()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'series' => 'Esta série possui lições e não pode ser excluída.',
            ]);
        }

        $series->delete();
    }
}
