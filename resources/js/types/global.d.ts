import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            church: { name: string };
            auth: Auth;
            features: { registration: boolean };
            [key: string]: unknown;
        };
    }
}
