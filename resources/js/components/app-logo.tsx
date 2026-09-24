import { cn } from '@/lib/utils';

/** O ícone do app (o mesmo da tela de início do celular). */
export function AppLogoMark({ className }: { className?: string }) {
    return (
        <img
            src="/icons/icon.svg"
            alt=""
            aria-hidden
            className={cn('size-9 shrink-0 rounded-[22%]', className)}
        />
    );
}

export default function AppLogo({ churchName }: { churchName?: string }) {
    return (
        <span className="flex items-center gap-2.5">
            <AppLogoMark />
            <span className="flex flex-col leading-tight">
                <span className="text-[15px] font-semibold tracking-tight">
                    EBD
                </span>
                {churchName && (
                    <span className="max-w-[14rem] truncate text-xs text-muted-foreground">
                        {churchName}
                    </span>
                )}
            </span>
        </span>
    );
}
