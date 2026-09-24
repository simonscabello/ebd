import { createInertiaApp } from '@inertiajs/react';
// Registra o JS dos componentes do Flowbite (drawer, dropdown, tooltip...).
import 'flowbite';
import { ConfirmProvider } from '@/components/confirm-dialog';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { registerServiceWorker } from '@/lib/pwa';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'EBD';

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
