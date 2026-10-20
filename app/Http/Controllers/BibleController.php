<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Lesson;
use App\Support\Bible\Bible;
use App\Support\Bible\Reference;
use App\Support\ChurchFeatures;
use App\Support\Markdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Texto das referências clicáveis das lições (ver BibleLinks). Público, como
 * as lições: quem abre o link do WhatsApp sem login também lê o trecho.
 *
 * O texto é licenciado: só sai para quem pode ver a lição, só se a igreja
 * dela tem licença e só das referências que estão nela.
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

        // Lição que a pessoa não pode ver (rascunho, turma fechada) conta como
        // inexistente: não abre o texto.
        if ($lesson !== null && Gate::denies('view', $lesson)) {
            $lesson = null;
        }

        $shows = match (true) {
            $lesson !== null => ChurchFeatures::showsBibleText($lesson),
            // Lição que não existe ou que a pessoa não vê: sem texto.
            isset($validated['licao']) => false,
            // Sem a lição (app antigo em cache): vale a igreja de quem pede.
            // Quem não tem igreja não recebe o texto.
            $request->user() !== null => (bool) $request->user()->currentChurch()?->shows_bible_text,
            // Visitante sem lição não recebe o texto, exceto numa instalação sem
            // nenhuma igreja cadastrada (uma igreja só, como antes).
            default => ! Church::query()->exists(),
        };

        // Quem edita a lição lê qualquer trecho (a prévia do editor mostra o
        // que ainda não foi salvo); os outros, só os da lição.
        $allowed = $lesson !== null && $shows && Gate::denies('update', $lesson)
            ? self::lessonReferences($lesson)
            : null;

        $references = array_slice(array_filter(array_map('trim', explode('|', $validated['ref']))), 0, 12);

        return response()->json([
            'passages' => array_map(fn (string $reference) => [
                'reference' => $reference,
                'passage' => $shows && ($allowed === null || isset($allowed[self::normalize($reference)]))
                    ? Bible::passage($reference)
                    : null,
            ], $references),
        ])
            // A resposta depende de quem pede (rascunho, igreja) e a licença pode
            // ser desligada a qualquer hora: só o navegador guarda, por pouco tempo.
            ->setCache(['private' => true, 'max_age' => 300]);
    }

    /**
     * Referências da lição na forma canônica (a do data-bible dos botões):
     * passagem, versículo-chave, leituras e as achadas no texto e nos blocos.
     *
     * @return array<string, true>
     */
    private static function lessonReferences(Lesson $lesson): array
    {
        $lesson->loadMissing(['readings', 'blocks']);

        $labels = [];

        foreach ([$lesson->bible_reference, $lesson->key_verse, ...$lesson->readings->pluck('reference')] as $reference) {
            $label = self::normalize((string) $reference);

            if ($label !== '') {
                $labels[$label] = true;
            }
        }

        // O mesmo HTML que a página recebe: os botões trazem o grupo inteiro.
        foreach ([$lesson->content, ...$lesson->blocks->pluck('body')] as $markdown) {
            preg_match_all('/data-bible="([^"]*)"/', (string) Markdown::toHtml($markdown), $matches);

            foreach ($matches[1] as $group) {
                foreach (explode('|', html_entity_decode($group, ENT_QUOTES | ENT_HTML5)) as $label) {
                    $labels[self::normalize($label)] = true;
                }
            }
        }

        return $labels;
    }

    /** Forma canônica ("Lc 5.12" e "Lucas 5.12" são o mesmo trecho). */
    private static function normalize(string $reference): string
    {
        return Reference::parse($reference)?->label() ?? trim($reference);
    }
}
