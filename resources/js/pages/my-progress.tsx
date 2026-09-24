import { Head, Link } from '@inertiajs/react';
import { Award, BookOpen, CalendarCheck, History } from 'lucide-react';
import { EmptyState, Page, PageHeader, Section } from '@/components/page';
import type {
    AvailableBadge,
    EarnedBadge,
} from '@/components/progress/badge-shelf';
import { BadgeShelf } from '@/components/progress/badge-shelf';
import type { Streak } from '@/components/progress/streak-flame';
import { StreakFlame } from '@/components/progress/streak-flame';
import { show } from '@/routes/lessons';
import type { Classroom } from '@/types';

export type LessonProgress = {
    id: number;
    slug: string;
    display_title: string;
    date_short: string | null;
    days_read: number;
    readings_total: number;
    meetings: number;
    present: number;
};

export type Progress = {
    streak: Streak;
    badges: EarnedBadge[];
    available_badges: AvailableBadge[];
    lessons: LessonProgress[];
};

type Props = {
    classrooms: Classroom[];
    classroom: Classroom | null;
    progress: Progress | null;
};

export default function MyProgress({ classroom, progress }: Props) {
    return (
        <>
            <Head title="Meu progresso" />
            <Page>
                <PageHeader
                    eyebrow={classroom ? `Classe ${classroom.name}` : undefined}
                    title="Meu progresso"
                    description="Só você vê esta página. Seu professor acompanha a presença e o estudo, nunca as suas anotações."
                />

                {!progress ? (
                    <EmptyState
                        icon={<History />}
                        title="Você ainda não está em uma classe"
                    />
                ) : (
                    <div className="space-y-10">
                        <StreakFlame streak={progress.streak} />

                        <Section title="Selos" icon={<Award />}>
                            <BadgeShelf
                                earned={progress.badges}
                                available={progress.available_badges}
                            />
                        </Section>

                        <Section title="Lição a lição" icon={<History />}>
                            <LessonProgressList
                                lessons={progress.lessons}
                                linkLessons
                            />
                        </Section>
                    </div>
                )}
            </Page>
        </>
    );
}

export function LessonProgressList({
    lessons,
    linkLessons = false,
}: {
    lessons: LessonProgress[];
    linkLessons?: boolean;
}) {
    if (lessons.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nenhuma lição estudada ainda.
            </p>
        );
    }

    return (
        <ul className="divide-y rounded-2xl border bg-card">
            {lessons.map((lesson) => (
                <li key={lesson.id} className="space-y-2 px-4 py-3">
                    <div className="flex items-baseline justify-between gap-3">
                        {linkLessons ? (
                            <Link
                                href={show(lesson.slug)}
                                className="font-medium hover:underline"
                            >
                                {lesson.display_title}
                            </Link>
                        ) : (
                            <span className="font-medium">
                                {lesson.display_title}
                            </span>
                        )}
                        <span className="shrink-0 text-xs text-muted-foreground">
                            {lesson.date_short}
                        </span>
                    </div>
                    <div className="grid gap-1.5 text-xs text-muted-foreground sm:grid-cols-2">
                        <Meter
                            icon={<BookOpen className="size-3.5" />}
                            label={`Leitura: ${lesson.days_read}/${lesson.readings_total} dias`}
                            value={lesson.days_read / lesson.readings_total}
                        />
                        {lesson.meetings > 0 && (
                            <Meter
                                icon={<CalendarCheck className="size-3.5" />}
                                label={`Presença: ${lesson.present}/${lesson.meetings}`}
                                value={lesson.present / lesson.meetings}
                            />
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}

function Meter({
    icon,
    label,
    value,
}: {
    icon: React.ReactNode;
    label: string;
    value: number;
}) {
    return (
        <div>
            <p className="mb-1 flex items-center gap-1">
                {icon} {label}
            </p>
            <div className="h-1.5 rounded-full bg-muted">
                <div
                    className="h-1.5 rounded-full bg-primary"
                    style={{
                        width: `${Math.min(100, Math.round(value * 100))}%`,
                    }}
                />
            </div>
        </div>
    );
}
