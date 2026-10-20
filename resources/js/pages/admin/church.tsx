import { Head, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { Field } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes/admin';
import { update } from '@/routes/admin/church';

/** Hora de agora no fuso escolhido, para conferir antes de salvar. */
function localTime(timezone: string): string | null {
    try {
        return new Intl.DateTimeFormat('pt-BR', {
            timeZone: timezone,
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date());
    } catch {
        return null;
    }
}

export default function ChurchSettings({
    settings: church,
    timezones,
}: {
    settings: { id: number; name: string; timezone: string };
    timezones: string[];
}) {
    const form = useForm({ name: church.name, timezone: church.timezone });
    const { data, setData, errors, processing } = form;
    const now = localTime(data.timezone);

    return (
        <>
            <Head title="Dados da igreja" />
            <Page>
                <PageHeader
                    breadcrumbs={[
                        { title: 'Painel', href: dashboard.url() },
                        { title: 'Dados da igreja' },
                    ]}
                    title="Dados da igreja"
                />
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(update());
                    }}
                    className="space-y-6"
                >
                    <Field label="Nome" htmlFor="name" error={errors.name}>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            maxLength={120}
                        />
                    </Field>
                    <Field
                        label="Fuso horário"
                        htmlFor="timezone"
                        error={errors.timezone}
                        hint={`"Hoje", a semana de leitura e os lembretes (9h, 20h e véspera às 8h) seguem este fuso.${now ? ` Agora lá: ${now}.` : ''}`}
                    >
                        <NativeSelect
                            id="timezone"
                            value={data.timezone}
                            onChange={(e) =>
                                setData('timezone', e.target.value)
                            }
                            className="w-full sm:w-80"
                        >
                            {timezones.map((timezone) => (
                                <option key={timezone} value={timezone}>
                                    {timezone.replaceAll('_', ' ')}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            <Save /> Salvar
                        </Button>
                    </div>
                </form>
            </Page>
        </>
    );
}
