import { Head, Link } from '@inertiajs/react';
import { Flag, Minus, Plus, Users, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { Roster } from '@/components/lesson/attendance-sheet';
import { AttendanceSheet } from '@/components/lesson/attendance-sheet';
import { BiblePassage } from '@/components/lesson/bible-passage';
import { FinishMeetingDialog } from '@/components/lesson/finish-meeting-dialog';
import { BlockAccordion, BlockCards } from '@/components/lesson/lesson-blocks';
import { RevistaHeader } from '@/components/lesson/revista-header';
import { BottomSheet } from '@/components/ui/bottom-sheet';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { show } from '@/routes/lessons';
import type { ClassMeeting, Lesson } from '@/types';

type Props = {
    lesson: Lesson;
    canManage: boolean;
    /** Chamada e encerramento: só para quem conduz a classe. */
    conduct: {
        meeting: ClassMeeting;
        can_take_attendance: boolean;
        roster: Roster;
        present: number[];
    } | null;
};

const SCALES = ['text-base', 'text-lg', 'text-xl', 'text-2xl'] as const;

function readScale(): number {
    try {
        const stored = Number(window.localStorage.getItem('ebd:sunday-scale'));

        return Number.isInteger(stored) && stored >= 0 && stored < SCALES.length
            ? stored
            : 1;
    } catch {
        return 1;
    }
}

/**
 * Modo Domingo: interface limpa para conduzir (professor) ou acompanhar (aluno)
 * a aula no notebook, tablet ou celular. Sem navegação do app e com fonte
 * ajustável (lembrada neste aparelho).
 */
export default function SundayMode({ lesson, canManage, conduct }: Props) {
    const [scale, setScale] = useState(1);
    const [attendanceOpen, setAttendanceOpen] = useState(false);
    const [presentCount, setPresentCount] = useState(
        conduct?.present.length ?? 0,
    );
    const conducting = !!conduct?.can_take_attendance;

    useEffect(() => {
        setScale(readScale());
    }, []);

    const changeScale = (delta: number) => {
        const next = Math.min(SCALES.length - 1, Math.max(0, scale + delta));
        setScale(next);

        try {
            window.localStorage.setItem('ebd:sunday-scale', String(next));
        } catch {
            // Armazenamento indisponível: mantém só na sessão.
        }
    };

    const topics = lesson.topics ?? [];
    const teacher = lesson.teacher_blocks ?? [];
    const roteiro = teacher.filter((b) =>
        ['roteiro', 'teacher_note'].includes(b.kind),
    );
    const accuracy = teacher.filter((b) => b.kind === 'accuracy_note');
    const extraTime = teacher.filter((b) => b.kind === 'extra_time');
    const meetings = lesson.meetings ?? [];
    const meeting =
        meetings.find((m) => m.days_until >= 0) ??
        meetings[meetings.length - 1];

    return (
        <div className="min-h-svh bg-background">
            <Head title={`Modo Domingo · ${lesson.display_title}`} />

            <header className="sticky top-0 z-20 border-b border-border/70 bg-background/90 backdrop-blur">
                <div className="mx-auto flex h-14 max-w-3xl items-center justify-between gap-3 px-4">
                    <Link
                        href={show(lesson.slug)}
                        className="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <X className="size-4" /> Sair
                    </Link>
                    <span className="truncate text-sm font-semibold">
                        Modo Domingo
                    </span>
                    <div
                        className="flex items-center gap-1"
                        role="group"
                        aria-label="Tamanho do texto"
                    >
                        <button
                            type="button"
                            onClick={() => changeScale(-1)}
                            className="flex size-10 items-center justify-center rounded-lg border bg-card focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-40"
                            disabled={scale === 0}
                            aria-label="Diminuir texto"
                        >
                            <Minus className="size-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => changeScale(1)}
                            className="flex size-10 items-center justify-center rounded-lg border bg-card focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-40"
                            disabled={scale === SCALES.length - 1}
                            aria-label="Aumentar texto"
                        >
                            <Plus className="size-4" />
                        </button>
                    </div>
                </div>
            </header>

            <main
                className={cn(
                    'mx-auto max-w-3xl space-y-10 px-4 pt-8 sm:px-6',
                    conducting ? 'pb-36' : 'pb-24',
                    SCALES[scale],
                )}
            >
                <header>
                    {lesson.series && (
                        <p className="text-[0.8em] font-medium text-primary">
                            {lesson.series.title}
                        </p>
                    )}
                    <h1 className="mt-1 font-serif text-[2.2em] leading-tight font-semibold tracking-tight text-balance">
                        {lesson.display_title}
                    </h1>
                    {meeting && (
                        <p className="mt-1 text-[0.85em] text-muted-foreground first-letter:uppercase">
                            {meeting.date_label}
                            {meetings.length > 1 &&
                                ` · encontro ${meetings.indexOf(meeting) + 1} de ${meetings.length}`}
                        </p>
                    )}
                </header>

                {canManage && roteiro.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-[0.8em] font-semibold tracking-wide text-muted-foreground uppercase">
                            Roteiro da aula
                        </h2>
                        <BlockCards
                            blocks={roteiro}
                            tone="teacher"
                            size="large"
                        />
                    </section>
                )}

                {lesson.bible_reference && (
                    <BiblePassage
                        reference={lesson.bible_reference}
                        passage={lesson.bible_passage}
                        size="large"
                    />
                )}

                <RevistaHeader lesson={lesson} size="large" />

                {topics.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-[0.8em] font-semibold tracking-wide text-muted-foreground uppercase">
                            Tópicos
                        </h2>
                        <ol className="space-y-2">
                            {topics.map((topic, index) => (
                                <li
                                    key={`${topic}-${index}`}
                                    className="flex gap-3 font-serif text-[1.15em] font-medium"
                                >
                                    <span className="w-6 shrink-0 text-muted-foreground">
                                        {index + 1}.
                                    </span>
                                    {topic}
                                </li>
                            ))}
                        </ol>
                    </section>
                )}

                {canManage && accuracy.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-[0.8em] font-semibold tracking-wide text-muted-foreground uppercase">
                            Notas de precisão
                        </h2>
                        <BlockCards
                            blocks={accuracy}
                            tone="highlight"
                            size="large"
                        />
                    </section>
                )}

                {canManage && extraTime.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-[0.8em] font-semibold tracking-wide text-muted-foreground uppercase">
                            Se houver tempo
                        </h2>
                        <BlockAccordion blocks={extraTime} />
                    </section>
                )}

                {lesson.content_html && (
                    <details className="group rounded-2xl border bg-card p-5">
                        <summary className="cursor-pointer text-[0.9em] font-semibold marker:text-muted-foreground">
                            Estudo completo
                        </summary>
                        <div
                            className="reading mt-4 text-[1em]"
                            dangerouslySetInnerHTML={{
                                __html: lesson.content_html,
                            }}
                        />
                    </details>
                )}
            </main>

            {conduct && conducting && (
                <>
                    {/* Barra do professor: chamada e encerramento sempre à mão. */}
                    <div className="fixed inset-x-0 bottom-0 z-30 border-t border-border/70 bg-background/95 pb-safe backdrop-blur">
                        <div className="mx-auto flex max-w-3xl items-center gap-2 px-4 py-2 sm:px-6">
                            <Button
                                type="button"
                                variant="outline"
                                size="lg"
                                className="flex-1"
                                onClick={() => setAttendanceOpen(true)}
                                aria-haspopup="dialog"
                                aria-expanded={attendanceOpen}
                            >
                                <Users /> Chamada
                                <span className="text-muted-foreground tabular-nums">
                                    {presentCount}/{conduct.roster.length}
                                </span>
                            </Button>
                            <FinishMeetingDialog
                                meetingId={conduct.meeting.id}
                                initialNotes={conduct.meeting.notes ?? null}
                            >
                                <Button size="lg" className="flex-1">
                                    <Flag /> Encerrar aula
                                </Button>
                            </FinishMeetingDialog>
                        </div>
                    </div>

                    <BottomSheet
                        open={attendanceOpen}
                        onOpenChange={setAttendanceOpen}
                        title="Chamada"
                        description="Toque no nome de quem está presente. Salva sozinha."
                    >
                        <div className="pb-4">
                            <AttendanceSheet
                                key={conduct.meeting.id}
                                meetingId={conduct.meeting.id}
                                roster={conduct.roster}
                                initialPresent={conduct.present}
                                initialVisitors={conduct.meeting.visitors_count}
                                onChange={setPresentCount}
                            />
                        </div>
                    </BottomSheet>
                </>
            )}
        </div>
    );
}
