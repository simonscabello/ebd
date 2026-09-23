import { CheckCircle2, CircleDashed, Eye, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { LessonQuestion } from '@/types';

export type SelfAssessment = 'correct' | 'partial' | 'wrong';

const assessments: {
    value: SelfAssessment;
    label: string;
    icon: typeof CheckCircle2;
    className: string;
}[] = [
    {
        value: 'correct',
        label: 'Acertei',
        icon: CheckCircle2,
        className:
            'data-[active=true]:border-emerald-500 data-[active=true]:bg-emerald-50 dark:data-[active=true]:bg-emerald-950/50',
    },
    {
        value: 'partial',
        label: 'Em parte',
        icon: CircleDashed,
        className:
            'data-[active=true]:border-amber-500 data-[active=true]:bg-amber-50 dark:data-[active=true]:bg-amber-950/50',
    },
    {
        value: 'wrong',
        label: 'Errei',
        icon: XCircle,
        className:
            'data-[active=true]:border-rose-500 data-[active=true]:bg-rose-50 dark:data-[active=true]:bg-rose-950/50',
    },
];

/**
 * Revisão com gabarito: o aluno responde (a resposta fica só no aparelho),
 * abre o gabarito e diz como foi. A autoavaliação é salva quando há login.
 */
export function ReviewQuiz({
    questions,
    attempts = {},
    onAssess,
}: {
    questions: LessonQuestion[];
    attempts?: Record<number, SelfAssessment>;
    onAssess?: (question: LessonQuestion, value: SelfAssessment) => void;
}) {
    return (
        <ol className="space-y-4">
            {questions.map((question, index) => (
                <ReviewItem
                    key={question.id}
                    index={index}
                    question={question}
                    initial={attempts[question.id]}
                    onAssess={onAssess}
                />
            ))}
        </ol>
    );
}

function ReviewItem({
    index,
    question,
    initial,
    onAssess,
}: {
    index: number;
    question: LessonQuestion;
    initial?: SelfAssessment;
    onAssess?: (question: LessonQuestion, value: SelfAssessment) => void;
}) {
    const [answer, setAnswer] = useState('');
    const [revealed, setRevealed] = useState(initial !== undefined);
    const [assessment, setAssessment] = useState<SelfAssessment | undefined>(
        initial,
    );

    return (
        <li className="rounded-2xl border bg-card p-4">
            <div className="flex gap-3">
                <span
                    className="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent font-semibold text-accent-foreground"
                    aria-hidden
                >
                    {index + 1}
                </span>
                <p className="font-serif text-[17px] leading-relaxed text-pretty">
                    {question.body}
                </p>
            </div>

            {!revealed ? (
                <div className="mt-3 space-y-2">
                    <label
                        htmlFor={`review-${question.id}`}
                        className="sr-only"
                    >
                        Sua resposta
                    </label>
                    <Textarea
                        id={`review-${question.id}`}
                        value={answer}
                        onChange={(event) => setAnswer(event.target.value)}
                        rows={2}
                        className="min-h-16"
                        placeholder="Escreva sua resposta (fica só no seu aparelho)…"
                    />
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => setRevealed(true)}
                    >
                        <Eye /> Ver gabarito
                    </Button>
                </div>
            ) : (
                <div className="mt-3 space-y-3">
                    {answer && (
                        <p className="rounded-xl bg-muted/60 p-3 text-sm">
                            <span className="font-medium">Sua resposta:</span>{' '}
                            {answer}
                        </p>
                    )}
                    <p className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 leading-relaxed text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100">
                        <span className="font-medium">Gabarito:</span>{' '}
                        {question.answer}
                    </p>
                    <div
                        className="grid grid-cols-3 gap-2"
                        role="radiogroup"
                        aria-label="Como você foi?"
                    >
                        {assessments.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                role="radio"
                                aria-checked={assessment === option.value}
                                data-active={assessment === option.value}
                                onClick={() => {
                                    setAssessment(option.value);
                                    onAssess?.(question, option.value);
                                }}
                                className={cn(
                                    'flex min-h-11 items-center justify-center gap-1.5 rounded-xl border bg-card px-2 text-sm font-medium',
                                    option.className,
                                )}
                            >
                                <option.icon className="size-4" />
                                {option.label}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </li>
    );
}
