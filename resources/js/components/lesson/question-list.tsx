import { cn } from '@/lib/utils';
import type { LessonQuestion } from '@/types';

export function QuestionList({
    questions,
    size = 'default',
}: {
    questions: LessonQuestion[];
    size?: 'default' | 'large';
}) {
    return (
        <ol className="space-y-3">
            {questions.map((question, index) => (
                <li
                    key={question.id}
                    className="flex gap-4 rounded-2xl border bg-card p-4"
                >
                    <span
                        className="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent font-semibold text-accent-foreground"
                        aria-hidden
                    >
                        {index + 1}
                    </span>
                    <p
                        className={cn(
                            'font-serif leading-relaxed text-pretty',
                            size === 'large' ? 'text-xl' : 'text-[17px]',
                        )}
                    >
                        {question.body}
                    </p>
                </li>
            ))}
        </ol>
    );
}
