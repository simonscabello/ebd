import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ReorderButtons } from '@/components/admin/reorder-buttons';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { destroy, store, update } from '@/routes/admin/lessons/questions';
import type { LessonQuestion } from '@/types';

export function QuestionsManager({
    lessonId,
    questions,
}: {
    lessonId: number;
    questions: LessonQuestion[];
}) {
    const [editing, setEditing] = useState<number | null>(null);
    const ids = questions.map((q) => q.id);

    return (
        <div className="space-y-3">
            {questions.length > 0 && (
                <ol className="divide-y rounded-2xl border bg-card">
                    {questions.map((question, index) => (
                        <li
                            key={question.id}
                            className="flex items-start gap-2 p-3"
                        >
                            <ReorderButtons
                                lessonId={lessonId}
                                relation="questions"
                                ids={ids}
                                index={index}
                            />
                            <span className="mt-1.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-semibold text-accent-foreground">
                                {index + 1}
                            </span>
                            {editing === question.id ? (
                                <QuestionEditor
                                    lessonId={lessonId}
                                    question={question}
                                    onDone={() => setEditing(null)}
                                />
                            ) : (
                                <>
                                    <p className="flex-1 py-1.5 font-serif">
                                        {question.body}
                                    </p>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setEditing(question.id)}
                                        aria-label="Editar pergunta"
                                    >
                                        <Pencil />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remover pergunta"
                                        onClick={() => {
                                            if (
                                                confirm(
                                                    'Remover esta pergunta?',
                                                )
                                            ) {
                                                router.delete(
                                                    destroy.url({
                                                        lesson: lessonId,
                                                        question: question.id,
                                                    }),
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    >
                                        <Trash2 />
                                    </Button>
                                </>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            <QuestionEditor
                key={`new-${questions.length}`}
                lessonId={lessonId}
            />
        </div>
    );
}

function QuestionEditor({
    lessonId,
    question,
    onDone,
}: {
    lessonId: number;
    question?: LessonQuestion;
    onDone?: () => void;
}) {
    const form = useForm({ body: question?.body ?? '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const target = question
            ? update({ lesson: lessonId, question: question.id })
            : store(lessonId);

        form.submit(target, {
            preserveScroll: true,
            onSuccess: () => {
                if (!question) form.reset();
                onDone?.();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className={
                question
                    ? 'flex-1 space-y-2'
                    : 'space-y-2 rounded-2xl border border-dashed p-4'
            }
        >
            <label
                htmlFor={`question-${question?.id ?? 'new'}`}
                className={question ? 'sr-only' : 'text-sm font-medium'}
            >
                {question ? 'Pergunta' : 'Nova pergunta para reflexão'}
            </label>
            <Textarea
                id={`question-${question?.id ?? 'new'}`}
                value={form.data.body}
                onChange={(event) => form.setData('body', event.target.value)}
                rows={2}
                className="min-h-16"
                placeholder="Ex.: Por que Pedro pede que Jesus se afaste dele?"
                required
            />
            <InputError message={form.errors.body} />
            <div className="flex justify-end gap-2">
                {onDone && (
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancelar
                    </Button>
                )}
                <Button
                    type="submit"
                    variant={question ? 'default' : 'secondary'}
                    disabled={form.processing}
                >
                    {!question && <Plus />}{' '}
                    {question ? 'Salvar' : 'Adicionar pergunta'}
                </Button>
            </div>
        </form>
    );
}
