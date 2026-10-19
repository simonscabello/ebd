<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Support\Bible\Bible;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Prévia do texto de uma referência enquanto o professor digita, para ele
 * confirmar que a leitura foi entendida (e corrigir se não foi).
 */
class BiblePreviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:160'],
            // Turma da lição em edição: o texto segue a igreja dela.
            'classe' => ['nullable', 'integer'],
        ]);

        $classroom = isset($validated['classe']) ? Classroom::query()->whereKey($validated['classe'])->first() : null;
        $church = $classroom !== null && $request->user()?->can('manageContent', $classroom)
            ? $classroom->church
            : $request->user()?->currentChurch();

        $passage = Bible::passage($validated['reference']);

        // Sem licença do texto, a prévia só confirma a referência entendida.
        if ($passage !== null && ! $church?->shows_bible_text) {
            $passage['verses'] = array_map(fn (array $verse) => [...$verse, 'text' => ''], $passage['verses']);
        }

        return response()->json(['passage' => $passage]);
    }
}
