import { router, usePoll } from '@inertiajs/react';
import { AlertCircle, AudioLines, Headphones, Pause, Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { audio as generateAudio } from '@/routes/admin/lessons';
import type { LessonAudio as LessonAudioData } from '@/types';

const SPEEDS = [1, 1.25, 1.5, 2, 0.75];

function formatTime(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = String(total % 60).padStart(2, '0');

    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
}

/**
 * "Ouvir estudo": player do áudio narrado do estudo. Quem gerencia a lição
 * também vê aqui o botão de gerar (ou regenerar, quando o estudo mudou) e o
 * andamento da geração, que roda no servidor.
 */
export function LessonAudio({
    audio,
    lessonId,
}: {
    audio: LessonAudioData | null;
    lessonId: number;
}) {
    const manage = audio?.manage;
    const generating = manage?.status === 'generating';
    const [requesting, setRequesting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Enquanto o servidor gera, a página confere o andamento a cada 5 s.
    const { start, stop } = usePoll(
        5000,
        { only: ['audio'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (generating) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [generating, start, stop]);

    if (!audio) {
        return null;
    }

    const request = () => {
        if (requesting || generating) {
            return;
        }

        router.post(generateAudio.url(lessonId), undefined, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                setRequesting(true);
                setError(null);
            },
            onError: (errors) =>
                setError(
                    errors.audio ?? 'Não foi possível pedir o áudio agora.',
                ),
            onFinish: () => setRequesting(false),
        });
    };

    return (
        <div className="mb-6 space-y-2">
            {audio.file && (
                <AudioPlayer
                    src={audio.file.url}
                    knownDuration={audio.file.duration}
                />
            )}

            {manage && (
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-2xl bg-muted/60 px-4 py-3 text-sm">
                    {generating || requesting ? (
                        <>
                            <Button size="sm" variant="outline" disabled>
                                <Spinner /> Gerando áudio…
                            </Button>
                            <span className="text-muted-foreground">
                                Pode levar alguns minutos. Você pode sair desta
                                página.
                            </span>
                        </>
                    ) : manage.status === 'failed' ? (
                        <>
                            <span className="flex min-w-0 flex-1 items-start gap-2 text-destructive">
                                <AlertCircle className="mt-0.5 size-4 shrink-0" />
                                {manage.error ??
                                    'Não foi possível gerar o áudio.'}
                            </span>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={request}
                            >
                                <AudioLines /> Tentar de novo
                            </Button>
                        </>
                    ) : manage.stale ? (
                        <>
                            <Badge className="rounded-full bg-amber-500 text-white">
                                Desatualizado
                            </Badge>
                            <span className="min-w-0 flex-1 text-muted-foreground">
                                O estudo mudou depois que o áudio foi gerado.
                            </span>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={request}
                            >
                                <AudioLines /> Regenerar áudio
                            </Button>
                        </>
                    ) : audio.file ? (
                        <span className="text-muted-foreground">
                            Áudio gerado em {manage.generated_at}. Fica
                            disponível para a classe junto com a lição.
                        </span>
                    ) : (
                        <>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={request}
                            >
                                <AudioLines /> Gerar áudio
                            </Button>
                            <span className="text-muted-foreground">
                                Narração do estudo para ouvir no celular.
                            </span>
                        </>
                    )}
                    {error && (
                        <p className="flex w-full items-start gap-2 text-destructive">
                            <AlertCircle className="mt-0.5 size-4 shrink-0" />
                            {error}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

function AudioPlayer({
    src,
    knownDuration,
}: {
    src: string;
    knownDuration: number | null;
}) {
    const ref = useRef<HTMLAudioElement>(null);
    const [playing, setPlaying] = useState(false);
    const [current, setCurrent] = useState(0);
    const [duration, setDuration] = useState(knownDuration ?? 0);
    const [speed, setSpeed] = useState(1);
    const [failed, setFailed] = useState(false);

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

        if (ref.current) {
            ref.current.playbackRate = next;
        }
    };

    return (
        <div className="rounded-2xl border bg-card p-3.5 shadow-xs">
            <audio
                ref={ref}
                src={src}
                preload="metadata"
                onPlay={() => setPlaying(true)}
                onPause={() => setPlaying(false)}
                onEnded={() => setPlaying(false)}
                onTimeUpdate={(e) => setCurrent(e.currentTarget.currentTime)}
                onLoadedMetadata={(e) => {
                    if (Number.isFinite(e.currentTarget.duration)) {
                        setDuration(e.currentTarget.duration);
                    }

                    e.currentTarget.playbackRate = speed;
                }}
                onError={() => setFailed(true)}
            />
            <div className="flex items-center gap-3.5">
                <button
                    type="button"
                    onClick={toggle}
                    aria-label={playing ? 'Pausar' : 'Ouvir estudo'}
                    className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
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
                        onChange={(e) => {
                            const time = Number(e.target.value);
                            setCurrent(time);

                            if (ref.current) {
                                ref.current.currentTime = time;
                            }
                        }}
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
                    size="sm"
                    onClick={changeSpeed}
                    aria-label={`Velocidade ${speed}x. Toque para mudar.`}
                    className="min-w-14 shrink-0 rounded-full tabular-nums"
                >
                    {String(speed).replace('.', ',')}x
                </Button>
            </div>
            {failed && (
                <p className="mt-2 text-sm text-destructive">
                    Não foi possível tocar o áudio. Confira a conexão e tente de
                    novo.
                </p>
            )}
        </div>
    );
}
