<?php

namespace App\Support\Audio;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Põe uma música suave por baixo da narração, com o ffmpeg. A música começa
 * sozinha, abaixa quando a voz fala e sobe sozinha nas pausas entre as partes
 * do estudo, como um interlúdio; no fim, some aos poucos.
 */
class BackgroundMusic
{
    /** Segundos de música sem voz: antes da primeira parte, entre as partes e depois da última. */
    private const INTRO = 2.5;

    private const INTERLUDE = 4;

    private const OUTRO = 4;

    public function isAvailable(): bool
    {
        $track = config('ebd.audio.music');

        return filled($track) && is_file($track) && Process::run(['ffmpeg', '-version'])->successful();
    }

    /**
     * @param  list<string>  $parts  MP3 da narração de cada parte, na ordem
     * @return string MP3 final
     */
    public function mix(array $parts): string
    {
        $dir = storage_path('app/tmp/'.Str::random(16));
        File::ensureDirectoryExists($dir);

        try {
            $command = ['ffmpeg', '-v', 'error', '-y'];

            foreach ($parts as $index => $part) {
                File::put("{$dir}/{$index}.mp3", $part);
                array_push($command, '-i', "{$dir}/{$index}.mp3");
            }

            array_push($command, '-stream_loop', '-1', '-i', (string) config('ebd.audio.music'));

            $total = Mp3::duration(Mp3::concat($parts)) + self::INTRO + self::INTERLUDE * (count($parts) - 1) + self::OUTRO;
            $silence = fn (string $label, float $seconds) => "aevalsrc=0|0:c=stereo:s=44100:d={$seconds}[{$label}]";

            $filters = [$silence('intro', self::INTRO), $silence('outro', self::OUTRO)];
            $voice = '[intro]';

            foreach (array_keys($parts) as $index) {
                $filters[] = "[{$index}:a]aresample=44100,pan=stereo|c0=c0|c1=c0[p{$index}]";

                if ($index > 0) {
                    $filters[] = $silence("g{$index}", self::INTERLUDE);
                    $voice .= "[g{$index}]";
                }

                $voice .= "[p{$index}]";
            }

            $music = count($parts);
            $filters[] = $voice.'[outro]concat=n='.(count($parts) * 2 + 1).':v=0:a=1,asplit[voice][key]';
            $filters[] = "[{$music}:a]aresample=44100,aformat=channel_layouts=stereo,volume=".config('ebd.audio.music_volume').',afade=t=in:d=2[bed]';
            // A voz comprime a música enquanto fala; nas pausas, ela volta ao volume normal.
            $filters[] = '[bed][key]sidechaincompress=threshold=0.015:ratio=6:attack=80:release=900[ducked]';
            $filters[] = '[voice][ducked]amix=inputs=2:duration=first:normalize=0,afade=t=out:st='.max(0, $total - self::OUTRO).':d='.self::OUTRO;

            array_push($command, '-filter_complex', implode(';', $filters), '-b:a', '128k', "{$dir}/out.mp3");

            Process::timeout(300)->run($command)->throw();

            return File::get("{$dir}/out.mp3");
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
