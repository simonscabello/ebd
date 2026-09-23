import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { reorder } from '@/routes/admin/lessons';

type Relation = 'materials' | 'questions' | 'readings' | 'blocks';

/**
 * Setas para reordenar itens. Botões são mais acessíveis e confiáveis no
 * celular do que arrastar e soltar.
 */
export function ReorderButtons({
    lessonId,
    relation,
    ids,
    index,
}: {
    lessonId: number;
    relation: Relation;
    ids: number[];
    index: number;
}) {
    const move = (delta: number) => {
        const target = index + delta;

        if (target < 0 || target >= ids.length) {
            return;
        }

        const next = [...ids];
        [next[index], next[target]] = [next[target], next[index]];

        router.put(
            reorder.url({ lesson: lessonId, relation }),
            { ids: next },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return (
        <div className="flex flex-col">
            <button
                type="button"
                onClick={() => move(-1)}
                disabled={index === 0}
                className="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30"
                aria-label="Mover para cima"
            >
                <ArrowUp className="size-4" />
            </button>
            <button
                type="button"
                onClick={() => move(1)}
                disabled={index === ids.length - 1}
                className="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30"
                aria-label="Mover para baixo"
            >
                <ArrowDown className="size-4" />
            </button>
        </div>
    );
}
