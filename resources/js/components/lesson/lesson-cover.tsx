import { cn } from '@/lib/utils';

/** Capa da lição (quadrada); a borda separa o fundo claro da imagem no tema escuro. */
export function LessonCover({
    src,
    className,
}: {
    src: string;
    className?: string;
}) {
    return (
        <img
            src={src}
            alt=""
            loading="lazy"
            className={cn(
                'aspect-square shrink-0 rounded-2xl object-cover ring-1 ring-border',
                className,
            )}
        />
    );
}
