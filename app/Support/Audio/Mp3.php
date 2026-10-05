<?php

namespace App\Support\Audio;

/**
 * Junta trechos MP3 em um arquivo só e calcula a duração. Os trechos da
 * OpenAI têm o mesmo formato (CBR, mesma taxa), então basta concatenar os
 * quadros, tirando etiquetas ID3 que ficariam no meio do arquivo.
 */
final class Mp3
{
    /** kbps por índice: [MPEG-1][MPEG-2/2.5], camada III. */
    private const BITRATES = [
        1 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
        2 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
    ];

    /** Hz por versão (bits do cabeçalho: 3 = MPEG-1, 2 = MPEG-2, 0 = MPEG-2.5). */
    private const SAMPLE_RATES = [
        3 => [44100, 48000, 32000],
        2 => [22050, 24000, 16000],
        0 => [11025, 12000, 8000],
    ];

    /**
     * @param  list<string>  $parts
     */
    public static function concat(array $parts): string
    {
        return implode('', array_map(self::stripTags(...), $parts));
    }

    /** Duração em segundos, contando os quadros. Null se não parecer MP3. */
    public static function duration(string $data): ?int
    {
        $data = self::stripTags($data);
        $length = strlen($data);
        $position = 0;
        $seconds = 0.0;

        while ($position + 4 <= $length) {
            $bytes = substr($data, $position, 4);
            $header = (ord($bytes[0]) << 24) | (ord($bytes[1]) << 16) | (ord($bytes[2]) << 8) | ord($bytes[3]);

            $version = ($header >> 19) & 0b11;
            $layer = ($header >> 17) & 0b11;
            $bitrateIndex = ($header >> 12) & 0b1111;
            $rateIndex = ($header >> 10) & 0b11;

            // Sincronismo + camada III + índices válidos.
            if (($header & 0xFFE00000) !== 0xFFE00000 || $version === 1 || $layer !== 1
                || $bitrateIndex === 0 || $bitrateIndex === 15 || $rateIndex === 3) {
                break;
            }

            $mpeg1 = $version === 3;
            $bitrate = self::BITRATES[$mpeg1 ? 1 : 2][$bitrateIndex] * 1000;
            $sampleRate = self::SAMPLE_RATES[$version][$rateIndex];
            $samples = $mpeg1 ? 1152 : 576;

            $position += intdiv($samples / 8 * $bitrate, $sampleRate) + (($header >> 9) & 1);
            $seconds += $samples / $sampleRate;
        }

        return $seconds > 0 ? (int) round($seconds) : null;
    }

    /** Remove ID3v2 do começo e ID3v1 ("TAG") do fim. */
    private static function stripTags(string $data): string
    {
        if (str_starts_with($data, 'ID3') && strlen($data) >= 10) {
            $size = (ord($data[6]) << 21) | (ord($data[7]) << 14) | (ord($data[8]) << 7) | ord($data[9]);
            $footer = (ord($data[5]) & 0x10) ? 10 : 0;
            $data = substr($data, 10 + $size + $footer);
        }

        if (strlen($data) >= 128 && substr($data, -128, 3) === 'TAG') {
            $data = substr($data, 0, -128);
        }

        return $data;
    }
}
