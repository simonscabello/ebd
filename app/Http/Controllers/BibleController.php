<?php

namespace App\Http\Controllers;

use App\Support\Bible\Bible;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Texto das referências clicáveis das lições (ver BibleLinks). Público, como
 * as lições: quem abre o link do WhatsApp sem login também lê o trecho.
 */
class BibleController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Trechos separados por "|", como no data-bible dos botões.
            'ref' => ['required', 'string', 'max:500'],
        ]);

        $references = array_slice(array_filter(array_map('trim', explode('|', $validated['ref']))), 0, 12);

        return response()->json([
            'passages' => array_map(fn (string $reference) => [
                'reference' => $reference,
                'passage' => Bible::passage($reference),
            ], $references),
        ])->setCache(['public' => true, 'max_age' => 86400]);
    }
}
