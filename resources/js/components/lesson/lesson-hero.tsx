import { Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, KeyRound } from 'lucide-react';
import type { ReactNode } from 'react';

type HeroLesson = {
    url: string;
    number: number | null;
    title: string;
    bible_reference: string | null;
    key_verse: string | null;
};

/**
 * Card de destaque da lição (Início e Minha semana): o card inteiro abre a lição.
 */
export function LessonHero({
    lesson,
    eyebrow,
}: {
    lesson: HeroLesson;
    eyebrow: ReactNode;
}) {
    return (
        <Link
            href={lesson.url}
            className="block rounded-3xl bg-primary p-6 text-primary-foreground shadow-sm transition-opacity hover:opacity-95"
        >
            <p className="text-sm font-medium opacity-90 first-letter:uppercase">
                {eyebrow}
            </p>
            {lesson.number && (
                <p className="mt-3 text-sm font-semibold tracking-wide uppercase opacity-80">
                    Lição {lesson.number}
                </p>
            )}
            <h2 className="mt-1 font-serif text-3xl leading-tight font-semibold text-balance">
                {lesson.title}
            </h2>
            {lesson.bible_reference && (
                <p className="mt-2 flex items-center gap-2 opacity-95">
                    <BookOpen className="size-4" /> {lesson.bible_reference}
                </p>
            )}
            {lesson.key_verse && (
                <p className="mt-3 flex gap-2 font-serif text-pretty italic opacity-95">
                    <KeyRound className="mt-1 size-4 shrink-0" />
                    {lesson.key_verse}
                </p>
            )}
            <p className="mt-4 inline-flex items-center gap-1 text-sm font-semibold">
                Abrir a lição <ArrowRight className="size-4" />
            </p>
        </Link>
    );
}
