import { router, usePoll } from '@inertiajs/react';
import { AlertCircle, AudioLines } from 'lucide-react';
import { useEffect, useState } from 'react';
import { LessonAudioPlayer } from '@/components/lesson/lesson-audio';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { audio as generateAudio } from '@/routes/admin/lessons';
import type { LessonAudioManage } from '@/types';

/**
 * Gestão do áudio do estudo: gerar, regenerar quando o estudo mudou e
 * acompanhar a geração, que roda no servidor. Mostra o player para conferir.
 */
export function LessonAudioManager({
    lessonId,
    audio,
}: {
    lessonId: number;
    audio: LessonAudioManage;
}) {
    const generating = audio.status === 'generating';
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

    const button = (label: string) => (
        <Button variant="outline" onClick={request}>
            <AudioLines /> {label}
        </Button>
    );

    return (
        <div className="space-y-3">
            {audio.file && (
                <LessonAudioPlayer
                    src={audio.file.url}
                    knownDuration={audio.file.duration}
                />
            )}

            <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                {generating || requesting ? (
                    <>
                        <Button variant="outline" disabled>
                            <Spinner /> Gerando áudio…
                        </Button>
                        <span className="text-muted-foreground">
                            Pode levar alguns minutos. Você pode sair desta
                            página.
                        </span>
                    </>
                ) : !audio.has_content ? (
                    <p className="rounded-2xl border border-dashed p-4 text-muted-foreground">
                        Escreva o estudo em Dados da lição para gerar o áudio.
                    </p>
                ) : audio.status === 'failed' ? (
                    <>
                        <span className="flex w-full items-start gap-2 text-destructive">
                            <AlertCircle className="mt-0.5 size-4 shrink-0" />
                            {audio.error ?? 'Não foi possível gerar o áudio.'}
                        </span>
                        {button('Tentar de novo')}
                    </>
                ) : audio.stale ? (
                    <>
                        <Badge className="rounded-full bg-amber-500 text-white">
                            Desatualizado
                        </Badge>
                        <span className="min-w-0 flex-1 text-muted-foreground">
                            O estudo mudou depois que o áudio foi gerado. A
                            classe continua ouvindo a versão anterior até você
                            regenerar.
                        </span>
                        {button('Regenerar áudio')}
                    </>
                ) : audio.file ? (
                    <span className="text-muted-foreground">
                        Gerado em {audio.generated_at}. A classe ouve na página
                        da lição, em Estudo.
                    </span>
                ) : (
                    <>
                        {button('Gerar áudio')}
                        <span className="text-muted-foreground">
                            A classe passa a ouvir o estudo na página da lição.
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
        </div>
    );
}
