import { cn } from '@/lib/utils';

export function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

/**
 * Foto de perfil ou, sem foto, as iniciais do nome.
 */
export function UserAvatar({
    name,
    src,
    className,
}: {
    name: string;
    src: string | null;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-accent text-sm font-semibold text-accent-foreground',
                className,
            )}
        >
            {src ? (
                <img
                    src={src}
                    alt=""
                    className="size-full object-cover"
                    loading="lazy"
                />
            ) : (
                initials(name)
            )}
        </span>
    );
}
