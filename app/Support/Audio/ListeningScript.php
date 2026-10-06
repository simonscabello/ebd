<?php

namespace App\Support\Audio;

use Illuminate\Support\Facades\Log;

/**
 * Reescreve o estudo para ser ouvido: frases mais curtas, transições entre
 * os tópicos, perguntas para quem ouve. Uma chamada por parte (narrador),
 * com o roteiro geral como contexto. O conteúdo não muda; se o modelo
 * devolver algo muito mais curto ou mais longo que a parte, vale o texto
 * original dela.
 */
class ListeningScript
{
    private const INSTRUCTIONS = <<<'TXT'
        Você adapta estudos bíblicos escritos (Escola Bíblica Dominical, classe de jovens) para serem ouvidos em áudio, em português do Brasil. Reescreva a parte do estudo que vier como roteiro de narração.

        Fidelidade (o mais importante):
        - Mantenha todas as ideias, os argumentos, os exemplos, as citações entre aspas e a ordem. Não resuma e não pule nenhuma ideia.
        - Não acrescente ideias, fatos, versículos, histórias nem aplicações que não estejam no texto.
        - Referências bíblicas que aparecerem já estão escritas por extenso: mantenha-as como estão. Não acrescente outras.

        Para ficar bom de ouvir (reescreva de verdade, não copie o texto):
        - Quebre as frases longas em duas ou três frases curtas. Quem ouve não pode voltar a linha.
        - Troque palavras difíceis ou acadêmicas por palavras simples ("irrupção" vira "chegada"); quando o termo importa (como "escatológico"), mantenha e explique em poucas palavras.
        - Linguagem falada, próxima e respeitosa, como um professor conversando com jovens. Voz ativa. Pode usar "nós" e falar com quem ouve ("você").
        - Abra cada tópico com uma frase que desperte a curiosidade e transforme o título numa transição natural ("Vamos ao primeiro ponto: …").
        - Em cada tópico, faça pelo menos uma pergunta retórica para quem ouve, a partir do que o próprio texto diz.
        - Varie as ligações ("Repare que…", "Pense nisso:", "E aqui vem algo importante:"), sem repetir a mesma.
        - Nada de markdown, listas, emojis, títulos ou indicações de cena: só o texto que será falado, em parágrafos curtos.

        Posição da parte:
        - Introdução: comece apresentando a lição pelo título, de forma acolhedora e breve.
        - Partes do meio: não cumprimente nem apresente a lição de novo; comece com a transição para o tópico.
        - Conclusão: amarre o estudo e, se houver uma pergunta final, termine com ela.
        TXT;

    public function __construct(private OpenAiSpeech $openai) {}

    /**
     * @param  list<string>  $parts  texto de cada parte (StudyNarration::partTexts)
     * @return list<string>
     *
     * @throws SpeechFailed
     */
    public function write(array $parts): array
    {
        $outline = implode("\n", array_map(
            fn (string $part, int $index) => ($index + 1).'. '.strtok($part, "\n"),
            $parts,
            array_keys($parts),
        ));

        return array_map(function (string $part, int $index) use ($parts, $outline) {
            $position = match (true) {
                count($parts) === 1 => 'Estudo inteiro (introdução, desenvolvimento e conclusão).',
                $index === 0 => 'Introdução (parte 1 de '.count($parts).').',
                $index === count($parts) - 1 => 'Conclusão (parte '.($index + 1).' de '.count($parts).').',
                default => 'Parte do meio ('.($index + 1).' de '.count($parts).').',
            };

            $script = $this->openai->write(
                self::INSTRUCTIONS,
                "Roteiro geral do estudo:\n{$outline}\n\nPosição desta parte: {$position}\n\nTexto da parte:\n\n{$part}",
            );

            // Folga fixa para partes curtas, que ganham transição e apresentação.
            $length = mb_strlen($part);

            if (mb_strlen($script) < $length * 0.6 || mb_strlen($script) > $length * 1.8 + 300) {
                Log::warning('Roteiro do áudio fora do tamanho esperado; usando o texto original da parte.', [
                    'part' => $index, 'original' => $length, 'script' => mb_strlen($script),
                ]);

                return $part;
            }

            return $script;
        }, $parts, array_keys($parts));
    }
}
