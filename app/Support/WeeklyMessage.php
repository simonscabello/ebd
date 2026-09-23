<?php

namespace App\Support;

use App\Models\ClassMeeting;
use App\Models\Lesson;
use Illuminate\Support\Facades\Route;

/**
 * Textos prontos para colar no WhatsApp da classe.
 */
class WeeklyMessage
{
    /**
     * Mensagem da semana: lição, texto base, versículo-chave, leituras e links.
     */
    public function for(ClassMeeting $meeting): ?string
    {
        $lesson = $meeting->lesson;

        if ($lesson === null) {
            return null;
        }

        $lesson->loadMissing('readings');

        $lines = [
            '📖 *'.$lesson->displayTitle().'*',
            'Domingo, '.ChurchCalendar::formatShort($meeting->held_on),
        ];

        if ($lesson->bible_reference) {
            $lines[] = "Texto base: {$lesson->bible_reference}";
        }

        if ($lesson->key_verse) {
            $lines[] = '';
            $lines[] = '🔑 '.trim($lesson->key_verse);
        }

        $readings = $lesson->readings->filter(fn ($r) => $r->weekday !== null);

        if ($readings->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Leitura da semana*';

            foreach ($readings as $reading) {
                $lines[] = "{$reading->weekday?->shortLabel()}: {$reading->reference}";
            }
        }

        $lines[] = '';
        $lines[] = 'Estude a lição: '.route('lessons.show', $lesson->slug);

        if (Route::has('my-week')) {
            $lines[] = 'Sua semana de estudo: '.route('my-week');
        }

        return implode("\n", $lines);
    }

    /**
     * Texto curto para compartilhar uma lição.
     */
    public function share(Lesson $lesson): string
    {
        return implode("\n", array_filter([
            '📖 '.$lesson->displayTitle(),
            $lesson->bible_reference ? "Texto base: {$lesson->bible_reference}" : null,
            route('lessons.show', $lesson->slug),
        ]));
    }
}
