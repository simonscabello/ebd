import { router } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useState } from 'react';
import { AutosaveStatus } from '@/components/autosave-status';
import { Textarea } from '@/components/ui/textarea';
import { useAutosave } from '@/hooks/use-autosave';
import { meetingWords } from '@/lib/meeting-words';
import { update } from '@/routes/lessons/note';
import type { Classroom } from '@/types/ebd';

/**
 * Anotação pessoal na lição, salva automaticamente. Só a própria pessoa vê.
 */
export function PersonalNote({
    lessonSlug,
    initial,
    classroom,
}: {
    lessonSlug: string;
    initial: string | null;
    classroom?: Pick<Classroom, 'meeting_weekday' | 'program_short_name'>;
}) {
    const words = meetingWords(classroom);
    const [body, setBody] = useState(initial ?? '');
    const { status, retry } = useAutosave(
        body,
        initial ?? '',
        (value, callbacks) =>
            router.put(
                update.url(lessonSlug),
                { body: value },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['study'],
                    ...callbacks,
                },
            ),
    );

    return (
        <div className="space-y-2">
            <Textarea
                value={body}
                onChange={(event) => setBody(event.target.value)}
                rows={5}
                maxLength={20000}
                placeholder={`O que Deus falou com você nesta lição? Dúvidas para levar ${words.one === 'domingo' ? 'no domingo' : 'ao encontro'}…`}
                aria-label="Minha anotação"
                aria-describedby="personal-note-status"
                className="font-serif text-base"
            />
            <p
                id="personal-note-status"
                className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                aria-live="polite"
            >
                <span className="inline-flex items-center gap-1.5">
                    <Lock className="size-3.5" /> Só você vê esta anotação.
                </span>
                <AutosaveStatus status={status} onRetry={retry} />
            </p>
        </div>
    );
}
