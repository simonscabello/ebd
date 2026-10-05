import { Headphones, Pause, Play } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

const SPEEDS = [1, 1.25, 1.5, 2, 0.75];

function formatTime(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = String(total % 60).padStart(2, '0');

    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
}

/**
 * "Ouvir estudo": player do áudio narrado do estudo, com play/pause,
 * posição, duração e velocidade. A geração fica na gestão (edição da lição).
 */
export function LessonAudioPlayer({
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
