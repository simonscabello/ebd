import { Head, Link, router } from '@inertiajs/react';
import { Lock, UserMinus } from 'lucide-react';
import { useState } from 'react';
import type { IssuedLink } from '@/components/admin/access-link-dialog';
import { AccessLinkDialog } from '@/components/admin/access-link-dialog';
import { ClassroomHeader } from '@/components/admin/classroom-header';
import type { StudentAccess } from '@/components/admin/students/access-card';
import { AccessCard } from '@/components/admin/students/access-card';
import { EditStudentDialog } from '@/components/admin/students/edit-student-dialog';
import { MoveStudentDialog } from '@/components/admin/students/move-student-dialog';
import type { StudentNote } from '@/components/admin/students/student-notes';
import { StudentNotes } from '@/components/admin/students/student-notes';
import { useConfirm } from '@/components/confirm-dialog';
import type { GenderOption } from '@/components/gender-field';
import { Page } from '@/components/page';
import type { EarnedBadge } from '@/components/progress/badge-shelf';
import { BadgeShelf } from '@/components/progress/badge-shelf';
import type { Streak } from '@/components/progress/streak-flame';
import { Button } from '@/components/ui/button';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { dayMonth } from '@/lib/dates';
import { meetingWords } from '@/lib/meeting-words';
import { formatPhone } from '@/lib/phone';
import { cn } from '@/lib/utils';
import { LessonProgressList } from '@/pages/my-progress';
import type { LessonProgress } from '@/pages/my-progress';
import { show as meetingPage } from '@/routes/admin/classrooms/meetings';
import { destroy as removeMember } from '@/routes/admin/classrooms/members';
import type { Classroom } from '@/types';

type Frequency = {
    present: number;
    expected: number;
    rate: number | null;
    missed_in_a_row: number;
    last_present_on: string | null;
};

type Props = {
    classroom: Classroom;
    today: string;
    student: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        birth_date: string | null;
        age: number | null;
        gender: string | null;
        gender_label: string | null;
        counts_since: string | null;
        has_password: boolean;
        profile_complete: boolean;
    };
    stats: {
        period: { label: string; series_id: number | null };
        frequency: Frequency;
        overall: Frequency;
        lessons_studied: number;
        lessons_total: number;
        streak: Streak;
    };
    sundays: {
        meeting_id: number;
        held_on: string;
        lesson: string | null;
        title: string | null;
        present: boolean;
    }[];
    lessons: LessonProgress[];
    badges: EarnedBadge[];
    access: StudentAccess;
    notes: StudentNote[];
    moveTargets: { slug: string; name: string }[];
    canEditEmail: boolean;
    genders: GenderOption[];
};

/** Domingos na faixa do Resumo. */
const RECENT = 8;

type Tab = 'resumo' | 'presenca' | 'estudos' | 'anotacoes' | 'acesso';

const TABS: { key: Tab; title: string }[] = [
    { key: 'resumo', title: 'Resumo' },
    { key: 'presenca', title: 'Presença' },
    { key: 'estudos', title: 'Estudos' },
    { key: 'anotacoes', title: 'Anotações' },
    { key: 'acesso', title: 'Acesso' },
];

/**
 * Página do aluno para o professor, no padrão de perfil dos painéis de
 * administração: à esquerda quem é (contato, números, ações); à direita, em
 * abas, o resumo, a presença domingo a domingo, o estudo em casa, as
 * anotações e o acesso ao app. As anotações pessoais do aluno nunca aparecem
 * aqui.
 */
export default function StudentPage({
    classroom,
    today,
    student,
    stats,
    sundays,
    lessons,
    badges,
    access,
    notes,
    moveTargets,
    canEditEmail,
    genders,
}: Props) {
    const confirm = useConfirm();
    const words = meetingWords(classroom);
    const [issued, setIssued] = useState<IssuedLink | null>(null);
    const [tab, setTab] = useState<Tab>('resumo');
    const { frequency } = stats;
    const missed = stats.overall.missed_in_a_row;
    const initials = student.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    const details = (
        [
            ['WhatsApp', formatPhone(student.phone)],
            ['E-mail', student.email],
            [
                'Aniversário',
                student.birth_date
                    ? `${dayMonth(student.birth_date)}${student.age !== null ? ` · ${student.age} anos` : ''}`
                    : null,
            ],
            ['Gênero', student.gender_label],
            [
                'Na classe desde',
                student.counts_since ? dayMonth(student.counts_since) : null,
            ],
            [
                'Última presença',
                stats.overall.last_present_on
                    ? dayMonth(stats.overall.last_present_on)
                    : null,
            ],
        ] as [string, string | null][]
    ).filter((row): row is [string, string] => Boolean(row[1]));

    const remove = async () => {
        if (
            await confirm({
                title: `Remover ${student.name} da classe?`,
                description: `${student.name} deixa de fazer parte da classe ${classroom.name}. As presenças ficam registradas, mas a pessoa sai da lista e dos números.`,
                confirmLabel: 'Remover',
                destructive: true,
            })
        ) {
            router.delete(
                removeMember.url({
                    classroom: classroom.slug,
                    user: student.id,
                }),
            );
        }
    };

    return (
        <>
            <Head title={`${student.name} · ${classroom.name}`} />

            <Page width="wide">
                <ClassroomHeader
                    classroom={classroom}
                    active="alunos"
                    crumbs={[{ title: student.name }]}
                />

                <div className="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
                    <aside className="h-fit rounded-2xl border bg-card p-5">
                        <div className="flex flex-col items-center text-center">
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'flex size-20 items-center justify-center rounded-full bg-primary/15 font-serif text-2xl font-semibold text-primary',
                                    missed >= 2 &&
                                        'bg-warning-soft text-warning-foreground',
                                )}
                            >
                                {initials}
                            </span>
                            <h1 className="mt-3 font-serif text-2xl font-semibold tracking-tight">
                                {student.name}
                            </h1>
                            {missed >= 2 && (
                                <span className="mt-2 rounded-md bg-warning-soft px-2 py-0.5 text-xs font-medium text-warning-foreground">
                                    Faltou os {missed} últimos
                                </span>
                            )}
                            <div className="mt-4 flex w-full flex-wrap justify-center gap-2">
                                <WhatsAppButton
                                    phone={student.phone}
                                    text={`Oi, ${student.name.split(' ')[0]}! `}
                                    size="default"
                                />
                                <EditStudentDialog
                                    classroom={classroom}
                                    student={student}
                                    genders={genders}
                                    canEditEmail={canEditEmail}
                                />
                            </div>
                        </div>

                        <div className="mt-5 grid grid-cols-3 gap-2 text-center">
                            <Figure
                                value={
                                    frequency.rate === null
                                        ? '—'
                                        : `${frequency.rate}%`
                                }
                                label="presença"
                            />
                            <Figure
                                value={`${stats.lessons_studied}/${stats.lessons_total}`}
                                label="lições lidas"
                            />
                            <Figure
                                value={String(stats.streak.current)}
                                label="dias seguidos"
                            />
                        </div>

                        <dl className="mt-5 space-y-3 border-t pt-4 text-sm">
                            {details.map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-xs text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="break-words">{value}</dd>
                                </div>
                            ))}
                        </dl>

                        <div className="mt-5 flex flex-col gap-2 border-t pt-4">
                            {moveTargets.length > 0 && (
                                <MoveStudentDialog
                                    classroom={classroom}
                                    student={student}
                                    targets={moveTargets}
                                />
                            )}
                            <Button
                                variant="ghost"
                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                onClick={remove}
                            >
                                <UserMinus /> Remover da classe
                            </Button>
                        </div>
                    </aside>

                    <div className="min-w-0">
                        <nav
                            aria-label={`Sobre ${student.name}`}
                            className="mb-4 flex gap-6 overflow-x-auto overflow-y-hidden rounded-2xl border bg-card px-5"
                        >
                            {TABS.map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    aria-pressed={tab === item.key}
                                    onClick={() => setTab(item.key)}
                                    className={cn(
                                        '-mb-px flex min-h-12 shrink-0 items-center border-b-2 border-transparent text-sm font-medium whitespace-nowrap text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        tab === item.key &&
                                            'border-primary text-foreground',
                                    )}
                                >
                                    {item.key === 'anotacoes' &&
                                    notes.length > 0
                                        ? `${item.title} · ${notes.length}`
                                        : item.title}
                                </button>
                            ))}
                        </nav>

                        {tab === 'resumo' && (
                            <div className="space-y-4">
                                <Card
                                    title={`Últimos ${words.many}`}
                                    aside={
                                        frequency.expected > 0
                                            ? `${frequency.present} de ${frequency.expected} · ${stats.period.series_id ? stats.period.label : stats.period.label.toLowerCase()}`
                                            : undefined
                                    }
                                >
                                    {sundays.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Nenhum {words.one} com chamada desde
                                            que entrou.
                                        </p>
                                    ) : (
                                        <ul className="flex flex-wrap gap-2">
                                            {sundays
                                                .slice(0, RECENT)
                                                .reverse()
                                                .map((sunday) => (
                                                    <li
                                                        key={sunday.meeting_id}
                                                        className="w-16 text-center"
                                                    >
                                                        <span
                                                            className={cn(
                                                                'flex h-9 items-center justify-center rounded-lg text-xs font-medium',
                                                                sunday.present
                                                                    ? 'bg-success-soft text-success-foreground'
                                                                    : 'bg-warning-soft text-warning-foreground',
                                                            )}
                                                        >
                                                            {sunday.present
                                                                ? 'Veio'
                                                                : 'Faltou'}
                                                        </span>
                                                        <span className="mt-1 block text-xs text-muted-foreground">
                                                            {dayMonth(
                                                                sunday.held_on,
                                                            )}
                                                        </span>
                                                    </li>
                                                ))}
                                        </ul>
                                    )}
                                </Card>
                                <Card title="Estudo em casa">
                                    <LessonProgressList
                                        lessons={lessons.slice(0, 3)}
                                        classroom={classroom}
                                    />
                                    {lessons.length > 3 && (
                                        <Button
                                            variant="link"
                                            className="mt-1 px-0"
                                            onClick={() => setTab('estudos')}
                                        >
                                            Ver todas as {lessons.length} lições
                                        </Button>
                                    )}
                                </Card>
                                {notes[0] && (
                                    <Card
                                        title="Última anotação"
                                        aside={notes[0].author ?? undefined}
                                    >
                                        <p className="text-sm whitespace-pre-line">
                                            {notes[0].body}
                                        </p>
                                    </Card>
                                )}
                                {badges.length > 0 && (
                                    <Card title="Selos">
                                        <BadgeShelf
                                            earned={badges}
                                            available={[]}
                                        />
                                    </Card>
                                )}
                            </div>
                        )}

                        {tab === 'presenca' && (
                            <Card
                                title={words.Many}
                                aside="Do mais recente para trás"
                            >
                                {sundays.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum {words.one} com chamada desde que
                                        entrou.
                                    </p>
                                ) : (
                                    <ul className="-mx-4 divide-y">
                                        {sundays.map((sunday) => (
                                            <li key={sunday.meeting_id}>
                                                <Link
                                                    href={meetingPage({
                                                        classroom:
                                                            classroom.slug,
                                                        meeting:
                                                            sunday.meeting_id,
                                                    })}
                                                    className="flex items-center gap-3 px-4 py-2.5 hover:bg-muted/50"
                                                >
                                                    <span className="w-14 shrink-0 text-sm text-muted-foreground">
                                                        {dayMonth(
                                                            sunday.held_on,
                                                        )}
                                                    </span>
                                                    <span className="min-w-0 flex-1 truncate text-sm">
                                                        {sunday.lesson ??
                                                            sunday.title ??
                                                            `${words.One} sem lição`}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'shrink-0 rounded-md px-2 py-0.5 text-xs font-medium',
                                                            sunday.present
                                                                ? 'bg-success-soft text-success-foreground'
                                                                : 'bg-muted text-muted-foreground',
                                                        )}
                                                    >
                                                        {sunday.present
                                                            ? 'Presente'
                                                            : 'Faltou'}
                                                    </span>
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Card>
                        )}

                        {tab === 'estudos' && (
                            <Card
                                title="Estudo em casa"
                                aside="Dias de leitura marcados em cada lição"
                            >
                                <LessonProgressList
                                    lessons={lessons}
                                    classroom={classroom}
                                />
                            </Card>
                        )}

                        {/* Montado sempre: trocar de aba não apaga a anotação em rascunho. */}
                        <div hidden={tab !== 'anotacoes'}>
                            <Card
                                title="Anotações"
                                aside="Só professores da classe veem"
                            >
                                <StudentNotes
                                    classroom={classroom}
                                    studentId={student.id}
                                    notes={notes}
                                />
                                <p className="mt-4 flex items-center gap-2 text-xs text-muted-foreground">
                                    <Lock className="size-3.5" /> As anotações
                                    pessoais do aluno nas lições são privadas e
                                    não aparecem para professores.
                                </p>
                            </Card>
                        </div>

                        {tab === 'acesso' && (
                            <Card title="Acesso ao app">
                                <AccessCard
                                    classroom={classroom}
                                    student={student}
                                    access={access}
                                    today={today}
                                    onIssued={setIssued}
                                />
                            </Card>
                        )}
                    </div>
                </div>
            </Page>

            <AccessLinkDialog link={issued} onClose={() => setIssued(null)} />
        </>
    );
}

/** Um número com legenda no cartão do aluno. */
function Figure({ value, label }: { value: string; label: string }) {
    return (
        <div className="rounded-xl border border-dashed px-2 py-2.5">
            <div className="text-lg font-semibold">{value}</div>
            <div className="text-xs text-muted-foreground">{label}</div>
        </div>
    );
}

function Card({
    title,
    aside,
    children,
}: {
    title: string;
    aside?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-2xl border bg-card p-4">
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-sm font-semibold">{title}</h2>
                {aside && (
                    <span className="text-xs text-muted-foreground">
                        {aside}
                    </span>
                )}
            </div>
            {children}
        </section>
    );
}
