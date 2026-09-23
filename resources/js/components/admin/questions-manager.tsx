import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ReorderButtons } from '@/components/admin/reorder-buttons';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { destroy, store, update } from '@/routes/admin/lessons/questions';
import type { LessonQuestion, QuestionKind } from '@/types';

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
                                    <div className="flex-1 py-1.5">
                                        {question.kind === 'review' && (
                                            <span className="mb-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                                                Revisão
                                            </span>
                                        )}
                                        <p className="font-serif">
                                            {question.body}
                                        </p>
                                        {question.answer && (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                <span className="font-medium">
                                                    Gabarito:
                                                </span>{' '}
                                                {question.answer}
                                            </p>
                                        )}
                                    </div>
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
    const form = useForm<{
        kind: QuestionKind;
        body: string;
        answer: string;
    }>({
        kind: question?.kind ?? 'reflection',
        body: question?.body ?? '',
        answer: question?.answer ?? '',
    });
    const id = question?.id ?? 'new';

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
            {!question && <p className="text-sm font-medium">Nova pergunta</p>}
            <div
                className="flex gap-1 rounded-lg bg-muted p-1 text-sm"
                role="radiogroup"
                aria-label="Tipo da pergunta"
            >
                {(
                    [
                        ['reflection', 'Reflexão'],
                        ['review', 'Revisão (com gabarito)'],
                    ] as const
                ).map(([value, label]) => (
                    <button
                        key={value}
                        type="button"
                        role="radio"
                        aria-checked={form.data.kind === value}
                        onClick={() => form.setData('kind', value)}
                        className={
                            form.data.kind === value
                                ? 'flex-1 rounded-md bg-background px-3 py-1.5 font-medium shadow-xs'
                                : 'flex-1 rounded-md px-3 py-1.5 text-muted-foreground'
                        }
                    >
                        {label}
                    </button>
                ))}
            </div>
            <label htmlFor={`question-${id}`} className="sr-only">
                Pergunta
            </label>
            <Textarea
                id={`question-${id}`}
                value={form.data.body}
                onChange={(event) => form.setData('body', event.target.value)}
                rows={2}
                className="min-h-16"
                placeholder="Ex.: Por que Pedro pede que Jesus se afaste dele?"
                required
            />
            <InputError message={form.errors.body} />
            {form.data.kind === 'review' && (
                <>
                    <label
                        htmlFor={`answer-${id}`}
                        className="text-sm font-medium"
                    >
                        Gabarito
                    </label>
                    <Textarea
                        id={`answer-${id}`}
                        value={form.data.answer}
                        onChange={(event) =>
                            form.setData('answer', event.target.value)
                        }
                        rows={2}
                        className="min-h-16"
                        placeholder="O aluno vê depois de tentar responder."
                        required
                    />
                    <InputError message={form.errors.answer} />
                </>
            )}
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
