import { Head, Link, router } from '@inertiajs/react';
import {
    Award,
    BookOpen,
    CalendarCheck,
    Flame,
    History,
    KeyRound,
    Lock,
    NotebookPen,
    UserMinus,
} from 'lucide-react';
import { useState } from 'react';
import type { IssuedLink } from '@/components/admin/access-link-dialog';
import { AccessLinkDialog } from '@/components/admin/access-link-dialog';
import { ClassroomHeader } from '@/components/admin/classroom-header';
import { StatTile } from '@/components/admin/stat-tile';
import type { StudentAccess } from '@/components/admin/students/access-card';
import { AccessCard } from '@/components/admin/students/access-card';
import { EditStudentDialog } from '@/components/admin/students/edit-student-dialog';
import { MoveStudentDialog } from '@/components/admin/students/move-student-dialog';
import type { StudentNote } from '@/components/admin/students/student-notes';
import { StudentNotes } from '@/components/admin/students/student-notes';
import { useConfirm } from '@/components/confirm-dialog';
import type { GenderOption } from '@/components/gender-field';
import { Page, PageHeader, Section } from '@/components/page';
import type { EarnedBadge } from '@/components/progress/badge-shelf';
import { BadgeShelf } from '@/components/progress/badge-shelf';
import type { Streak } from '@/components/progress/streak-flame';
import { Button } from '@/components/ui/button';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { dayMonth, monthShort } from '@/lib/dates';
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

/** Domingos mostrados antes de "ver todos". */
const RECENT = 8;

/**
 * Ficha do aluno para o professor: contato, frequência domingo a domingo,
 * estudo em casa, anotações, acesso ao app e ações. As anotações pessoais do
 * aluno nunca aparecem aqui.
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
    const [issued, setIssued] = useState<IssuedLink | null>(null);
    const [allSundays, setAllSundays] = useState(false);
    const { frequency } = stats;
    const shownSundays = allSundays ? sundays : sundays.slice(0, RECENT);

    const details = [
        student.age !== null ? `${student.age} anos` : null,
        student.gender_label,
        student.counts_since
            ? `na classe desde ${dayMonth(student.counts_since)}`
            : null,
    ].filter(Boolean);

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
                {/* Abas na largura das outras páginas da classe; conteúdo na de leitura. */}
                <div className="max-w-2xl">
                    <PageHeader
                        title={student.name}
                        description={
                            <span className="flex flex-col gap-0.5">
                                {details.length > 0 && (
                                    <span>{details.join(' · ')}</span>
                                )}
                                {(student.phone || student.email) && (
                                    <span className="text-sm">
                                        {[
                                            formatPhone(student.phone),
                                            student.email,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                )}
                            </span>
                        }
                        actions={
                            <>
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
                            </>
                        }
                    />

                    <div className="grid gap-3 sm:grid-cols-3">
                        <StatTile
                            icon={<CalendarCheck />}
                            label="Frequência"
                            value={
                                frequency.rate === null
                                    ? '—'
                                    : `${frequency.rate}%`
                            }
                            hint={
                                frequency.expected > 0
                                    ? `${frequency.present} de ${frequency.expected} domingos · ${stats.period.series_id ? stats.period.label : stats.period.label.toLowerCase()}`
                                    : 'Sem chamada no período'
                            }
                            tone={
                                stats.overall.missed_in_a_row >= 2
                                    ? 'warning'
                                    : 'default'
                            }
                        />
                        <StatTile
                            icon={<BookOpen />}
                            label="Estudo em casa"
                            value={`${stats.lessons_studied}/${stats.lessons_total}`}
                            hint="lições com leitura marcada"
                        />
                        <StatTile
                            icon={<Flame />}
                            label="Sequência"
                            value={String(stats.streak.current)}
                            hint={`dias seguidos · recorde ${stats.streak.best}`}
                        />
                    </div>
                    {stats.overall.missed_in_a_row >= 2 && (
                        <p className="mt-3 rounded-xl bg-warning-soft px-3 py-2 text-sm text-warning-foreground">
                            {stats.overall.missed_in_a_row} faltas seguidas
                            {stats.overall.last_present_on &&
                                ` · última presença ${dayMonth(stats.overall.last_present_on)}`}
                            .
                        </p>
                    )}

                    <div className="mt-10 space-y-10">
                        <Section
                            title="Domingos"
                            icon={<History />}
                            description="Desde que o aluno entrou na classe, do mais recente para trás."
                        >
                            {sundays.length === 0 ? (
                                <p className="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                                    Nenhum domingo com chamada desde que entrou.
                                </p>
                            ) : (
                                <>
                                    <ul className="divide-y rounded-2xl border bg-card">
                                        {shownSundays.map((sunday) => {
                                            return (
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
                                                        <span className="w-10 shrink-0 text-center">
                                                            <span className="block text-xs text-muted-foreground uppercase">
                                                                {monthShort(
                                                                    sunday.held_on,
                                                                ).replace(
                                                                    '.',
                                                                    '',
                                                                )}
                                                            </span>
                                                            <span className="block font-serif text-lg font-semibold">
                                                                {Number(
                                                                    sunday.held_on.slice(
                                                                        8,
                                                                        10,
                                                                    ),
                                                                )}
                                                            </span>
                                                        </span>
                                                        <span className="min-w-0 flex-1 truncate text-sm">
                                                            {sunday.lesson ??
                                                                sunday.title ??
                                                                'Domingo sem lição'}
                                                        </span>
                                                        <span
                                                            className={cn(
                                                                'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium',
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
                                            );
                                        })}
                                    </ul>
                                    {sundays.length > RECENT && !allSundays && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="mt-2"
                                            onClick={() => setAllSundays(true)}
                                        >
                                            Ver todos os {sundays.length}{' '}
                                            domingos
                                        </Button>
                                    )}
                                </>
                            )}
                        </Section>

                        <Section
                            title="Estudo em casa"
                            icon={<BookOpen />}
                            description="Dias de leitura marcados em cada lição."
                        >
                            <LessonProgressList lessons={lessons} />
                        </Section>

                        <Section
                            title="Anotações"
                            icon={<NotebookPen />}
                            description="Só professores da classe veem. O aluno nunca vê."
                        >
                            <StudentNotes
                                classroom={classroom}
                                studentId={student.id}
                                notes={notes}
                            />
                        </Section>

                        <Section title="Acesso ao app" icon={<KeyRound />}>
                            <AccessCard
                                classroom={classroom}
                                student={student}
                                access={access}
                                today={today}
                                onIssued={setIssued}
                            />
                        </Section>

                        {badges.length > 0 && (
                            <Section title="Selos" icon={<Award />}>
                                <BadgeShelf earned={badges} available={[]} />
                            </Section>
                        )}

                        <Section title="Na classe">
                            <div className="flex flex-wrap gap-2">
                                {moveTargets.length > 0 && (
                                    <MoveStudentDialog
                                        classroom={classroom}
                                        student={student}
                                        targets={moveTargets}
                                    />
                                )}
                                <Button
                                    variant="ghost"
                                    className="text-destructive hover:text-destructive"
                                    onClick={remove}
                                >
                                    <UserMinus /> Remover da classe
                                </Button>
                            </div>
                        </Section>

                        <p className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Lock className="size-3.5" /> As anotações pessoais
                            do aluno nas lições são privadas e não aparecem para
                            professores.
                        </p>
                    </div>
                </div>
            </Page>

            <AccessLinkDialog link={issued} onClose={() => setIssued(null)} />
        </>
    );
}
