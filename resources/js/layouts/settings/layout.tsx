import type { PropsWithChildren } from 'react';
import { Page } from '@/components/page';

/**
 * Páginas da conta: o perfil (/conta) e as telas que ele abre.
 */
export default function SettingsLayout({ children }: PropsWithChildren) {
    return <Page className="pb-10">{children}</Page>;
}
