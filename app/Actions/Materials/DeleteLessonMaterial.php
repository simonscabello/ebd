<?php

namespace App\Actions\Materials;

use App\Models\LessonMaterial;
use Illuminate\Support\Facades\Storage;

class DeleteLessonMaterial
{
    public function handle(LessonMaterial $material): void
    {
        $file = $material->hasFile() ? [$material->disk, $material->path] : null;

        $material->delete();

        if ($file !== null) {
            Storage::disk((string) $file[0])->delete((string) $file[1]);
        }
    }
}
