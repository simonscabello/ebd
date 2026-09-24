import { createInertiaApp } from '@inertiajs/react';
import { useEffect } from 'react';
// Registra o JS dos componentes do Flowbite (drawer, dropdown, tooltip...).
import 'flowbite';
import { ConfirmProvider } from '@/components/confirm-dialog';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { registerServiceWorker } from '@/lib/pwa';
import { dismissSplash } from '@/lib/splash';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'EBD';

/** Tira a tela de abertura depois que a primeira página é pintada. */
function SplashDismisser() {
    useEffect(dismissSplash, []);

    return null;
}

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'lessons/sunday':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('admin/'):
                return [AppLayout, AdminLayout];
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <ConfirmProvider>{app}</ConfirmProvider>
                <Toaster position="top-center" />
                <SplashDismisser />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#2f6b5c',
    },
});

// Aplica tema claro/escuro no carregamento.
initializeTheme();

registerServiceWorker();
