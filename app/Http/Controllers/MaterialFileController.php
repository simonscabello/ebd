<?php

namespace App\Http\Controllers;

use App\Models\LessonMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega arquivos de materiais. Os arquivos nunca ficam em pasta pública:
 * o acesso segue exatamente a permissão de leitura da lição.
 */
class MaterialFileController extends Controller
{
    /** Tipos que o navegador pode abrir direto (PDF no visualizador, áudio no player). */
    private const INLINE_MIME_TYPES = [
        'application/pdf',
        'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/wav', 'audio/x-wav',
        'image/jpeg', 'image/png', 'image/webp',
    ];

    public function __invoke(Request $request, LessonMaterial $material): Response
    {
        if (! $material->hasFile() || Gate::denies('view', $material->lesson)) {
            abort(404);
        }

        // Material só do professor (ex.: manual completo) segue a regra do conteúdo do professor.
        if ($material->isForTeachers() && Gate::denies('viewTeacherContent', $material->lesson)) {
            abort(404);
        }

        $disk = Storage::disk((string) $material->disk);
        $path = (string) $material->path;

        if (! $disk->exists($path)) {
            abort(404);
        }

        $inline = ! $request->boolean('download')
            && in_array($material->mime_type, self::INLINE_MIME_TYPES, true);
        $disposition = $inline ? 'inline' : 'attachment';
        $filename = $material->original_name ?: basename($path);

        // Em S3/R2 redirecionamos para uma URL temporária assinada, sem trafegar
        // o arquivo pela aplicação.
        if (config("filesystems.disks.{$material->disk}.driver") === 's3') {
            return redirect()->away($disk->temporaryUrl(
                $path,
                now()->addMinutes((int) config('ebd.materials.temporary_url_minutes')),
                [
                    'ResponseContentDisposition' => "{$disposition}; filename=\"".addslashes($filename).'"',
                    'ResponseContentType' => $material->mime_type,
                ],
            ));
        }

        return $disk->response($path, $filename, [
            'Content-Type' => $material->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $material->lesson->isPublic() && ! $material->isForTeachers() ? 'public, max-age=3600' : 'private, max-age=3600',
        ], $disposition);
    }
}
