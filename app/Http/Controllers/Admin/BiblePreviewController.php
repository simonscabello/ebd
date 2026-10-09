<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        ]);

        return response()->json(['passage' => Bible::passage($validated['reference'])]);
    }
}
