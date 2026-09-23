<?php

namespace App\Actions\Materials;

use App\Models\LessonMaterial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateLessonMaterial
{
    public function __construct(private readonly StoreLessonMaterial $store) {}

    /**
     * O tipo do material não muda depois de criado; para trocar o tipo,
     * remove-se o material e cria-se outro.
     *
     * @param  array<string, mixed>  $data  dados validados por LessonMaterialRequest
     */
    public function handle(LessonMaterial $material, array $data, ?UploadedFile $file = null): LessonMaterial
    {
        $material->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ]);

        if (! $material->hasFile() && array_key_exists('url', $data) && $file === null) {
            $material->url = $data['url'];
        }

        $previous = $material->hasFile() ? [$material->disk, $material->path] : null;

        if ($file !== null && $material->type->acceptsUpload()) {
            $this->store->storeFile($material->lesson, $file, $material);
        }

        $material->save();

        if ($previous !== null && $previous[1] !== $material->path) {
            Storage::disk((string) $previous[0])->delete((string) $previous[1]);
        }

        return $material;
    }
}
