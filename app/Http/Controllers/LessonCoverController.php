<?php

namespace App\Http\Controllers;

use App\Actions\Lessons\SaveLessonCover;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega a capa da lição com a mesma permissão de leitura da lição. Lição
 * pública abre sem login, o que deixa o Spotify e o WhatsApp mostrarem a capa.
 */
class LessonCoverController extends Controller
{
    public function __invoke(Request $request, Lesson $lesson): Response
    {
        if (! $lesson->hasCover() || Gate::denies('view', $lesson)) {
            abort(404);
        }

        $disk = Storage::disk((string) $lesson->cover_disk);
        $path = (string) $lesson->cover_path;
        $type = 'image/jpeg';

        // ?tamanho=p: a miniatura dos cards, quando existe. ?tamanho=r: o recorte.
        if ($request->query('tamanho') === 'p' && $disk->exists(SaveLessonCover::thumbnailPath($path))) {
            $path = SaveLessonCover::thumbnailPath($path);
        } elseif ($request->query('tamanho') === 'r') {
            abort_unless($lesson->cover_cutout, 404);
            [$path, $type] = [SaveLessonCover::cutoutPath($path), 'image/webp'];
        }

        if (! $disk->exists($path)) {
            abort(404);
        }

        if (config("filesystems.disks.{$lesson->cover_disk}.driver") === 's3') {
            return redirect()->away($disk->temporaryUrl(
                $path,
                now()->addMinutes((int) config('ebd.materials.temporary_url_minutes')),
                ['ResponseContentType' => $type],
            ));
        }

        $response = response()->file($disk->path($path), [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // O endereço muda a cada troca (?v=), então pode ficar em cache. O
        // BinaryFileResponse nasce "public": a capa restrita fica só no navegador.
        $lesson->isPublic() ? $response->setPublic() : $response->setPrivate();

        return $response->setMaxAge(86400);
    }
}
