<?php

namespace App\Queries\Data;

use App\Models\ClassMeeting;
use App\Models\Lesson;
use Illuminate\Support\Collection;

/**
 * Resultado de CurrentLessonQuery: a lição "da semana" de uma classe.
 */
final readonly class CurrentLesson
{
    /**
     * @param  Collection<int, ClassMeeting>  $cancelledBefore  domingos sem EBD entre hoje e o encontro
     */
    public function __construct(
        public ?ClassMeeting $meeting,
        public ?Lesson $lesson,
        public bool $preparing,
        public int $meetingIndex,
        public int $meetingTotal,
        public Collection $cancelledBefore,
    ) {}

    /**
     * Dia que fecha a semana de leitura: o domingo "sem EBD" desta semana, se
     * houver (a lição atual segue sendo lida nele), senão o encontro.
     */
    public function readingDay(): ?\DateTimeInterface
    {
        return $this->cancelledBefore->first()->held_on ?? $this->meeting?->held_on;
    }

    public static function none(): self
    {
        return new self(null, null, false, 0, 0, collect());
    }
}
