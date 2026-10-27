import { router } from '@inertiajs/react';
import { AlertCircle, ImagePlus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { LessonCover } from '@/components/lesson/lesson-cover';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy as removeCover,
    store as storeCover,
} from '@/routes/admin/lessons/cover';

/**
 * Capa da lição: escolher a imagem já envia. O servidor recorta no centro e
 * guarda um JPEG quadrado leve, então qualquer foto ou ilustração serve.
 */
export function LessonCoverManager({
    lessonId,
    coverUrl,
}: {
    lessonId: number;
    coverUrl: string | null;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const options = {
        preserveScroll: true,
        onStart: () => {
            setBusy(true);
            setError(null);
        },
        onError: (errors: Record<string, string>) =>
            setError(errors.cover ?? 'Não foi possível salvar a capa.'),
        onFinish: () => {
            setBusy(false);
            if (input.current) input.current.value = '';
        },
    };

    const upload = (file: File | undefined) => {
        if (file) {
            router.post(
                storeCover.url(lessonId),
                { cover: file },
                { ...options, forceFormData: true },
            );
        }
    };

    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
            {coverUrl ? (
                <LessonCover src={coverUrl} className="w-40" />
            ) : (
                <div className="flex aspect-square w-40 shrink-0 items-center justify-center rounded-2xl border border-dashed text-sm text-muted-foreground">
                    Sem capa
                </div>
            )}
            <div className="space-y-3 text-sm">
                <p className="text-muted-foreground">
                    Imagem quadrada de pelo menos 600 × 600 px (JPEG, PNG ou
                    WebP). Aparece no Início, na página da lição, no episódio do
                    podcast e na prévia do link da lição pública.
                </p>
                <div className="flex flex-wrap gap-2">
                    <input
                        ref={input}
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp"
                        className="sr-only"
                        aria-label="Escolher imagem da capa"
                        onChange={(event) => upload(event.target.files?.[0])}
                    />
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => input.current?.click()}
                    >
                        {busy ? <Spinner /> : <ImagePlus />}
                        {coverUrl ? 'Trocar capa' : 'Enviar capa'}
                    </Button>
                    {coverUrl && (
                        <Button
                            variant="ghost"
                            disabled={busy}
                            onClick={() =>
                                router.delete(
                                    removeCover.url(lessonId),
                                    options,
                                )
                            }
                        >
                            <Trash2 /> Remover
                        </Button>
                    )}
                </div>
                {error && (
                    <p className="flex items-start gap-2 text-destructive">
                        <AlertCircle className="mt-0.5 size-4 shrink-0" />
                        {error}
                    </p>
                )}
            </div>
        </div>
    );
}
