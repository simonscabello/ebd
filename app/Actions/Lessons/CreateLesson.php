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
    public const NON_FILLABLE = ['series_id', 'slug', 'author_ids'];

    public function __construct(
        private readonly GenerateLessonSlug $generateSlug,
        private readonly EnsureSeriesBelongsToClassroom $ensureSeries,
    ) {}

    /**
     * @param  array{title: string, series_id?: int|null, slug?: string|null, author_ids?: list<int>|null}&array<string, mixed>  $data
     */
    public function handle(User $creator, Classroom $classroom, array $data): Lesson
    {
        $seriesId = $this->ensureSeries->handle($classroom, $data['series_id'] ?? null);

        return DB::transaction(function () use ($creator, $classroom, $data, $seriesId) {
            $lesson = new Lesson(Arr::except($data, self::NON_FILLABLE));
            $lesson->classroom_id = $classroom->id;
            $lesson->series_id = $seriesId;
            $lesson->created_by = $creator->id;
            $lesson->slug = filled($data['slug'] ?? null)
                ? $data['slug']
                : $this->generateSlug->handle($data['title'], $classroom);
            $lesson->save();

            $lesson->authors()->sync($data['author_ids'] ?? [$creator->id]);

            return $lesson;
        });
    }
}
