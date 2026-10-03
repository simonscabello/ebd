<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Markdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Prévia do estudo e dos blocos no editor de lição, com o mesmo conversor
 * que monta a página do aluno.
 */
class MarkdownPreviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['nullable', 'string', 'max:50000'],
        ]);

        return response()->json(['html' => Markdown::toHtml($validated['text'] ?? '')]);
    }
}
