<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use App\Support\Audio\Mp3;
use App\Support\Audio\OpenAiSpeech;
use App\Support\Audio\SpeechFailed;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Narra o estudo pela OpenAI e guarda um único MP3 no disco dos materiais.
 * Roda depois da resposta (ver RequestLessonAudio): leva de segundos a alguns
 * minutos, conforme o tamanho do estudo. O áudio anterior continua valendo
 * até o novo ficar pronto.
 */
class GenerateLessonAudio
{
    public function __construct(private OpenAiSpeech $speech) {}

    public function handle(Lesson $lesson): void
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $lesson->refresh();
        // Hash do texto que de fato foi narrado: se o estudo mudar durante a
        // geração, o áudio já nasce desatualizado.
        $hash = $lesson->audioSourceHash();

        try {
            $chunks = $lesson->narration()->chunks((int) config('ebd.audio.max_chars'));

            if ($chunks === []) {
                throw SpeechFailed::because('O estudo está vazio. Escreva o conteúdo antes de gerar o áudio.');
            }

            // Os narradores se revezam a cada parte do estudo.
            $voices = (array) config('ebd.audio.voices') ?: ['cedar'];
            $audio = Mp3::concat(array_map(
                fn (array $chunk) => $this->speech->synthesize($chunk['text'], (string) $voices[$chunk['part'] % count($voices)]),
                $chunks,
            ));

            $disk = (string) config('ebd.materials.disk');
            $path = config('ebd.audio.directory').'/'.$lesson->id.'/'.Str::random(32).'.mp3';

            if (! Storage::disk($disk)->put($path, $audio, ['visibility' => 'private', 'ContentType' => 'audio/mpeg'])) {
                throw SpeechFailed::because('Não foi possível salvar o arquivo de áudio. Tente de novo.');
            }
        } catch (Throwable $e) {
            report($e);

            $lesson->forceFill([
                'audio_status' => Lesson::AUDIO_FAILED,
                'audio_error' => $e instanceof SpeechFailed ? $e->getMessage() : 'Não foi possível gerar o áudio. O erro ficou registrado.',
            ])->save();

            return;
        }

        $previous = $lesson->hasAudio() ? [$lesson->audio_disk, $lesson->audio_path] : null;

        $lesson->forceFill([
            'audio_disk' => $disk,
            'audio_path' => $path,
            'audio_duration' => Mp3::duration($audio),
            'audio_source_hash' => $hash,
            'audio_generated_at' => now(),
            'audio_status' => null,
            'audio_error' => null,
        ])->save();

        if ($previous !== null) {
            Storage::disk((string) $previous[0])->delete((string) $previous[1]);
        }
    }
}
