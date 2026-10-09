import { Headphones, Pause, Play, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    SKIP_SECONDS,
    savedPosition,
    useAudioPlayer,
} from '@/components/lesson/audio-player-provider';
import type { AudioTrack } from '@/components/lesson/audio-player-provider';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function formatTime(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = String(total % 60).padStart(2, '0');

    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
}

/**
 * "Ouvir estudo": player do áudio narrado do estudo, com play/pause,
 * posição, duração e velocidade. O som sai do player único do app
 * (AudioPlayerProvider), então continua tocando se a pessoa navegar e
 * retoma de onde ela parou. A geração fica na gestão (edição da lição).
 */
export function LessonAudioPlayer({
    src,
    title = 'Estudo da lição',
    href = null,
    knownDuration,
    highlight = false,
}: {
    src: string;
    /** Título da lição, para o mini player e a tela de bloqueio. */
    title?: string;
    /** Página da lição, para o mini player levar de volta. */
    href?: string | null;
    knownDuration: number | null;
    /** Chegou pelo link "Ouvir" (#ouvir): destaca o player e o play. */
    highlight?: boolean;
}) {
    const player = useAudioPlayer();
    const rootRef = useRef<HTMLDivElement>(null);
    const bound = player.track?.src === src;
    const track: AudioTrack = { src, title, href, knownDuration };

    // Enquanto outro áudio (ou nenhum) está no player, mostra onde parou.
    const [idlePosition, setIdlePosition] = useState(0);
    const [resumedFrom, setResumedFrom] = useState(0);

    useEffect(() => {
        if (!bound) {
            const saved = savedPosition(src);
            setIdlePosition(saved);
            setResumedFrom(saved);
        }
    }, [src, bound]);

    const playing = bound && player.playing;
    const current = bound ? player.current : idlePosition;
    const duration = (bound && player.duration) || knownDuration || 0;

    // Avisa o player se este está na tela, para o mini player se esconder.
    const { setInlineVisible } = player;

    useEffect(() => {
        const element = rootRef.current;

        if (!element || !bound || !('IntersectionObserver' in window)) {
            return;
        }

        const observer = new IntersectionObserver(([entry]) =>
            setInlineVisible(src, entry.isIntersecting),
        );
        observer.observe(element);

        return () => {
            observer.disconnect();
            setInlineVisible(src, false);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [src, bound]);

    const toggle = () => {
        if (playing) {
            player.pause();
        } else {
            setResumedFrom(0);
            player.play(track);
        }
    };

    const seekTo = (time: number) => {
        if (bound) {
            player.seekTo(time);
        } else {
            setIdlePosition(Math.max(0, time));
            player.play(track);
            player.seekTo(time);
        }
    };

    return (
        <div
            ref={rootRef}
            className={cn(
                'rounded-2xl border bg-card p-3.5 shadow-xs transition-shadow duration-500',
                highlight &&
                    'ring-2 ring-primary ring-offset-2 ring-offset-background',
            )}
        >
            <div className="flex items-center gap-3.5">
                <button
                    type="button"
                    onClick={toggle}
                    aria-label={playing ? 'Pausar' : 'Ouvir estudo'}
                    className={cn(
                        'flex size-12 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                        highlight && !playing && 'animate-pulse',
                    )}
                >
                    {playing ? (
                        <Pause className="size-5 fill-current" />
                    ) : (
                        <Play className="ml-0.5 size-5 fill-current" />
                    )}
                </button>
                <div className="min-w-0 flex-1">
                    <p className="flex items-center gap-1.5 text-sm font-medium">
                        <Headphones className="size-4 text-muted-foreground" />
                        Ouvir estudo
                    </p>
                    <input
                        type="range"
                        min={0}
                        max={duration || 0}
                        step={1}
                        value={Math.min(current, duration || 0)}
                        onChange={(e) => seekTo(Number(e.target.value))}
                        disabled={!duration}
                        aria-label="Posição do áudio"
                        className="mt-1.5 h-1.5 w-full cursor-pointer accent-primary disabled:cursor-default"
                    />
                    <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                        {formatTime(current)}
                        {duration > 0 && ` / ${formatTime(duration)}`}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => seekTo(current - SKIP_SECONDS)}
                    disabled={current <= 0}
                    aria-label={`Voltar ${SKIP_SECONDS} segundos`}
                    className="shrink-0 rounded-full"
                >
                    <RotateCcw />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={player.changeSpeed}
                    aria-label={`Velocidade ${player.speed}x. Toque para mudar.`}
                    className="min-w-14 shrink-0 rounded-full tabular-nums"
                >
                    {String(player.speed).replace('.', ',')}x
                </Button>
            </div>
            {!bound && resumedFrom > 0 && (
                <p className="mt-2 text-xs text-muted-foreground">
                    Você parou em {formatTime(resumedFrom)}. O áudio continua
                    daqui.{' '}
                    <button
                        type="button"
                        onClick={() => {
                            setResumedFrom(0);
                            setIdlePosition(0);
                            player.play(track);
                            player.seekTo(0);
                        }}
                        className="font-medium text-primary underline-offset-2 hover:underline"
                    >
                        Ouvir do início
                    </button>
                </p>
            )}
            {bound && player.failed && (
                <p className="mt-2 text-sm text-destructive">
                    Não foi possível tocar o áudio. Confira a conexão e tente de
                    novo.
                </p>
            )}
        </div>
    );
}
