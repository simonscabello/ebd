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
        public bool $isFallback,
    ) {}

    public static function none(): self
    {
        return new self(null, null, false, 0, 0, collect(), false);
    }
}
