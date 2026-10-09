<?php

namespace App\Support;

use App\Support\Bible\Bible;
use Illuminate\Support\Arr;

/**
 * Versículo da tela de abertura do app, sorteado a cada carregamento.
 *
 * Só as referências ficam no código: o texto vem da versão importada
 * (`php artisan bible:import`). Sem a importação, a abertura mostra uma
 * mensagem no lugar do versículo.
 */
final class SplashVerse
{
    /**
     * Trechos curtos (cabem na tela e dá para ler enquanto o app abre) sobre
     * a Palavra, o aprendizado e o dia do Senhor.
     */
    public const REFERENCES = [
        'Sl 119.105',
        'Sl 119.18',
        'Sl 119.11',
        'Sl 119.97',
        'Sl 119.130',
        'Sl 19.7',
        'Sl 25.4',
        'Sl 46.10',
        'Sl 90.12',
        'Sl 118.24',
        'Sl 122.1',
        'Dt 6.6',
        'Pv 1.7',
        'Pv 3.5',
        'Pv 4.18',
        'Pv 9.10',
        'Is 40.8',
        'Mt 24.35',
        'Jo 17.17',
        'Rm 10.17',
        'Fp 1.6',
        '1Ts 5.18',
        'Hb 10.24',
        'Tg 1.22',
    ];

    public const FALLBACK = 'Que a Palavra de Deus ilumine o seu estudo hoje.';

    /**
     * @return array{text: string, reference: string|null}
     */
    public static function pick(): array
    {
        $passage = Bible::passage(Arr::random(self::REFERENCES));

        if ($passage === null) {
            return ['text' => self::FALLBACK, 'reference' => null];
        }

        return [
            'text' => implode(' ', array_column($passage['verses'], 'text')),
            'reference' => "{$passage['label']} · {$passage['version']}",
        ];
    }
}
