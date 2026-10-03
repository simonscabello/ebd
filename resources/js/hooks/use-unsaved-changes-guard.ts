import { router } from '@inertiajs/react';
import { useEffect, useId } from 'react';

const MESSAGE = 'Você tem alterações não salvas. Sair mesmo assim?';

/** Formulários com alteração pendente nesta página (um aviso só para todos). */
const dirty = new Set<string>();
let detach: (() => void) | null = null;

function onBeforeUnload(event: BeforeUnloadEvent) {
    event.preventDefault();
}

function attach() {
    window.addEventListener('beforeunload', onBeforeUnload);

    const removeRouterListener = router.on('before', (event) => {
        const { visit } = event.detail;

        // Só a navegação para outra página: envios, recargas parciais e
        // pré-carregamentos não tiram a pessoa daqui.
        if (
            visit.method !== 'get' ||
            visit.prefetch ||
            visit.url.pathname === window.location.pathname
        ) {
            return;
        }

        if (window.confirm(MESSAGE)) {
            // Confirmou: a página nova não herda o aviso.
            dirty.clear();
            release();

            return;
        }

        event.preventDefault();
    });

    detach = () => {
        window.removeEventListener('beforeunload', onBeforeUnload);
        removeRouterListener();
    };
}

function release() {
    detach?.();
    detach = null;
}

/**
 * Avisa antes de sair da página (link, voltar, fechar a aba) enquanto o
 * formulário tem alteração não salva.
 */
export function useUnsavedChangesGuard(isDirty: boolean): void {
    const id = useId();

    useEffect(() => {
        if (!isDirty) {
            return;
        }

        dirty.add(id);

        if (!detach) {
            attach();
        }

        return () => {
            dirty.delete(id);

            if (dirty.size === 0) {
                release();
            }
        };
    }, [id, isDirty]);
}
