<?php

namespace App\Support;

/**
 * Comparação de textos linha a linha (maior subsequência comum), para mostrar
 * o que mudou numa versão nova de um estudo. Textos de lição são curtos o
 * bastante para a tabela quadrática.
 */
final class TextDiff
{
    /**
     * @return list<array{type: 'same'|'added'|'removed', text: string}>
     */
    public static function lines(?string $old, ?string $new): array
    {
        $a = self::split($old);
        $b = self::split($new);
        $n = count($a);
        $m = count($b);

        // $lcs[$i][$j] = tamanho da maior subsequência comum de a[i..] e b[j..].
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $diff = [];
        $i = 0;
        $j = 0;

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $diff[] = ['type' => 'same', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $diff[] = ['type' => 'removed', 'text' => $a[$i++]];
            } else {
                $diff[] = ['type' => 'added', 'text' => $b[$j++]];
            }
        }

        while ($i < $n) {
            $diff[] = ['type' => 'removed', 'text' => $a[$i++]];
        }

        while ($j < $m) {
            $diff[] = ['type' => 'added', 'text' => $b[$j++]];
        }

        return $diff;
    }

    public static function changed(?string $old, ?string $new): bool
    {
        return self::split($old) !== self::split($new);
    }

    /**
     * @return list<string>
     */
    private static function split(?string $text): array
    {
        $text = str_replace("\r\n", "\n", (string) $text);

        return $text === '' ? [] : explode("\n", $text);
    }
}
