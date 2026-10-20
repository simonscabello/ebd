<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

/**
 * Pix estático sem valor fixo (BR Code do Banco Central, padrão EMV): o texto
 * do "Pix copia e cola" e o QR Code dele. Quem paga escolhe o valor no banco.
 */
final class PixCode
{
    public static function payload(string $key, string $name, string $city): string
    {
        $account = self::field('00', 'br.gov.bcb.pix').self::field('01', $key);

        $payload = self::field('00', '01')
            .self::field('01', '11')
            .self::field('26', $account)
            .self::field('52', '0000')
            .self::field('53', '986')
            .self::field('58', 'BR')
            .self::field('59', self::clean($name, 25))
            .self::field('60', self::clean($city, 15))
            .self::field('62', self::field('05', '***'))
            .'6304';

        return $payload.self::crc16($payload);
    }

    /** O BR Code da chave de config/brand.php. */
    public static function fromConfig(): string
    {
        return self::payload(
            (string) config('brand.pix.key'),
            (string) config('brand.pix.merchant_name'),
            (string) config('brand.pix.merchant_city'),
        );
    }

    /** QR Code em SVG (preto no branco, para qualquer banco ler). */
    public static function qrSvg(string $payload, int $size = 240): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($payload);

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    /** CRC16-CCITT (polinômio 0x1021, início 0xFFFF), em 4 dígitos hexadecimais. */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        foreach (str_split($data) as $char) {
            $crc ^= ord($char) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }

    private static function field(string $id, string $value): string
    {
        return $id.sprintf('%02d', strlen($value)).$value;
    }

    private static function clean(string $value, int $max): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($value)), 0, $max);
    }
}
