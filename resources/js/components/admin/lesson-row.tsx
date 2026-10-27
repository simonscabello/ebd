import { Link, router } from '@inertiajs/react';
import { BookOpen, Check, Ellipsis, Pencil } from 'lucide-react';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { update as setCurrentLesson } from '@/routes/admin/classrooms/current-lesson';
import { edit as editLesson } from '@/routes/admin/lessons';
import type { Classroom, LessonStatus } from '@/types';

/** ⋯ de uma lição: estudar agora ou editar. */
export function LessonMenu({
    classroom,
    lesson,
    studyNow = true,
}: {
    classroom: Classroom;
    lesson: { id: number; display_title: string };
    studyNow?: boolean;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Mais da lição ${lesson.display_title}`}
                >
                    <Ellipsis />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {studyNow && (
                    <DropdownMenuItem
                        onSelect={() =>
                            router.post(
                                setCurrentLesson.url(classroom.slug),
                                { lesson_id: lesson.id },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <BookOpen /> Estudar esta agora
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild>
                    <Link href={editLesson.url(lesson.id)}>
                        <Pencil /> Editar
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function LessonNumber({
    number,
    done = false,
}: {
    number: number | null;
    done?: boolean;
}) {
    return (
        <span
            className={cn(
                'flex size-7 shrink-0 items-center justify-center rounded-lg bg-muted text-xs font-semibold',
                done && 'bg-primary/15 text-primary',
            )}
        >
            {done ? <Check className="size-3.5" /> : (number ?? '·')}
        </span>
    );
}

export function LessonStatusBadge({ status }: { status: LessonStatus }) {
    return (
        <StatusBadge
            status={status}
            label={status === 'draft' ? 'Rascunho' : 'Pronta'}
        />
    );
}
