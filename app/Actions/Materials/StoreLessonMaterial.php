<?php

namespace App\Actions\Materials;

use App\Enums\ContentAudience;
use App\Enums\MaterialType;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Adiciona um material à lição. Arquivos vão para o disco configurado em
 * config/ebd.php (local em dev, S3/R2 em produção) com nome aleatório;
 * o nome original é guardado apenas para exibição/download.
 */
class StoreLessonMaterial
{
    /**
     * @param  array<string, mixed>  $data  dados validados por LessonMaterialRequest
     */
    public function handle(Lesson $lesson, array $data, ?UploadedFile $file = null): LessonMaterial
    {
        $type = $data['type'] instanceof MaterialType ? $data['type'] : MaterialType::from((string) $data['type']);

        $material = new LessonMaterial([
            'type' => $type,
            'audience' => $data['audience'] ?? ContentAudience::Student->value,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'url' => $file ? null : ($data['url'] ?? null),
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ]);
        $material->lesson_id = $lesson->id;

        $storedPath = null;

        if ($file !== null && $type->acceptsUpload()) {
            $storedPath = $this->storeFile($lesson, $file, $material);
        }

        try {
            DB::transaction(fn () => $material->save());
        } catch (\Throwable $e) {
            // Não deixa arquivo órfão se o registro não for gravado.
            if ($storedPath !== null) {
                Storage::disk((string) $material->disk)->delete($storedPath);
            }

            throw $e;
        }

        return $material;
    }

    public function storeFile(Lesson $lesson, UploadedFile $file, LessonMaterial $material): string
    {
        $disk = (string) config('ebd.materials.disk');
        $directory = config('ebd.materials.directory').'/'.$lesson->id;

        $path = $file->store($directory, ['disk' => $disk, 'visibility' => 'private']);

        if ($path === false) {
            throw new \RuntimeException('Não foi possível gravar o arquivo enviado.');
        }

        $material->disk = $disk;
        $material->path = $path;
        $material->original_name = Str::limit(basename($file->getClientOriginalName()), 250, '');
        $material->mime_type = $file->getMimeType();
        $material->size_bytes = $file->getSize() ?: null;
        $material->url = null;

        return $path;
    }
}
