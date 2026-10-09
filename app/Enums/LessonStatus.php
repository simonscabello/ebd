<?php

namespace App\Enums;

/**
 * Estado editorial de uma lição:
 *
 *   draft ──publicar──▶ published
 *     ▲                    │
 *     └────despublicar─────┘
 *
 * "Já foi dada" / "em andamento" não é status: vem dos encontros (ClassMeeting),
 * porque uma lição pode ocupar mais de um domingo.
 */
enum LessonStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Published => 'Publicada',
        };
    }

    /**
     * Uma lição é visível fora da administração quando publicada.
     */
    public function isVisible(): bool
    {
        return $this === self::Published;
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
            self::Published => [self::Draft],
        };
    }

    /**
     * @return list<self>
     */
    public static function visibleCases(): array
    {
        return [self::Published];
    }
}
