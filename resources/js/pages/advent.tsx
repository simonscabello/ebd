import { Head, router } from '@inertiajs/react';
import { Check, Lock } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ReadToggle } from '@/components/lesson/reading-plan';
import { ReadingSheet } from '@/components/lesson/reading-sheet';
import { Page, PageHeader } from '@/components/page';
import { cn, plural } from '@/lib/utils';
import { show } from '@/routes/advent';
import { destroy, store } from '@/routes/advent/checkins';
import type { BiblePassage } from '@/types';

type Day = {
    day: number;
    reference: string;
    read: boolean;
    locked: boolean;
    /** Quantas pessoas da igreja já leram (só o número). */
    readers: number;
};

type Props = {
    /** Ainda não começou: tudo travado. */
    upcoming: boolean;
    startsLabel: string;
    today: number | null;
    blocks: { from: number; to: number; title: string }[];
    days: Day[];
    /** O capítulo aberto (?dia=N). */
    reading: {
        day: number;
        reference: string;
        passage: BiblePassage | null;
    } | null;
};

/**
 * Natal em Lucas: os 24 capítulos como um calendário. Tocar num dia abre o
 * capítulo; os dias que ainda não chegaram ficam travados.
 */
export default function Advent({
    upcoming,
    startsLabel,
    today,
    blocks,
    days,
    reading,
}: Props) {
    // Quem chega pelo lembrete (?dia=N) já vê o capítulo aberto.
    const [open, setOpen] = useState(reading !== null);
    const [pending, setPending] = useState(false);
    const read = days.filter((day) => day.read).length;
    const current = reading ? days[reading.day - 1] : null;

    const openDay = (day: number) => {
        setOpen(true);
        router.get(
            show.url({ query: { dia: day } }),
            {},
            {
                only: ['reading'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const toggle = (day: Day) => {
        router.visit((day.read ? destroy : store).url(day.day), {
            method: day.read ? 'delete' : 'post',
            preserveState: true,
            preserveScroll: true,
            onStart: () => setPending(true),
            onFinish: () => setPending(false),
            onError: () =>
                toast.error(
                    'Não foi possível salvar a leitura. Tente de novo.',
                ),
        });
    };

    return (
        <>
            <Head title="Natal em Lucas" />
            <Page>
                <PageHeader
                    eyebrow="Especial de Natal"
                    title="Natal em Lucas"
                    description={
                        upcoming
                            ? `Um capítulo do Evangelho de Lucas por dia até o Natal. Começa em ${startsLabel}.`
                            : 'Um capítulo do Evangelho de Lucas por dia até o Natal. Perdeu um dia? Dá para pôr em dia.'
                    }
                />

                {/* Ilustrações no estilo das capas, já sem o fundo (public/images/advent). */}
                <img
                    src="/images/advent/capa.webp"
                    alt=""
                    width={900}
                    height={438}
                    className="mx-auto mb-6 h-auto w-full max-w-md"
                />

                <div className="mb-8">
                    <div className="mb-2 flex items-baseline justify-between text-sm">
                        <span className="font-medium">
                            {read} de {days.length} capítulos
                        </span>
                        {read === days.length && (
                            <span className="text-success-foreground">
                                Evangelho completo!
                            </span>
                        )}
                    </div>
                    <div
                        className="h-2 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-label="Capítulos lidos"
                        aria-valuemin={0}
                        aria-valuemax={days.length}
                        aria-valuenow={read}
                    >
                        <div
                            className="h-full rounded-full bg-primary transition-[width]"
                            style={{ width: `${(read / days.length) * 100}%` }}
                        />
                    </div>
                </div>

                <div className="space-y-8 pb-10">
                    {blocks.map((block, index) => (
                        <section key={block.from}>
                            <img
                                src={`/images/advent/bloco-${index + 1}.webp`}
                                alt=""
                                loading="lazy"
                                className="mb-3 h-28 w-auto max-w-full object-contain sm:h-36"
                            />
                            <h2 className="mb-3 text-sm font-medium text-muted-foreground">
                                <span className="font-mono">
                                    {block.from}–{block.to}
                                </span>{' '}
                                · {block.title}
                            </h2>
                            <ol className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                {days
                                    .slice(block.from - 1, block.to)
                                    .map((day) => (
                                        <li key={day.day}>
                                            <DayTile
                                                day={day}
                                                isToday={day.day === today}
                                                onOpen={() => openDay(day.day)}
                                            />
                                        </li>
                                    ))}
                            </ol>
                        </section>
                    ))}
                </div>
            </Page>

            <ReadingSheet
                open={open && reading !== null}
                onOpenChange={setOpen}
                reading={
                    reading && {
                        id: reading.day,
                        week: 1,
                        weekday: null,
                        weekday_label: `Dia ${reading.day} de ${days.length}`,
                        weekday_short: null,
                        is_today: reading.day === today,
                        reference: reading.reference,
                        passage: reading.passage,
                        notes: null,
                        position: reading.day,
                    }
                }
                footer={
                    current && (
                        <ReadToggle
                            done={current.read}
                            pending={pending}
                            highlight
                            onClick={() => toggle(current)}
                            className="w-full justify-center"
                        />
                    )
                }
            />
        </>
    );
}

function DayTile({
    day,
    isToday,
    onOpen,
}: {
    day: Day;
    isToday: boolean;
    onOpen: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onOpen}
            disabled={day.locked}
            aria-current={isToday ? 'date' : undefined}
            aria-label={[
                `Dia ${day.day}, ${day.reference}`,
                day.read ? 'lido' : null,
                day.locked ? 'ainda não liberado' : null,
                isToday ? 'hoje' : null,
            ]
                .filter(Boolean)
                .join(', ')}
            className={cn(
                'flex min-h-20 w-full flex-col justify-between rounded-2xl border p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                day.locked
                    ? 'border-dashed text-muted-foreground'
                    : day.read
                      ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                      : 'bg-card hover:bg-muted',
                isToday && !day.read && 'border-primary ring-1 ring-primary',
            )}
        >
            <span className="flex items-start justify-between">
                <span className="font-mono text-2xl leading-none font-medium">
                    {day.day}
                </span>
                {day.read ? (
                    <Check className="size-4" aria-hidden />
                ) : day.locked ? (
                    <Lock className="size-3.5" aria-hidden />
                ) : null}
            </span>
            <span
                className={cn('text-xs', !day.read && 'text-muted-foreground')}
            >
                {isToday && !day.read
                    ? 'Hoje'
                    : day.readers > 0
                      ? plural(day.readers, 'leu', 'leram')
                      : day.reference}
            </span>
        </button>
    );
}
