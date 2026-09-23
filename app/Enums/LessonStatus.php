<?php

namespace App\Enums;

/**
 * Ciclo de vida de uma lição:
 *
 *   draft ──publicar──▶ published ──concluir──▶ completed
 *     ▲                    │  ▲                     │
 *     └────despublicar─────┘  └──────reabrir────────┘
 */
enum LessonStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Published => 'Publicada',
            self::Completed => 'Concluída',
        };
    }

    /**
     * Uma lição é visível fora da administração quando publicada ou concluída.
     */
    public function isVisible(): bool
    {
        return $this !== self::Draft;
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published],
            self::Published => [self::Draft, self::Completed],
            self::Completed => [self::Published],
        };
    }

    /**
     * @return list<self>
     */
    public static function visibleCases(): array
    {
        return [self::Published, self::Completed];
    }
}
