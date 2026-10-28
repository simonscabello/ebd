<?php

namespace App\Http\Resources;

use App\Models\Lesson;
use App\Support\ChurchCalendar;
use App\Support\ChurchFeatures;
use App\Support\WeeklyMessage;

/**
 * Áudio do estudo. Na página da lição vai só o arquivo ("Ouvir estudo"); na
 * gestão (edição da lição) vai também o andamento da geração e se o áudio
 * ficou desatualizado.
 */
final class LessonAudioResource
{
    /**
     * @return array{url: string, duration: int|null, share_text: string}|null
     */
    public static function file(Lesson $lesson): ?array
    {
        return $lesson->hasAudio() ? [
            // ?v= muda a cada geração: o navegador não reaproveita o áudio antigo.
            'url' => route('lessons.audio', [$lesson->slug, 'v' => $lesson->audio_generated_at?->timestamp]),
            'duration' => $lesson->audio_duration,
            // Mensagem para o WhatsApp com o link que abre a lição no player.
            'share_text' => app(WeeklyMessage::class)->shareAudio($lesson),
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
            // Liberado pelo dono para a igreja, e quantos ainda cabem no mês.
            'enabled' => ChurchFeatures::audioAvailable($lesson),
            'remaining' => ChurchFeatures::audioAvailable($lesson) ? ChurchFeatures::audioRemainingThisMonth($lesson) : 0,
            'monthly_limit' => ChurchFeatures::churchOf($lesson)->audio_monthly_limit,
            // Gerar de novo com o áudio em dia (administrador), com o custo estimado.
            'regenerate' => request()->user()?->can('regenerateAudio', $lesson) ? self::estimate($lesson) : null,
        ];
    }

    /**
     * @return array{minutes: int, usd: float, brl: float}
     */
    private static function estimate(Lesson $lesson): array
    {
        $minutes = mb_strlen($lesson->narration()->text()) / (int) config('ebd.audio.chars_per_minute');
        $usd = $minutes * (float) config('ebd.audio.usd_per_minute');

        return [
            'minutes' => max(1, (int) round($minutes)),
            'usd' => round($usd, 2),
            'brl' => round($usd * (float) config('ebd.audio.usd_brl'), 2),
        ];
    }
}
