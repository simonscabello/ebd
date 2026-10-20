import { Head, useForm } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { OwnerTabs } from '@/components/owner/owner-tabs';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/owner/churches';

type ChurchStats = {
    id: number;
    name: string;
    created_at: string | null;
    classrooms_count: number;
    people_count: number;
    last_activity_at: string | null;
    questions_this_month: number;
    audios_this_month: number;
    helper_enabled: boolean;
    helper_daily_limit: number;
    audio_enabled: boolean;
    audio_monthly_limit: number;
    shows_bible_text: boolean;
};

/**
 * Painel do dono do sistema: cada igreja com os números de uso (nada do
 * conteúdo de dentro) e o que ela tem liberado.
 */
export default function OwnerChurches({
    churches,
}: {
    churches: ChurchStats[];
}) {
    return (
        <>
            <Head title="Igrejas" />
            <Page width="wide">
                <PageHeader
                    title="Igrejas"
                    description="Quem usa o app, só com números. A IA, o áudio e o texto bíblico só funcionam onde você ligar."
                />
                <OwnerTabs />

                {churches.length === 0 ? (
                    <EmptyState
                        icon={<Building2 />}
                        title="Nenhuma igreja cadastrada"
                    />
                ) : (
                    <ul className="grid gap-4">
                        {churches.map((church) => (
                            <ChurchCard key={church.id} church={church} />
                        ))}
                    </ul>
                )}
            </Page>
        </>
    );
}

function Stat({ label, value }: { label: string; value: string | number }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

function ChurchCard({ church }: { church: ChurchStats }) {
    const form = useForm({
        helper_enabled: church.helper_enabled,
        helper_daily_limit: church.helper_daily_limit,
        audio_enabled: church.audio_enabled,
        audio_monthly_limit: church.audio_monthly_limit,
        shows_bible_text: church.shows_bible_text,
    });

    const id = (field: string) => `church-${church.id}-${field}`;

    return (
        <li className="rounded-2xl border bg-card p-4 sm:p-5">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="font-serif text-lg font-semibold">
                    {church.name}
                </h2>
                {church.created_at && (
                    <span className="text-sm text-muted-foreground">
                        Desde {church.created_at.split('-').reverse().join('/')}
                    </span>
                )}
            </div>

            <dl className="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-5">
                <Stat label="Turmas" value={church.classrooms_count} />
                <Stat label="Pessoas" value={church.people_count} />
                <Stat
                    label="Último uso"
                    value={church.last_activity_at ?? '—'}
                />
                <Stat
                    label="Dúvidas no mês"
                    value={church.questions_this_month}
                />
                <Stat label="Áudios no mês" value={church.audios_this_month} />
            </dl>

            <form
                className="mt-4 grid gap-4 border-t pt-4 sm:grid-cols-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update.url(church.id), { preserveScroll: true });
                }}
            >
                <div className="space-y-2">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={id('helper')}
                            checked={form.data.helper_enabled}
                            onCheckedChange={(value) =>
                                form.setData('helper_enabled', value === true)
                            }
                        />
                        <Label htmlFor={id('helper')}>Tirar dúvida</Label>
                    </div>
                    <Label
                        htmlFor={id('helper-limit')}
                        className="text-xs font-normal text-muted-foreground"
                    >
                        Dúvidas por pessoa por dia
                    </Label>
                    <Input
                        id={id('helper-limit')}
                        type="number"
                        min={1}
                        max={100}
                        value={form.data.helper_daily_limit}
                        onChange={(event) =>
                            form.setData(
                                'helper_daily_limit',
                                Number(event.target.value),
                            )
                        }
                    />
                    {form.errors.helper_daily_limit && (
                        <p className="text-sm text-destructive">
                            {form.errors.helper_daily_limit}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={id('audio')}
                            checked={form.data.audio_enabled}
                            onCheckedChange={(value) =>
                                form.setData('audio_enabled', value === true)
                            }
                        />
                        <Label htmlFor={id('audio')}>Áudio do estudo</Label>
                    </div>
                    <Label
                        htmlFor={id('audio-limit')}
                        className="text-xs font-normal text-muted-foreground"
                    >
                        Áudios por mês na igreja
                    </Label>
                    <Input
                        id={id('audio-limit')}
                        type="number"
                        min={1}
                        max={100}
                        value={form.data.audio_monthly_limit}
                        onChange={(event) =>
                            form.setData(
                                'audio_monthly_limit',
                                Number(event.target.value),
                            )
                        }
                    />
                    {form.errors.audio_monthly_limit && (
                        <p className="text-sm text-destructive">
                            {form.errors.audio_monthly_limit}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={id('bible')}
                            checked={form.data.shows_bible_text}
                            onCheckedChange={(value) =>
                                form.setData('shows_bible_text', value === true)
                            }
                        />
                        <Label htmlFor={id('bible')}>Texto bíblico</Label>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Ligue só para igrejas com licença da NAA. Sem ele,
                        aparece só a referência.
                    </p>
                </div>

                <div className="sm:col-span-3">
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing || !form.isDirty}
                    >
                        Salvar
                    </Button>
                </div>
            </form>
        </li>
    );
}
