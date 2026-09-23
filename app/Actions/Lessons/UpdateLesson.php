<?php

namespace App\Actions\Lessons;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateLesson
{
    public function __construct(
        private readonly EnsureSeriesBelongsToClassroom $ensureSeries,
    ) {}

    /**
     * O slug só pode mudar enquanto a lição é rascunho: depois de publicada,
     * o link já pode ter sido compartilhado no WhatsApp.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Lesson $lesson, array $data): Lesson
    {
        return DB::transaction(function () use ($lesson, $data) {
            $lesson->fill(Arr::except($data, CreateLesson::NON_FILLABLE));

            if (array_key_exists('series_id', $data)) {
                $lesson->series_id = $this->ensureSeries->handle($lesson->classroom, $data['series_id']);
            }

            if ($lesson->status === LessonStatus::Draft && filled($data['slug'] ?? null)) {
                $lesson->slug = $data['slug'];
            }

            $lesson->save();

            if (array_key_exists('author_ids', $data) && is_array($data['author_ids'])) {
                $lesson->authors()->sync($data['author_ids']);
            }

            return $lesson;
        });
    }
}
