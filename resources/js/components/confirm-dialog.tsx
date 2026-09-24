import {
    createContext,
    use,
    useCallback,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ConfirmOptions = {
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    /** Ação destrutiva: botão vermelho. */
    destructive?: boolean;
};

type ConfirmFn = (options: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = createContext<ConfirmFn | null>(null);

/**
 * Substitui o `confirm()` nativo por um diálogo acessível e no visual do app.
 * Uso: `const confirm = useConfirm(); if (await confirm({ title })) { ... }`.
 */
export function ConfirmProvider({ children }: { children: ReactNode }) {
    const [options, setOptions] = useState<ConfirmOptions | null>(null);
    const resolver = useRef<((value: boolean) => void) | null>(null);

    const confirm = useCallback<ConfirmFn>((next) => {
        resolver.current?.(false);

        return new Promise<boolean>((resolve) => {
            resolver.current = resolve;
            setOptions(next);
        });
    }, []);

    const settle = (value: boolean) => {
        resolver.current?.(value);
        resolver.current = null;
        setOptions(null);
    };

    const value = useMemo(() => confirm, [confirm]);

    return (
        <ConfirmContext value={value}>
            {children}
            <Dialog
                open={options !== null}
                onOpenChange={(open) => !open && settle(false)}
            >
                <DialogContent className="sm:max-w-md">
                    {options && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{options.title}</DialogTitle>
                                {options.description && (
                                    <DialogDescription>
                                        {options.description}
                                    </DialogDescription>
                                )}
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    variant="secondary"
                                    onClick={() => settle(false)}
                                >
                                    {options.cancelLabel ?? 'Cancelar'}
                                </Button>
                                <Button
                                    variant={
                                        options.destructive
                                            ? 'destructive'
                                            : 'default'
                                    }
                                    autoFocus
                                    onClick={() => settle(true)}
                                >
                                    {options.confirmLabel ?? 'Confirmar'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </ConfirmContext>
    );
}

export function useConfirm(): ConfirmFn {
    const confirm = use(ConfirmContext);

    if (!confirm) {
        throw new Error(
            'useConfirm precisa estar dentro de <ConfirmProvider>.',
        );
    }

    return confirm;
}
