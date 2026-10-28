<?php

namespace App\Support\Covers;

use GdImage;

/**
 * Recorte da capa: tira o fundo papel liso das ilustrações e apara até a
 * cena, para o destaque do Início pôr a cena sobre a cor do cartão.
 *
 * Remove o papel ligado à borda (flood fill a partir dela) e as manchas
 * grandes de papel presas na cena; roupas claras ficam, porque não têm a cor
 * do papel. Foto, fundo escuro ou
 * fundo com textura não geram recorte, e quem usa cai na capa inteira.
 */
class PaperCutout
{
    /** Distância máxima (soma das diferenças de R, G e B) até a cor do papel. */
    public const TOLERANCE = 38;

    /** Fração mínima da borda que precisa ser papel. */
    public const MIN_PAPER_EDGE = 0.5;

    /**
     * @param  positive-int  $size  lado maior da cena reduzida
     * @return array{image: GdImage|null, note: string} imagem com alfa, ou null e o motivo
     */
    public function make(GdImage $source, int $size): array
    {
        $scale = min(1, $size / max(imagesx($source), imagesy($source)));
        $w = max(1, (int) round(imagesx($source) * $scale));
        $h = max(1, (int) round(imagesy($source) * $scale));
        $img = imagecreatetruecolor($w, $h);
        imagecopyresampled($img, $source, 0, 0, 0, 0, $w, $h, imagesx($source), imagesy($source));

        $edge = [];
        for ($x = 0; $x < $w; $x++) {
            $edge[] = $x;
            $edge[] = ($h - 1) * $w + $x;
        }
        for ($y = 1; $y < $h - 1; $y++) {
            $edge[] = $y * $w;
            $edge[] = $y * $w + $w - 1;
        }

        $channels = [[], [], []];
        foreach ($edge as $i) {
            $c = imagecolorat($img, $i % $w, intdiv($i, $w));
            $channels[0][] = ($c >> 16) & 0xFF;
            $channels[1][] = ($c >> 8) & 0xFF;
            $channels[2][] = $c & 0xFF;
        }
        [$pr, $pg, $pb] = array_map(function (array $values) {
            sort($values);

            return $values[intdiv(count($values), 2)];
        }, $channels);

        $isPaper = function (int $i) use ($img, $w, $pr, $pg, $pb): bool {
            $c = imagecolorat($img, $i % $w, intdiv($i, $w));

            return abs((($c >> 16) & 0xFF) - $pr) + abs((($c >> 8) & 0xFF) - $pg) + abs(($c & 0xFF) - $pb) < self::TOLERANCE;
        };

        $stack = array_values(array_filter($edge, $isPaper));
        $smooth = count($stack) / count($edge);

        if ($smooth < self::MIN_PAPER_EDGE) {
            return ['image' => null, 'note' => sprintf('borda lisa %d%%, mínimo %d%%', $smooth * 100, self::MIN_PAPER_EDGE * 100)];
        }

        $light = ($pr + $pg + $pb) / 3;

        if ($light <= 215) {
            return ['image' => null, 'note' => sprintf('fundo escuro (média %d, precisa passar de 215)', $light)];
        }

        // Sem recursão: pilha de índices e "visto" numa string de bytes (\1 = fundo, \2 = cena).
        $removed = str_repeat("\0", $w * $h);
        $fill = function (array $stack, string $mark) use (&$removed, $w, $h, $isPaper): array {
            foreach ($stack as $i) {
                $removed[$i] = $mark;
            }
            $region = $stack;

            while ($stack !== []) {
                $i = array_pop($stack);
                $x = $i % $w;

                foreach ([$x > 0 ? $i - 1 : -1, $x < $w - 1 ? $i + 1 : -1, $i - $w, $i + $w] as $n) {
                    if ($n >= 0 && $n < $w * $h && $removed[$n] === "\0" && $isPaper($n)) {
                        $removed[$n] = $mark;
                        $stack[] = $n;
                        $region[] = $n;
                    }
                }
            }

            return $region;
        };
        $count = count($fill($stack, "\1"));

        // Papel preso no meio da cena (vão entre braço e manto, porta aberta) vira
        // mancha clara no cartão escuro. Só sai quando é grande: brilho pequeno fica.
        for ($i = 0; $i < $w * $h; $i++) {
            if ($removed[$i] === "\0" && $isPaper($i)) {
                $region = $fill([$i], "\2");

                if (count($region) >= $w * $h * 0.002) {
                    foreach ($region as $n) {
                        $removed[$n] = "\1";
                    }
                    $count += count($region);
                }
            }
        }

        $fraction = $count / ($w * $h);

        if ($fraction < 0.2 || $fraction > 0.95) {
            return ['image' => null, 'note' => sprintf('fundo removido %d%%, fora de 20–95%%', $fraction * 100)];
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);
        $clear = (int) imagecolorallocatealpha($img, 255, 255, 255, 127);
        [$left, $top, $right, $bottom] = [$w, $h, -1, -1];

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $i = $y * $w + $x;

                if ($removed[$i] === "\1") {
                    imagesetpixel($img, $x, $y, $clear);

                    continue;
                }

                [$left, $top, $right, $bottom] = [min($left, $x), min($top, $y), max($right, $x), max($bottom, $y)];

                // 1 px de borda meio transparente: sem isso a silhueta fica serrilhada.
                if (($x > 0 && $removed[$i - 1] === "\1") || ($x < $w - 1 && $removed[$i + 1] === "\1")
                    || ($y > 0 && $removed[$i - $w] === "\1") || ($y < $h - 1 && $removed[$i + $w] === "\1")) {
                    imagesetpixel($img, $x, $y, (imagecolorat($img, $x, $y) & 0xFFFFFF) | (60 << 24));
                }
            }
        }

        $pad = (int) round($w * 0.01);
        $left = max(0, $left - $pad);
        $top = max(0, $top - $pad);
        $cw = max(1, min($w - 1, $right + $pad) - $left + 1);
        $ch = max(1, min($h - 1, $bottom + $pad) - $top + 1);

        $cut = imagecreatetruecolor($cw, $ch);
        imagealphablending($cut, false);
        imagesavealpha($cut, true);
        imagecopy($cut, $img, 0, 0, $left, $top, $cw, $ch);

        return ['image' => $cut, 'note' => sprintf('fundo %d%%, cena %.2f:1', $fraction * 100, $cw / $ch)];
    }
}
