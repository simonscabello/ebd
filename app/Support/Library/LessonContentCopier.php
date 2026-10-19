<?php

namespace App\Support\Library;

use App\Actions\Lessons\GenerateLessonSlug;
use App\Actions\Lessons\SyncLessonSearchText;
use App\Enums\LessonStatus;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cria uma lição em rascunho a partir do retrato de uma lição (formato de
 * SeriesSnapshot), com blocos, leituras e materiais. Os arquivos são copiados
 * para a pasta da lição nova; os copiados ficam em $copiedFiles para quem
 * chama apagar se algo der errado depois. Áudio nunca é copiado.
 */
class LessonContentCopier
{
    /** @var list<array{0: string, 1: string}> */
    public array $copiedFiles = [];

    public function __construct(
        private GenerateLessonSlug $slugs,
        private SyncLessonSearchText $search,
    ) {}

    /**
     * @param  array<string, mixed>  $data  uma lição do retrato
     */
    public function copy(array $data, Series $series, Classroom $classroom, User $by, ?int $sourceLessonId): Lesson
    {
        $lesson = new Lesson([
            'number' => $data['number'],
            'title' => $data['title'],
            'summary' => $data['summary'],
            'bible_reference' => $data['bible_reference'],
            'key_verse' => $data['key_verse'],
            'goal' => $data['goal'],
            'content' => $data['content'],
            'visibility' => $data['visibility'],
        ]);
        $lesson->forceFill([
            'classroom_id' => $classroom->id,
            'series_id' => $series->id,
            'slug' => $this->slugs->handle((string) $data['title'], $classroom),
            'status' => LessonStatus::Draft,
            'created_by' => $by->id,
            'source_lesson_id' => $sourceLessonId,
        ])->save();

        foreach ($data['blocks'] as $block) {
            $lesson->blocks()->create($block);
        }

        foreach ($data['readings'] as $reading) {
            $lesson->readings()->create($reading);
        }

        foreach ($data['materials'] as $material) {
            $file = $material['file'];
            unset($material['file']);
            $model = $lesson->materials()->make($material);

            if ($file !== null) {
                $disk = (string) $file['disk'];
                $path = "materials/{$lesson->id}/".Str::random(16).'-'.basename((string) $file['path']);

                if (! Storage::disk($disk)->copy((string) $file['path'], $path)) {
                    throw new RuntimeException("Não foi possível copiar o arquivo \"{$material['title']}\".");
                }

                $this->copiedFiles[] = [$disk, $path];
                $model->forceFill([
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $file['original_name'],
                    'mime_type' => $file['mime_type'],
                    'size_bytes' => $file['size_bytes'],
                ]);
            }

            $model->save();
        }

        $this->search->handle($lesson);

        return $lesson;
    }

    /** Apaga os arquivos já copiados (depois de uma falha). */
    public function discardFiles(): void
    {
        foreach ($this->copiedFiles as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        $this->copiedFiles = [];
    }
}
