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
            church: {
                name: string;
                can_edit: boolean;
                current_id: number | null;
                choices: { id: number; name: string }[];
            };
            auth: Auth;
            features: { registration: boolean };
            push: { public_key: string | null };
            [key: string]: unknown;
        };
    }
}
