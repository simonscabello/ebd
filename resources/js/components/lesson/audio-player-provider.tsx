import { createContext, useContext, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

export const SPEEDS = [1, 1.25, 1.5, 2, 0.75];
export const SKIP_SECONDS = 15;
const SPEED_KEY = 'ebd:audio-speed';

/** Áudio que o player toca: o estudo narrado de uma lição. */
export type AudioTrack = {
    src: string;
    title: string;
    /** Página da lição, para o mini player levar de volta ao player. */
    href: string | null;
    knownDuration: number | null;
};

type AudioPlayerContextValue = {
    track: AudioTrack | null;
    playing: boolean;
    current: number;
    duration: number;
    speed: number;
    failed: boolean;
    /** O player da própria lição está na tela (o mini player se esconde). */
    inlineVisible: boolean;
    play: (track: AudioTrack) => void;
    pause: () => void;
    seekTo: (time: number) => void;
    changeSpeed: () => void;
    close: () => void;
    setInlineVisible: (src: string, visible: boolean) => void;
};

const AudioPlayerContext = createContext<AudioPlayerContextValue | null>(null);

export function useAudioPlayer(): AudioPlayerContextValue {
    const value = useContext(AudioPlayerContext);

    if (!value) {
        throw new Error('useAudioPlayer precisa do AudioPlayerProvider.');
    }

    return value;
}

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

/** Onde a pessoa parou neste áudio (0 se não há posição guardada). */
export function savedPosition(src: string): number {
    const saved = readNumber(positionKey(src));

    return saved > 5 ? saved : 0;
}

function savePosition(src: string, time: number): void {
    writeStorage(positionKey(src), time > 5 ? String(Math.floor(time)) : null);
}

/**
 * Um só <audio> para o app inteiro, fora das páginas: o estudo continua
 * tocando quando a pessoa navega (o mini player mostra o controle). Também
 * lembra no aparelho onde ela parou e a velocidade, e liga os controles da
 * tela de bloqueio.
 */
export function AudioPlayerProvider({ children }: { children: ReactNode }) {
    const ref = useRef<HTMLAudioElement>(null);
    const [track, setTrack] = useState<AudioTrack | null>(null);
    const [playing, setPlaying] = useState(false);
    const [current, setCurrent] = useState(0);
    const [duration, setDuration] = useState(0);
    const [speed, setSpeed] = useState(1);
    const [failed, setFailed] = useState(false);
    const [inlineSrc, setInlineSrc] = useState<string | null>(null);
    // Posição a aplicar quando o áudio carregar; última posição gravada.
    const pendingResume = useRef(0);
    const lastSaved = useRef(0);
    const trackRef = useRef<AudioTrack | null>(null);
    trackRef.current = track;

    useEffect(() => {
        const savedSpeed = readNumber(SPEED_KEY);

        if (SPEEDS.includes(savedSpeed)) {
            setSpeed(savedSpeed);
        }
    }, []);

    const persist = (time: number) => {
        const src = trackRef.current?.src;

        if (src) {
            lastSaved.current = time;
            savePosition(src, time);
        }
    };

    const startPlayback = (element: HTMLAudioElement) => {
        setFailed(false);
        element.play().catch(() => setFailed(true));
    };

    const play = (next: AudioTrack) => {
        const element = ref.current;

        if (!element) {
            return;
        }

        if (trackRef.current?.src !== next.src) {
            if (trackRef.current && element.currentTime > 0) {
                persist(element.currentTime);
            }

            const resume = savedPosition(next.src);
            pendingResume.current = resume;
            lastSaved.current = resume;
            trackRef.current = next;
            setTrack(next);
            setCurrent(resume);
            setDuration(next.knownDuration ?? 0);
            element.src = next.src;
        } else if (trackRef.current !== next) {
            trackRef.current = next;
            setTrack(next);
        }

        startPlayback(element);
    };

    const pause = () => ref.current?.pause();

    const seekTo = (time: number) => {
        const element = ref.current;
        const known = element?.duration;
        const max = known && Number.isFinite(known) ? known : time;
        const target = Math.max(0, Math.min(time, max));
        setCurrent(target);
        pendingResume.current = 0;

        if (element && trackRef.current) {
            element.currentTime = target;
        }

        persist(target);
    };

    const changeSpeed = () => {
        const next = SPEEDS[(SPEEDS.indexOf(speed) + 1) % SPEEDS.length];
        setSpeed(next);
        writeStorage(SPEED_KEY, String(next));

        if (ref.current) {
            ref.current.playbackRate = next;
        }
    };

    const close = () => {
        const element = ref.current;

        if (element) {
            if (!element.ended && element.currentTime > 0) {
                persist(element.currentTime);
            }

            element.pause();
            element.removeAttribute('src');
            element.load();
        }

        trackRef.current = null;
        setTrack(null);
        setPlaying(false);
        setCurrent(0);
        setFailed(false);
    };

    const setInlineVisible = (src: string, visible: boolean) => {
        setInlineSrc((prev) => (visible ? src : prev === src ? null : prev));
    };

    // Ao fechar a aba ou ir para outro app.
    useEffect(() => {
        const flush = () => {
            const element = ref.current;

            if (element && element.currentTime > 0 && !element.ended) {
                persist(element.currentTime);
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
            window.removeEventListener('pagehide', flush);
            document.removeEventListener('visibilitychange', onHidden);
        };
    }, []);

    // Controles na tela de bloqueio e na central de mídia do celular.
    const actions = useRef({ seekTo, pause });
    actions.current = { seekTo, pause };

    useEffect(() => {
        if (!('mediaSession' in navigator)) {
            return;
        }

        const offset = (d: MediaSessionActionDetails, sign: 1 | -1) =>
            actions.current.seekTo(
                (ref.current?.currentTime ?? 0) +
                    sign * (d.seekOffset ?? SKIP_SECONDS),
            );
        const handlers: [MediaSessionAction, MediaSessionActionHandler][] = [
            ['play', () => ref.current && startPlayback(ref.current)],
            ['pause', () => actions.current.pause()],
            ['seekbackward', (d) => offset(d, -1)],
            ['seekforward', (d) => offset(d, 1)],
            [
                'seekto',
                (d) =>
                    d.seekTime !== undefined &&
                    actions.current.seekTo(d.seekTime),
            ],
        ];

        for (const [action, handler] of handlers) {
            try {
                navigator.mediaSession.setActionHandler(action, handler);
            } catch {
                // Ação não suportada neste navegador.
            }
        }
    }, []);

    useEffect(() => {
        if (!('mediaSession' in navigator)) {
            return;
        }

        navigator.mediaSession.metadata = track
            ? new MediaMetadata({
                  title: track.title,
                  artist: 'Ouvir estudo · EBD',
              })
            : null;
    }, [track]);

    const value: AudioPlayerContextValue = {
        track,
        playing,
        current,
        duration,
        speed,
        failed,
        inlineVisible: track !== null && inlineSrc === track.src,
        play,
        pause,
        seekTo,
        changeSpeed,
        close,
        setInlineVisible,
    };

    return (
        <AudioPlayerContext.Provider value={value}>
            {children}
            <audio
                ref={ref}
                preload="metadata"
                onPlay={() => setPlaying(true)}
                onPause={(e) => {
                    setPlaying(false);

                    if (
                        !e.currentTarget.ended &&
                        e.currentTarget.currentTime > 0
                    ) {
                        persist(e.currentTarget.currentTime);
                    }
                }}
                onEnded={() => {
                    setPlaying(false);
                    persist(0);
                }}
                onTimeUpdate={(e) => {
                    const time = e.currentTarget.currentTime;

                    if (pendingResume.current > 0) {
                        return;
                    }

                    setCurrent(time);

                    if (Math.abs(time - lastSaved.current) >= 5) {
                        persist(time);
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
                        Number.isFinite(element.duration) &&
                        resume > element.duration - 10
                    ) {
                        setCurrent(0);
                        persist(0);
                    } else if (resume > 0) {
                        element.currentTime = resume;
                    }

                    element.playbackRate = speed;
                }}
                onError={() => {
                    if (trackRef.current) {
                        setFailed(true);
                    }
                }}
            />
        </AudioPlayerContext.Provider>
    );
}
