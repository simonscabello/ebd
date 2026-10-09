import {
    BookOpenText,
    Clock,
    Gem,
    Landmark,
    Lightbulb,
    ListChecks,
    NotebookPen,
    ScrollText,
    ShieldAlert,
} from 'lucide-react';
import type { LessonBlockKind } from '@/types';

const icons: Record<LessonBlockKind, typeof Lightbulb> = {
    roteiro: ListChecks,
    extra_time: Clock,
    accuracy_note: ShieldAlert,
    teacher_note: NotebookPen,
    context: Landmark,
    theology: ScrollText,
    curiosity: Lightbulb,
    application: Gem,
    concept: BookOpenText,
};

export function BlockIcon({
    kind,
    className,
}: {
    kind: LessonBlockKind;
    className?: string;
}) {
    const Icon = icons[kind];

    return <Icon className={className} />;
}
