import { KeyRound, Target } from 'lucide-react';
import type { Lesson } from '@/types';

/**
 * Versículo-chave e alvo da lição, como na revista.
 */
export function RevistaHeader({
    lesson,
    size = 'default',
}: {
    lesson: Pick<Lesson, 'key_verse' | 'goal'>;
    size?: 'default' | 'large';
}) {
    if (!lesson.key_verse && !lesson.goal) {
        return null;
    }

    const text = size === 'large' ? 'text-xl md:text-2xl' : 'text-lg';

    return (
        <div className="grid gap-3">
            {lesson.key_verse && (
                <div className="flex gap-3 rounded-2xl border bg-card p-4">
                    <KeyRound className="mt-1 size-5 shrink-0 text-primary" />
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">
                            Versículo-chave
                        </p>
                        <p
                            className={`font-serif leading-relaxed text-pretty italic ${text}`}
                        >
                            {lesson.key_verse}
                        </p>
                    </div>
                </div>
            )}
            {lesson.goal && (
                <div className="flex gap-3 rounded-2xl border bg-card p-4">
                    <Target className="mt-1 size-5 shrink-0 text-primary" />
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">
                            Alvo da lição
                        </p>
                        <p className="leading-relaxed text-pretty">
                            {lesson.goal}
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}
