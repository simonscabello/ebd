import {
    BookText,
    Download,
    ExternalLink,
    FileText,
    Headphones,
    Link2,
    PlayCircle,
    Star,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { LessonMaterial, MaterialTypeValue } from '@/types';

const icons: Record<MaterialTypeValue, LucideIcon> = {
    pdf: FileText,
    file: FileText,
    link: Link2,
    video: PlayCircle,
    audio: Headphones,
    reference: BookText,
};

export function MaterialIcon({
    type,
    className,
}: {
    type: MaterialTypeValue;
    className?: string;
}) {
    const Icon = icons[type];

    return <Icon className={className} />;
}

/**
 * Cartão de material pensado para o celular: um toque abre o conteúdo.
 * PDFs abrem no visualizador do navegador; "Baixar" fica como ação secundária.
 */
export function MaterialCard({ material }: { material: LessonMaterial }) {
    const href = material.file?.open_url ?? material.url ?? undefined;
    const external = !material.file && !!material.url;

    const meta = [
        material.file?.extension || material.type_label,
        material.file?.size,
    ]
        .filter(Boolean)
        .join(' · ');

    const body = (
        <>
            <span
                className={cn(
                    'flex size-12 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground',
                    material.type === 'pdf' &&
                        'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300',
                )}
            >
                <MaterialIcon type={material.type} className="size-6" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                    {material.is_primary && (
                        <Star
                            className="size-3.5 fill-current text-amber-500"
                            aria-label="Material principal"
                        />
                    )}
                    {meta}
                </span>
                <span className="block leading-snug font-medium text-pretty">
                    {material.title}
                </span>
                {material.description && (
                    <span className="mt-0.5 block text-sm text-pretty text-muted-foreground">
                        {material.description}
                    </span>
                )}
            </span>
            {href && (
                <ExternalLink
                    className="size-4 shrink-0 self-center text-muted-foreground"
                    aria-hidden
                />
            )}
        </>
    );

    const cardClass =
        'flex w-full items-start gap-3.5 rounded-2xl border bg-card p-3.5 text-left shadow-xs transition-colors';

    return (
        <div className="space-y-2">
            {material.embed_url && (
                <div className="aspect-video overflow-hidden rounded-2xl border bg-black">
                    <iframe
                        src={material.embed_url}
                        title={material.title}
                        className="size-full"
                        loading="lazy"
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                        allowFullScreen
                        referrerPolicy="strict-origin-when-cross-origin"
                    />
                </div>
            )}

            {href ? (
                <a
                    href={href}
                    target="_blank"
                    rel={external ? 'noopener noreferrer nofollow' : 'noopener'}
                    className={cn(
                        cardClass,
                        'hover:border-primary/40 hover:bg-accent/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    )}
                >
                    {body}
                </a>
            ) : (
                <div className={cardClass}>{body}</div>
            )}

            {material.type === 'audio' && material.file && (
                <audio
                    controls
                    preload="none"
                    src={material.file.open_url}
                    className="w-full"
                >
                    Seu navegador não reproduz áudio.
                </audio>
            )}

            {material.file && (
                <div className="flex justify-end">
                    <a
                        href={material.file.download_url}
                        className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <Download className="size-4" /> Baixar
                    </a>
                </div>
            )}
        </div>
    );
}
