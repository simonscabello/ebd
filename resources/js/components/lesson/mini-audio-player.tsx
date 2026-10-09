import { Link } from '@inertiajs/react';
import { Pause, Play, X } from 'lucide-react';
import { useEffect } from 'react';
import { useAudioPlayer } from '@/components/lesson/audio-player-provider';
import { formatTime } from '@/components/lesson/lesson-audio';
import { cn } from '@/lib/utils';

/** Altura reservada no fim da página enquanto o mini player está aberto. */
const RESERVED = '5rem';

/**
 * Mini player fixo acima do menu inferior: aparece quando há um estudo no
 * player e o player da própria lição não está na tela (a pessoa rolou ou
 * foi para outra página). Toca, pausa, volta à lição ou fecha.
 */
export function MiniAudioPlayer() {
    const player = useAudioPlayer();
    const { track } = player;
    const visible = track !== null && !player.inlineVisible;

    // Reserva espaço para o fim da página não ficar escondido atrás dele.
    useEffect(() => {
        const root = document.documentElement;

        if (visible) {
            root.style.setProperty('--app-mini-player', RESERVED);
        } else {
            root.style.removeProperty('--app-mini-player');
        }
    }, [visible]);

    useEffect(
        () => () => {
            document.documentElement.style.removeProperty('--app-mini-player');
        },
        [],
    );

    if (!visible || !track) {
        return null;
    }

    const progress =
        player.duration > 0
            ? Math.min(100, (player.current / player.duration) * 100)
            : 0;
    const info = (
        <>
            <span className="block truncate text-sm font-medium">
                {track.title}
            </span>
            <span className="block text-xs text-muted-foreground tabular-nums">
                {player.failed
                    ? 'Não foi possível tocar. Tente de novo.'
                    : `Ouvir estudo · ${formatTime(player.current)}${
                          player.duration > 0
                              ? ` / ${formatTime(player.duration)}`
                              : ''
                      }`}
            </span>
        </>
    );

    return (
        <div
            role="region"
            aria-label="Áudio do estudo"
            className="fixed inset-x-3 bottom-[calc(var(--app-bottom-nav)+0.5rem)] z-30 mx-auto max-w-md overflow-hidden rounded-2xl border bg-card/95 shadow-lg backdrop-blur md:bottom-4 print:hidden"
        >
            <div className="h-0.5 bg-muted" aria-hidden>
                <div
                    className="h-full bg-primary transition-[width] duration-300"
                    style={{ width: `${progress}%` }}
                />
            </div>
            <div className="flex items-center gap-3 py-2 pr-1.5 pl-2.5">
                <button
                    type="button"
                    onClick={() =>
                        player.playing ? player.pause() : player.play(track)
                    }
                    aria-label={player.playing ? 'Pausar' : 'Continuar ouvindo'}
                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    {player.playing ? (
                        <Pause className="size-4 fill-current" />
                    ) : (
                        <Play className="ml-0.5 size-4 fill-current" />
                    )}
                </button>
                {track.href ? (
                    <Link
                        href={`${track.href}#ouvir`}
                        className="min-w-0 flex-1 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {info}
                    </Link>
                ) : (
                    <div className="min-w-0 flex-1">{info}</div>
                )}
                <button
                    type="button"
                    onClick={player.close}
                    aria-label="Fechar o áudio"
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    )}
                >
                    <X className="size-5" />
                </button>
            </div>
        </div>
    );
}
