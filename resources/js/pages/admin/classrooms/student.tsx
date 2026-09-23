import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Award, History, KeyRound, Lock } from 'lucide-react';
import { Page, PageHeader, Section } from '@/components/page';
import { BadgeShelf } from '@/components/progress/badge-shelf';
import { StreakFlame } from '@/components/progress/streak-flame';
import { LessonProgressList } from '@/pages/my-progress';
import type { Progress } from '@/pages/my-progress';
import { insights } from '@/routes/admin/classrooms';
import type { Classroom } from '@/types';

type Props = {
    classroom: Classroom;
    student: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        is_managed: boolean;
        access_link: { use_count: number; last_used_at: string | null } | null;
    };
    progress: Progress;
};

export default function StudentProgress({
    classroom,
    student,
    progress,
}: Props) {
    return (
        <>
            <Head title={`${student.name} · ${classroom.name}`} />
            <Page>
                <Link
                    href={insights(classroom.slug)}
                    className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Evolução da classe
                </Link>
                <PageHeader
                    eyebrow={`Classe ${classroom.name}`}
                    title={student.name}
                    description={
                        <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            {student.email && <span>{student.email}</span>}
                            {student.phone && <span>{student.phone}</span>}
                            {student.access_link && (
                                <span className="inline-flex items-center gap-1">
                                    <KeyRound className="size-3.5" /> link usado{' '}
                                    {student.access_link.use_count}x
                                </span>
                            )}
                        </span>
                    }
                />

                <div className="space-y-10">
                    <StreakFlame streak={progress.streak} />

                    <Section title="Lição a lição" icon={<History />}>
                        <LessonProgressList lessons={progress.lessons} />
                    </Section>

                    <Section title="Selos" icon={<Award />}>
                        <BadgeShelf earned={progress.badges} available={[]} />
                        {progress.badges.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Nenhum selo ainda.
                            </p>
                        )}
                    </Section>

                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                        <Lock className="size-3.5" /> As anotações pessoais do
                        aluno são privadas e não aparecem para professores.
                    </p>
                </div>
            </Page>
        </>
    );
}
