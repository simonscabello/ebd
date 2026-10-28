<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use App\Support\Audio\BackgroundMusic;
use App\Support\Audio\ListeningScript;
use App\Support\Audio\Mp3;
use App\Support\Audio\OpenAiSpeech;
use App\Support\Audio\SpeechFailed;
use App\Support\Audio\StudyNarration;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Narra o estudo pela OpenAI e guarda um único MP3 no disco dos materiais.
 * Cada parte do estudo vira roteiro para ouvir (ListeningScript) e é lida
 * por um narrador, revezando entre as vozes configuradas; com a música
 * configurada, ela vai por baixo, com um interlúdio entre as partes.
 * Roda depois da resposta (ver RequestLessonAudio): leva de segundos a alguns
 * minutos, conforme o tamanho do estudo. O áudio anterior continua valendo
 * até o novo ficar pronto.
 */
class GenerateLessonAudio
{
    public function __construct(
        private OpenAiSpeech $speech,
        private ListeningScript $script,
        private BackgroundMusic $music,
    ) {}

    public function handle(Lesson $lesson): void
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $lesson->refresh();
        // Hash do texto que de fato foi narrado: se o estudo mudar durante a
        // geração, o áudio já nasce desatualizado.
        $hash = $lesson->audioSourceHash();
        // O pedido que esta geração atende (ver RequestLessonAudio). A cota já
        // foi contada lá, então aqui não se registra uso nenhum.
        $requestedAt = $lesson->audio_requested_at;

        try {
            $chunks = $this->chunks($lesson);

            if ($chunks === []) {
                throw SpeechFailed::because('O estudo está vazio. Escreva o conteúdo antes de gerar o áudio.');
            }

            // Os narradores se revezam a cada parte do estudo.
            $voices = (array) config('ebd.audio.voices') ?: ['cedar'];
            $parts = [];

            foreach ($chunks as $chunk) {
                $parts[$chunk['part']][] = $this->speech->synthesize($chunk['text'], (string) $voices[$chunk['part'] % count($voices)]);
            }

            $parts = array_map(Mp3::concat(...), array_values($parts));
            $audio = $this->music->isAvailable() ? $this->music->mix($parts) : Mp3::concat($parts);

            $disk = (string) config('ebd.materials.disk');
            $path = config('ebd.audio.directory').'/'.$lesson->id.'/'.Str::random(32).'.mp3';

            if (! Storage::disk($disk)->put($path, $audio, ['visibility' => 'private', 'ContentType' => 'audio/mpeg'])) {
                throw SpeechFailed::because('Não foi possível salvar o arquivo de áudio. Tente de novo.');
            }
        } catch (Throwable $e) {
            report($e);

            $this->ownRequest($lesson, $requestedAt)->update([
                'audio_status' => Lesson::AUDIO_FAILED,
                'audio_error' => $e instanceof SpeechFailed ? $e->getMessage() : 'Não foi possível gerar o áudio. O erro ficou registrado.',
            ]);

            return;
        }

        $lesson->refresh();
        $previous = $lesson->hasAudio() ? [$lesson->audio_disk, $lesson->audio_path] : null;

        $saved = $this->ownRequest($lesson, $requestedAt)->update([
            'audio_disk' => $disk,
            'audio_path' => $path,
            'audio_duration' => Mp3::duration($audio),
            'audio_source_hash' => $hash,
            'audio_generated_at' => now(),
            'audio_status' => null,
            'audio_error' => null,
        ]);

        // A lição foi pedida de novo enquanto esta geração demorava (passou do
        // prazo): a mais nova é que vale, e este arquivo fica sem uso.
        if ($saved === 0) {
            Storage::disk($disk)->delete($path);

            return;
        }

        if ($previous !== null) {
            Storage::disk((string) $previous[0])->delete((string) $previous[1]);
        }
    }

    /**
     * A lição, se o pedido de geração ainda for o desta execução. Uma geração
     * que passou do prazo pode ser pedida de novo; a antiga, se continuar viva,
     * não grava por cima da nova.
     *
     * @return Builder<Lesson>
     */
    private function ownRequest(Lesson $lesson, ?CarbonInterface $requestedAt): Builder
    {
        return Lesson::query()
            ->whereKey($lesson->id)
            ->where('audio_status', Lesson::AUDIO_GENERATING)
            ->where('audio_requested_at', $requestedAt);
    }

    /**
     * Trechos para a voz, cada um com a parte (narrador) a que pertence. Com
     * o roteiro ligado, cada parte é reescrita para ser ouvida antes.
     *
     * @return list<array{part: int, text: string}>
     */
    private function chunks(Lesson $lesson): array
    {
        $narration = $lesson->narration();
        $maxChars = (int) config('ebd.audio.max_chars');

        if (! config('ebd.audio.script') || $narration->isEmpty()) {
            return $narration->chunks($maxChars);
        }

        $chunks = [];

        foreach ($this->script->write($narration->partTexts()) as $part => $text) {
            foreach (StudyNarration::chunkText($text, $maxChars) as $piece) {
                $chunks[] = ['part' => $part, 'text' => $piece];
            }
        }

        return $chunks;
    }
}
