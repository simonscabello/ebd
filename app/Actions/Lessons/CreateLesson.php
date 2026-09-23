<?php

namespace App\Actions\Lessons;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Cria uma lição (sempre como rascunho) em uma classe.
 */
class CreateLesson
{
    /** Campos tratados explicitamente pela action (não entram via mass assignment). */
    public const NON_FILLABLE = ['classroom_id', 'series_id', 'slug', 'author_ids'];

    public function __construct(
        private readonly GenerateLessonSlug $generateSlug,
        private readonly EnsureSeriesBelongsToClassroom $ensureSeries,
    ) {}

    /**
     * @param  array<string, mixed>  $data  dados validados por LessonRequest
     */
    public function handle(User $creator, Classroom $classroom, array $data): Lesson
    {
        $seriesId = $this->ensureSeries->handle($classroom, $data['series_id'] ?? null);
        $authorIds = is_array($data['author_ids'] ?? null) ? $data['author_ids'] : [$creator->id];

        return DB::transaction(function () use ($creator, $classroom, $data, $seriesId, $authorIds) {
            $lesson = new Lesson(Arr::except($data, self::NON_FILLABLE));
            $lesson->classroom_id = $classroom->id;
            $lesson->series_id = $seriesId;
            $lesson->created_by = $creator->id;
            $lesson->slug = is_string($data['slug'] ?? null) && $data['slug'] !== ''
                ? $data['slug']
                : $this->generateSlug->handle($lesson->title, $classroom);
            $lesson->save();

            $lesson->authors()->sync($authorIds);

            return $lesson;
        });
    }
}
