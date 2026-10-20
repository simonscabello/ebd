<?php

namespace App\Support;

use App\Enums\AiUsageKind;
use App\Models\AiUsage;
use App\Models\Church;
use App\Models\Lesson;
use App\Support\Audio\OpenAiSpeech;

/**
 * O que cada igreja tem liberado: "Tirar dúvida", áudio do estudo e texto
 * bíblico. Tudo segue a igreja da lição (nunca a de quem lê), e só o dono
 * do sistema liga ou desliga (painel /dono).
 */
final class ChurchFeatures
{
    public static function churchOf(Lesson $lesson): Church
    {
        return $lesson->loadMissing('classroom.church')->classroom->church;
    }

    public static function helperAvailable(Lesson $lesson): bool
    {
        return (bool) config('ebd.helper.enabled')
            && app(OpenAiSpeech::class)->isConfigured()
            && self::churchOf($lesson)->helper_enabled;
    }

    public static function helperDailyLimit(Lesson $lesson): int
    {
        return self::churchOf($lesson)->helper_daily_limit;
    }

    public static function audioAvailable(Lesson $lesson): bool
    {
        return self::churchOf($lesson)->audio_enabled;
    }

    /**
     * Áudios que a igreja ainda pode gerar neste mês (fuso da igreja). Conta
     * os gerados e os que estão sendo gerados agora em outras lições, para
     * dois pedidos ao mesmo tempo não furarem o limite.
     */
    public static function audioRemainingThisMonth(Lesson $lesson): int
    {
        $church = self::churchOf($lesson);

        $generated = AiUsage::query()
            ->where('church_id', $church->id)
            ->where('kind', AiUsageKind::Audio)
            ->where('created_at', '>=', ChurchCalendar::using($church, fn () => ChurchCalendar::today()->startOfMonth()->utc()))
            ->count();

        $running = Lesson::query()
            ->whereKeyNot($lesson->id)
            ->whereHas('classroom', fn ($query) => $query->where('church_id', $church->id))
            ->where('audio_status', Lesson::AUDIO_GENERATING)
            ->where('audio_requested_at', '>=', now()->subMinutes(Lesson::AUDIO_GENERATION_TIMEOUT_MINUTES))
            ->count();

        return max(0, $church->audio_monthly_limit - $generated - $running);
    }

    /** Texto da NAA só em lição de igreja com licença; sem lição, nunca. */
    public static function showsBibleText(?Lesson $lesson): bool
    {
        return $lesson !== null && self::churchOf($lesson)->shows_bible_text;
    }
}
