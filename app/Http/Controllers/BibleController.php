<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Lesson;
use App\Support\Bible\Bible;
use App\Support\ChurchFeatures;
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
            // De qual lição veio o pedido: o texto segue a igreja dela.
            'licao' => ['nullable', 'string', 'max:200'],
        ]);

        $lesson = isset($validated['licao'])
            ? Lesson::query()->where('slug', $validated['licao'])->first()
            : null;

        // Sem a lição (app antigo em cache), vale a igreja de quem pede; o
        // visitante segue a igreja principal, como hoje (sem igreja cadastrada, a
        // instalação é de uma igreja só e mostra o texto). Essa resposta depende
        // de quem pede, então não vai para caches compartilhados.
        $shows = $lesson !== null
            ? ChurchFeatures::showsBibleText($lesson)
            : (($request->user()?->currentChurch() ?? Church::primary())?->shows_bible_text ?? true);

        $references = array_slice(array_filter(array_map('trim', explode('|', $validated['ref']))), 0, 12);

        return response()->json([
            'passages' => array_map(fn (string $reference) => [
                'reference' => $reference,
                'passage' => $shows ? Bible::passage($reference) : null,
            ], $references),
        ])->setCache($lesson !== null ? ['public' => true, 'max_age' => 86400] : ['private' => true, 'max_age' => 3600]);
    }
}
