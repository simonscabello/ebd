import { Drawer } from 'flowbite';
import type { DrawerInterface } from 'flowbite';
import { X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Duração da subida/descida (duration-200) com uma folga. */
const EXIT_MS = 260;

/**
 * Painel que sobe da parte de baixo da tela (Drawer do Flowbite, controlado
 * pelo React). O conteúdo continua montado quando fechado, então o estado
 * interno (ex.: a chamada) não se perde entre uma abertura e outra.
 *
 * m-0: dentro de um contêiner space-y-* o painel herdava margin-bottom e,
 * mesmo "fora da tela", subia 32px; o cabeçalho ("Leitura ✕") aparecia por
 * cima do menu inferior no Início e em Leituras da semana. Além disso, fechado ele termina a
 * descida e fica invisível e inerte, sem depender de estar fora da tela.
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
    // O Flowbite troca as classes do painel na mão; o React reescreveria o
    // atributo class inteiro. Por isso o "escondido" vai por style/inert.
    const [exited, setExited] = useState(!open);
    const concealed = !open && exited;

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

        if (open) {
            setExited(false);

            if (!instance.isVisible()) {
                opener.current = document.activeElement as HTMLElement | null;
                instance.show();
                panel.current?.focus();
            }

            return;
        }

        // Fechar pelo Esc ou tocando fora já passou pelo hide() do Flowbite.
        if (instance.isVisible()) {
            instance.hide();
            opener.current?.focus();
        }

        const timer = window.setTimeout(() => setExited(true), EXIT_MS);

        return () => window.clearTimeout(timer);
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
            onTransitionEnd={(event) => {
                if (event.target === event.currentTarget && !open) {
                    setExited(true);
                }
            }}
            inert={!open}
            style={concealed ? { visibility: 'hidden' } : undefined}
            aria-labelledby={`${id}-title`}
            className={cn(
                'fixed inset-x-0 bottom-0 z-50 m-0 flex max-h-[85svh] w-full translate-y-full flex-col rounded-t-3xl border-t bg-card shadow-2xl outline-none duration-200 ease-out motion-reduce:transition-none',
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
