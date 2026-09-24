import { Drawer } from 'flowbite';
import type { DrawerInterface } from 'flowbite';
import { X } from 'lucide-react';
import { useEffect, useId, useRef } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Painel que sobe da parte de baixo da tela (Drawer do Flowbite, controlado
 * pelo React). O conteúdo continua montado quando fechado, então o estado
 * interno (ex.: a chamada) não se perde entre uma abertura e outra.
 */
export function BottomSheet({
    open,
    onOpenChange,
    title,
    description,
    children,
    className,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    const id = useId();
    const panel = useRef<HTMLDivElement>(null);
    const drawer = useRef<DrawerInterface | null>(null);
    const opener = useRef<HTMLElement | null>(null);
    const onOpenChangeRef = useRef(onOpenChange);
    onOpenChangeRef.current = onOpenChange;

    useEffect(() => {
        if (!panel.current) {
            return;
        }

        const instance = new Drawer(
            panel.current,
            {
                placement: 'bottom',
                backdrop: true,
                bodyScrolling: false,
                backdropClasses:
                    'fixed inset-0 z-40 bg-backdrop/60 backdrop-blur-[2px]',
                onHide: () => onOpenChangeRef.current(false),
            },
            { id, override: true },
        );
        drawer.current = instance;

        return () => {
            instance.destroyAndRemoveInstance();
            document.body.classList.remove('overflow-hidden');
            drawer.current = null;
        };
    }, [id]);

    useEffect(() => {
        const instance = drawer.current;

        if (!instance) {
            return;
        }

        if (open && !instance.isVisible()) {
            opener.current = document.activeElement as HTMLElement | null;
            instance.show();
            panel.current?.focus();
        } else if (!open && instance.isVisible()) {
            instance.hide();
            opener.current?.focus();
        }
    }, [open]);

    // Mantém o Tab dentro do painel enquanto ele está aberto.
    const trapFocus = (event: React.KeyboardEvent<HTMLDivElement>) => {
        if (event.key !== 'Tab' || !panel.current) {
            return;
        }

        const focusable = Array.from(
            panel.current.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])',
            ),
        );

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    return (
        <div
            ref={panel}
            id={id}
            tabIndex={-1}
            onKeyDown={trapFocus}
            aria-labelledby={`${id}-title`}
            className={cn(
                'fixed z-50 flex max-h-[85svh] w-full translate-y-full flex-col rounded-t-3xl border-t bg-card shadow-2xl outline-none',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3 px-5 pt-4 pb-3">
                <div className="min-w-0">
                    <h2
                        id={`${id}-title`}
                        className="text-lg font-semibold tracking-tight"
                    >
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                <button
                    type="button"
                    onClick={() => onOpenChange(false)}
                    className="flex size-10 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Fechar"
                >
                    <X className="size-5" />
                </button>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto px-5 pb-safe">
                {children}
            </div>
        </div>
    );
}
