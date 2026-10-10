import { Head } from '@inertiajs/react';
import {
    ChevronRight,
    ExternalLink,
    MessageCircleQuestion,
} from 'lucide-react';
import { ClassroomHeader } from '@/components/admin/classroom-header';
import { EmptyState, Page, PageHeader } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { dayMonth } from '@/lib/dates';
import { plural } from '@/lib/utils';
import type { Classroom } from '@/types';

type Question = {
    id: number;
    question: string;
    answer: string;
    /** Quantas vezes a mesma pergunta foi feita. */
    times: number;
    asked_on: string;
};

type LessonQuestions = {
    id: number;
    title: string;
    url: string;
    total: number;
    people: number;
    questions: Question[];
};

type Props = {
    classroom: Classroom;
    lessons: LessonQuestions[];
    today: string;
};

function askedLabel(date: string, today: string): string {
    return date === today ? 'hoje' : dayMonth(date);
}

/**
 * Dúvidas que a turma tirou com a IA, por lição, das mais recentes. Ajudam o
 * professor a ver o que ficou confuso antes do domingo. Anônimas.
 */
export default function ClassroomQuestions({
    classroom,
    lessons,
    today,
}: Props) {
    return (
        <>
            <Head title={`Dúvidas · ${classroom.name}`} />

            <Page>
                <ClassroomHeader classroom={classroom} active="duvidas" />

                <PageHeader
                    title="Dúvidas da turma"
                    description="O que os alunos perguntaram à IA no “Tirar dúvida” de cada lição. Os nomes não aparecem."
                />

                {lessons.length === 0 ? (
                    <EmptyState
                        icon={<MessageCircleQuestion />}
                        title="Nenhuma dúvida ainda"
                    >
                        Quando os alunos usarem o “Tirar dúvida” na página da
                        lição, as perguntas aparecem aqui.
                    </EmptyState>
                ) : (
                    <div className="space-y-10">
                        {lessons.map((lesson) => (
                            <section
                                key={lesson.id}
                                aria-labelledby={`lesson-${lesson.id}`}
                            >
                                <div className="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <h2
                                        id={`lesson-${lesson.id}`}
                                        className="font-serif text-xl font-semibold tracking-tight"
                                    >
                                        {lesson.title}
                                    </h2>
                                    <a
                                        href={lesson.url}
                                        className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                    >
                                        Abrir lição{' '}
                                        <ExternalLink className="size-3.5" />
                                    </a>
                                </div>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    {plural(lesson.total, 'dúvida', 'dúvidas')}{' '}
                                    de{' '}
                                    {plural(lesson.people, 'pessoa', 'pessoas')}
                                </p>

                                <ul className="divide-y rounded-2xl border bg-card">
                                    {lesson.questions.map((question) => (
                                        <li key={question.id}>
                                            <details className="group">
                                                <summary className="flex cursor-pointer list-none items-start gap-3 px-4 py-3.5 hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                                                    <ChevronRight className="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-90" />
                                                    <span className="min-w-0 flex-1 text-pretty">
                                                        {question.question}
                                                        {question.times > 1 && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="ml-2 rounded-full align-middle"
                                                            >
                                                                {question.times}
                                                                ×
                                                            </Badge>
                                                        )}
                                                    </span>
                                                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                        {askedLabel(
                                                            question.asked_on,
                                                            today,
                                                        )}
                                                    </span>
                                                </summary>
                                                <div className="space-y-2 px-4 pb-4 pl-11 text-sm leading-relaxed text-pretty text-muted-foreground">
                                                    <p className="text-xs font-semibold tracking-wide uppercase">
                                                        Resposta da IA
                                                    </p>
                                                    {question.answer
                                                        .split(/\n\s*\n|\n/)
                                                        .filter(Boolean)
                                                        .map((paragraph, i) => (
                                                            <p key={i}>
                                                                {paragraph}
                                                            </p>
                                                        ))}
                                                </div>
                                            </details>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </Page>
        </>
    );
}
