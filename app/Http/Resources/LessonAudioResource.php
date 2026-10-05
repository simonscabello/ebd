<?php

namespace App\Http\Resources;

use App\Models\Lesson;
use App\Support\ChurchCalendar;

/**
 * Áudio do estudo. Na página da lição vai só o arquivo ("Ouvir estudo"); na
 * gestão (edição da lição) vai também o andamento da geração e se o áudio
 * ficou desatualizado.
 */
final class LessonAudioResource
{
    /**
     * @return array{url: string, duration: int|null}|null
     */
    public static function file(Lesson $lesson): ?array
    {
        return $lesson->hasAudio() ? [
            // ?v= muda a cada geração: o navegador não reaproveita o áudio antigo.
            'url' => route('lessons.audio', [$lesson->slug, 'v' => $lesson->audio_generated_at?->timestamp]),
            'duration' => $lesson->audio_duration,
        ] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function manage(Lesson $lesson): array
    {
        $file = self::file($lesson);

        return [
            'file' => $file,
            'status' => match (true) {
                $lesson->isGeneratingAudio() => 'generating',
                $lesson->audio_status === Lesson::AUDIO_FAILED => 'failed',
                default => $file ? 'ready' : 'none',
            },
            'stale' => $lesson->isAudioStale(),
            'error' => $lesson->audio_status === Lesson::AUDIO_FAILED ? $lesson->audio_error : null,
            'generated_at' => $lesson->audio_generated_at
                ? ChurchCalendar::formatShort($lesson->audio_generated_at->timezone(ChurchCalendar::timezone()))
                : null,
            'has_content' => filled($lesson->content),
        ];
    }
}
