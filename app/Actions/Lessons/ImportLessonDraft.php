<?php

namespace App\Actions\Lessons;

use App\Enums\ContentAudience;
use App\Enums\LessonStatus;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grava uma lição inteira de uma vez (dados, leituras, blocos e materiais com
 * link), sempre como rascunho. Usada pelo lesson:import e pelo servidor MCP.
 *
 * Com $existing, atualiza só os campos informados, e cada lista informada
 * (readings, blocks, materials) substitui a atual. Materiais com arquivo
 * continuam: arquivos são enviados e removidos pelo app.
 */
class ImportLessonDraft
{
    public const array LESSON_FIELDS = ['number', 'title', 'summary', 'bible_reference', 'key_verse', 'goal', 'content', 'visibility'];

    public function __construct(
        private readonly CreateLesson $create,
        private readonly UpdateLesson $update,
        private readonly SyncLessonSearchText $syncSearch,
    ) {}

    /**
     * @param  array<string, mixed>  $data  campos da lição, series_id, meeting_on (só na criação) e as listas
     */
    public function handle(User $author, Classroom $classroom, array $data, ?Lesson $existing = null): Lesson
    {
        if ($existing !== null && $existing->status !== LessonStatus::Draft) {
            throw ValidationException::withMessages([
                'lesson' => "A {$existing->displayTitle()} já foi publicada. Edite-a pelo app.",
            ]);
        }

        $lesson = DB::transaction(function () use ($author, $classroom, $data, $existing) {
            $fields = Arr::only($data, [...self::LESSON_FIELDS, 'series_id']);

            if ($existing !== null) {
                $lesson = $this->update->handle($existing, $fields);
            } else {
                $lesson = $this->create->handle($author, $classroom, $fields + [
                    'author_ids' => [$author->id],
                    'meeting_on' => $data['meeting_on'] ?? null,
                ]);
            }

            if (is_array($data['readings'] ?? null)) {
                $lesson->readings()->delete();

                foreach ($data['readings'] as $reading) {
                    $lesson->readings()->create($reading);
                }
            }

            if (is_array($data['blocks'] ?? null)) {
                $lesson->blocks()->delete();

                foreach ($data['blocks'] as $block) {
                    $lesson->blocks()->create(SaveLessonBlock::normalize($block));
                }
            }

            if (is_array($data['materials'] ?? null)) {
                $lesson->materials()->whereNull('path')->delete();

                foreach ($data['materials'] as $material) {
                    $material['audience'] ??= ContentAudience::Student->value;
                    $record = new LessonMaterial($material);
                    $record->lesson_id = $lesson->id;
                    $record->save();
                }
            }

            return $lesson;
        });

        $this->syncSearch->handle($lesson);

        return $lesson;
    }
}
