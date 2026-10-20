import { Head } from '@inertiajs/react';
import { CopyField } from '@/components/site/copy-field';
import { brand } from '@/lib/brand';

type Props = {
    pixKey: string;
    /** BR Code estático, sem valor ("Pix copia e cola"). */
    pixPayload: string;
    /** QR Code do mesmo BR Code, em SVG (data URI). */
    pixQr: string;
};

/**
 * "Apoie a Didaché": o app é gratuito e mantido por ofertas voluntárias.
 * Pix sem valor fixo: quem contribui escolhe o valor no banco.
 */
export default function Support({ pixKey, pixPayload, pixQr }: Props) {
    return (
        <>
            <Head title={`Apoie a ${brand.name}`} />

            <div className="mx-auto max-w-4xl px-4 py-12 sm:px-6 md:py-16">
                <p className="text-sm font-medium text-primary">
                    Ofertas voluntárias
                </p>
                <h1 className="mt-2 font-serif text-4xl font-semibold tracking-tight">
                    Apoie a {brand.name}
                </h1>
                <div className="mt-5 max-w-2xl space-y-3 text-lg text-muted-foreground">
                    <p>
                        A {brand.name} é gratuita para todas as igrejas, sem
                        plano pago nem anúncio. O servidor e os serviços que o
                        app usa são mantidos por ofertas voluntárias.
                    </p>
                    <p>
                        Se ela tem servido à sua classe, você pode contribuir
                        com qualquer valor, uma vez ou quando quiser.
                    </p>
                </div>

                <div className="mt-10 grid gap-8 rounded-2xl border border-border bg-card p-5 sm:p-8 md:grid-cols-[auto_1fr] md:items-start">
                    <div className="mx-auto w-full max-w-[15rem] rounded-xl bg-white p-3">
                        <img
                            src={pixQr}
                            alt="QR Code do Pix para apoiar a Didaché"
                            width={240}
                            height={240}
                            className="block h-auto w-full"
                        />
                    </div>

                    <div className="min-w-0 space-y-6">
                        <CopyField
                            label="Pix copia e cola"
                            value={pixPayload}
                            copyLabel="Copiar código"
                        />
                        <CopyField
                            label="Chave Pix (aleatória)"
                            value={pixKey}
                            copyLabel="Copiar chave"
                        />
                    </div>
                </div>

                <h2 className="mt-12 font-serif text-2xl font-semibold">
                    Como fazer
                </h2>
                <ol className="mt-4 max-w-2xl list-decimal space-y-2 pl-5 text-muted-foreground marker:font-medium marker:text-foreground">
                    <li>Abra o app do seu banco e entre na área do Pix.</li>
                    <li>
                        Escolha ler o QR Code ou pagar com Pix copia e cola (ou
                        transferir para a chave).
                    </li>
                    <li>Digite o valor que quiser e confirme.</li>
                </ol>
                <p className="mt-8 text-sm text-muted-foreground">
                    Obrigado por ajudar a manter a {brand.name} no ar para
                    outras igrejas.
                </p>
            </div>
        </>
    );
}
