import { Head, Link } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import { ClassroomHeader } from '@/components/admin/classroom-header';
import { MeetingRow } from '@/components/admin/meetings/meeting-row';
import type { MeetingRowData as Row } from '@/components/admin/meetings/meeting-row';
import { EmptyState, Page, PageHeader, Section } from '@/components/page';
import { monthYear } from '@/lib/dates';
import { meetingWords } from '@/lib/meeting-words';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/classrooms/meetings';
import type { Classroom } from '@/types';

type Props = {
    classroom: Classroom;
    today: string;
    upcoming: Row[];
    past: Row[];
    showingAll: boolean;
    hasOlder: boolean;
};

/**
 * Domingos da classe: os próximos primeiro e os anteriores abaixo. Cada linha
 * abre a página do domingo, onde ficam a lição, a chamada e "onde paramos".
 */
export default function ClassroomMeetings({
    classroom,
    upcoming,
    past,
    showingAll,
    hasOlder,
}: Props) {
    const words = meetingWords(classroom);

    return (
        <>
            <Head title={`${words.Many} · ${classroom.name}`} />

            <Page width="wide">
                <ClassroomHeader classroom={classroom} active="domingos" />
                <PageHeader
                    title={words.Many}
                    description={`A chamada de cada ${words.one}. O próximo já está marcado com a lição atual; marque “Sem ${words.program}” quando não houver aula.`}
                />

                {upcoming.length === 0 && past.length === 0 ? (
                    <EmptyState
                        icon={<CalendarPlus />}
                        title={`Nenhum ${words.one} na agenda`}
                    >
                        Escolha a lição atual da classe: o próximo {words.one}{' '}
                        entra na agenda com ela.
                    </EmptyState>
                ) : (
                    <div className="space-y-10">
                        <Section title="Próximos">
                            {upcoming.length === 0 ? (
                                <p className="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                                    Nenhum {words.one} daqui para frente.
                                </p>
                            ) : (
                                <MeetingList
                                    classroom={classroom}
                                    rows={upcoming}
                                />
                            )}
                        </Section>

                        {past.length > 0 && (
                            <Section title="Anteriores">
                                <MeetingList
                                    classroom={classroom}
                                    rows={past}
                                />
                                {hasOlder && !showingAll && (
                                    <Link
                                        href={index.url(classroom.slug, {
                                            query: { todos: 1 },
                                        })}
                                        preserveScroll
                                        className="mt-3 inline-flex text-sm font-medium text-primary hover:underline"
                                    >
                                        Ver {words.many} mais antigos
                                    </Link>
                                )}
                            </Section>
                        )}
                    </div>
                )}
            </Page>
        </>
    );
}

function MeetingList({
    classroom,
    rows,
}: {
    classroom: Classroom;
    rows: Row[];
}) {
    let month = '';

    return (
        <ul className="divide-y rounded-2xl border bg-card">
            {rows.map((row, index) => {
                const rowMonth = monthYear(row.held_on);
                const showMonth = rowMonth !== month;
                month = rowMonth;

                return (
                    <li key={row.id}>
                        {showMonth && (
                            <p
                                className={cn(
                                    'bg-muted/40 px-4 py-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase',
                                    index === 0 && 'rounded-t-2xl',
                                )}
                            >
                                {rowMonth}
                            </p>
                        )}
                        <MeetingRow classroom={classroom} row={row} />
                    </li>
                );
            })}
        </ul>
    );
}
