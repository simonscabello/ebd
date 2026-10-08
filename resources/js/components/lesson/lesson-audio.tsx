import { Headphones, Pause, Play, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const SPEEDS = [1, 1.25, 1.5, 2, 0.75];
const SPEED_KEY = 'ebd:audio-speed';
const SKIP_SECONDS = 15;

/** A URL muda a cada geração (?v=), então um áudio novo começa do zero. */
function positionKey(src: string): string {
    return `ebd:audio-pos:${src}`;
}

function readNumber(key: string): number {
    try {
        const value = Number(window.localStorage.getItem(key));

        return Number.isFinite(value) ? value : 0;
    } catch {
        return 0;
    }
}

function writeStorage(key: string, value: string | null): void {
    try {
        if (value === null) {
            window.localStorage.removeItem(key);
        } else {
            window.localStorage.setItem(key, value);
        }
    } catch {
        // Navegação privada ou armazenamento cheio: só não lembra.
    }
}

function formatTime(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = String(total % 60).padStart(2, '0');

    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
}

/**
 * "Ouvir estudo": player do áudio narrado do estudo, com play/pause,
 * posição, duração e velocidade. Lembra no aparelho onde a pessoa parou
 * (e a velocidade), para continuar dali se sair da tela. A geração fica na
 * gestão (edição da lição).
 */
export function LessonAudioPlayer({
    src,
    title = 'Estudo da lição',
    knownDuration,
    highlight = false,
}: {
    src: string;
    /** Título da lição, para os controles da tela de bloqueio. */
    title?: string;
    knownDuration: number | null;
    /** Chegou pelo link "Ouvir" (#ouvir): destaca o player e o play. */
    highlight?: boolean;
}) {
    const ref = useRef<HTMLAudioElement>(null);
    const [playing, setPlaying] = useState(false);
    const [current, setCurrent] = useState(0);
    const [duration, setDuration] = useState(knownDuration ?? 0);
    const [speed, setSpeed] = useState(1);
    const [failed, setFailed] = useState(false);
    // Posição salva da última vez; aplicada quando o áudio carrega.
    const pendingResume = useRef(0);
    const lastSaved = useRef(0);
    const [resumedFrom, setResumedFrom] = useState(0);

    useEffect(() => {
        const saved = readNumber(positionKey(src));
        const savedSpeed = readNumber(SPEED_KEY);

        if (SPEEDS.includes(savedSpeed)) {
            setSpeed(savedSpeed);
        }

        if (saved > 5) {
            pendingResume.current = saved;
            lastSaved.current = saved;
            setCurrent(saved);
            setResumedFrom(saved);
        }
    }, [src]);

    const savePosition = (time: number) => {
        lastSaved.current = time;
        writeStorage(
            positionKey(src),
            time > 5 ? String(Math.floor(time)) : null,
        );
    };

    // Ao sair da tela (trocar de página, fechar a aba, ir para outro app).
    useEffect(() => {
        const element = ref.current;
        const flush = () => {
            if (element && element.currentTime > 0 && !element.ended) {
                savePosition(element.currentTime);
            }
        };
        const onHidden = () => {
            if (document.visibilityState === 'hidden') {
                flush();
            }
        };

        window.addEventListener('pagehide', flush);
        document.addEventListener('visibilitychange', onHidden);

        return () => {
            flush();
            window.removeEventListener('pagehide', flush);
            document.removeEventListener('visibilitychange', onHidden);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [src]);

    const seekTo = (time: number) => {
        const element = ref.current;
        const max = duration || element?.duration || time;
        const target = Math.max(0, Math.min(time, max));
        setCurrent(target);
        pendingResume.current = 0;

        if (element) {
            element.currentTime = target;
        }
    };

    // Controles na tela de bloqueio e na central de mídia do celular.
    useEffect(() => {
        if (!('mediaSession' in navigator)) {
            return;
        }

        navigator.mediaSession.metadata = new MediaMetadata({
            title,
            artist: 'Ouvir estudo · EBD',
        });

        const handlers: [MediaSessionAction, MediaSessionActionHandler][] = [
            ['play', () => ref.current?.play().catch(() => setFailed(true))],
            ['pause', () => ref.current?.pause()],
            [
                'seekbackward',
                (d) =>
                    seekTo(
                        (ref.current?.currentTime ?? 0) -
                            (d.seekOffset ?? SKIP_SECONDS),
                    ),
            ],
            [
                'seekforward',
                (d) =>
                    seekTo(
                        (ref.current?.currentTime ?? 0) +
                            (d.seekOffset ?? SKIP_SECONDS),
                    ),
            ],
            ['seekto', (d) => d.seekTime !== undefined && seekTo(d.seekTime)],
        ];

        for (const [action, handler] of handlers) {
            try {
                navigator.mediaSession.setActionHandler(action, handler);
            } catch {
                // Ação não suportada neste navegador.
            }
        }

        return () => {
            for (const [action] of handlers) {
                try {
                    navigator.mediaSession.setActionHandler(action, null);
                } catch {
                    // idem
                }
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [title, duration]);

    const toggle = () => {
        const element = ref.current;

        if (!element) {
            return;
        }

        if (element.paused) {
            setFailed(false);
            element.play().catch(() => setFailed(true));
        } else {
            element.pause();
        }
    };

    const changeSpeed = () => {
        const next = SPEEDS[(SPEEDS.indexOf(speed) + 1) % SPEEDS.length];
        setSpeed(next);
        writeStorage(SPEED_KEY, String(next));

        if (ref.current) {
            ref.current.playbackRate = next;
        }
    };

    return (
        <div
            className={cn(
                'rounded-2xl border bg-card p-3.5 shadow-xs transition-shadow duration-500',
                highlight &&
                    'ring-2 ring-primary ring-offset-2 ring-offset-background',
            )}
        >
            <audio
                ref={ref}
                src={src}
                preload="metadata"
                onPlay={() => {
                    setPlaying(true);
                    setResumedFrom(0);
                }}
                onPause={(e) => {
                    setPlaying(false);

                    if (!e.currentTarget.ended) {
                        savePosition(e.currentTarget.currentTime);
                    }
                }}
                onEnded={() => {
                    setPlaying(false);
                    setResumedFrom(0);
                    savePosition(0);
                }}
                onTimeUpdate={(e) => {
                    const time = e.currentTarget.currentTime;
                    setCurrent(time);

                    if (Math.abs(time - lastSaved.current) >= 5) {
                        savePosition(time);
                    }
                }}
                onLoadedMetadata={(e) => {
                    const element = e.currentTarget;

                    if (Number.isFinite(element.duration)) {
                        setDuration(element.duration);
                    }

                    // Perto do fim não retoma: a pessoa já ouviu tudo.
                    const resume = pendingResume.current;
                    pendingResume.current = 0;

                    if (
                        resume > 0 &&
                        !(
                            Number.isFinite(element.duration) &&
                            resume > element.duration - 10
                        )
                    ) {
                        element.currentTime = resume;
                    } else if (resume > 0) {
                        setCurrent(0);
                        setResumedFrom(0);
                        savePosition(0);
                    }

                    element.playbackRate = speed;
                }}
                onError={() => setFailed(true)}
            />
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
                    onClick={changeSpeed}
                    aria-label={`Velocidade ${speed}x. Toque para mudar.`}
                    className="min-w-14 shrink-0 rounded-full tabular-nums"
                >
                    {String(speed).replace('.', ',')}x
                </Button>
            </div>
            {resumedFrom > 0 && (
                <p className="mt-2 text-xs text-muted-foreground">
                    Você parou em {formatTime(resumedFrom)}. O áudio continua
                    daqui.{' '}
                    <button
                        type="button"
                        onClick={() => {
                            seekTo(0);
                            setResumedFrom(0);
                            savePosition(0);
                        }}
                        className="font-medium text-primary underline-offset-2 hover:underline"
                    >
                        Ouvir do início
                    </button>
                </p>
            )}
            {failed && (
                <p className="mt-2 text-sm text-destructive">
                    Não foi possível tocar o áudio. Confira a conexão e tente de
                    novo.
                </p>
            )}
        </div>
    );
}
