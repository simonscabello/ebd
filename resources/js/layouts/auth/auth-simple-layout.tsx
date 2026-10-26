import { brand } from '@/lib/brand';
import { Link, usePage } from '@inertiajs/react';
import { AppLogoMark } from '@/components/app-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { church, auth } = usePage().props;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <AppLogoMark className="size-12" />
                            <span className="text-sm text-muted-foreground">
                                {/* Visitante: sem igreja ainda (a principal confundiria quem é de outra). */}
                                {auth.user
                                    ? `${brand.name} · ${church.name}`
                                    : brand.name}
                            </span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="font-serif text-2xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
