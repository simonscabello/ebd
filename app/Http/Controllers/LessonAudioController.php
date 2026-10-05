<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega o áudio do estudo ("Ouvir estudo") com a mesma permissão de
 * leitura da lição. O arquivo é gerado uma vez (GenerateLessonAudio) e
 * servido daqui em diante, com suporte a Range para o player avançar.
 */
class LessonAudioController extends Controller
{
    public function __invoke(Lesson $lesson): Response
    {
        if (! $lesson->hasAudio() || Gate::denies('view', $lesson)) {
            abort(404);
        }

        $disk = Storage::disk((string) $lesson->audio_disk);
        $path = (string) $lesson->audio_path;

        if (! $disk->exists($path)) {
            abort(404);
        }

        // Em S3/R2, URL temporária assinada (o próprio bucket atende o Range).
        if (config("filesystems.disks.{$lesson->audio_disk}.driver") === 's3') {
            return redirect()->away($disk->temporaryUrl(
                $path,
                now()->addMinutes((int) config('ebd.materials.temporary_url_minutes')),
                ['ResponseContentType' => 'audio/mpeg'],
            ));
        }

        return response()->file($disk->path($path), [
            'Content-Type' => 'audio/mpeg',
            'X-Content-Type-Options' => 'nosniff',
            // O endereço muda a cada geração (?v=), então pode ficar em cache.
            'Cache-Control' => ($lesson->isPublic() ? 'public' : 'private').', max-age=86400',
        ]);
    }
}
