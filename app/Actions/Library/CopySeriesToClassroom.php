<?php

namespace App\Actions\Library;

use App\Actions\Series\CreateSeries;
use App\Enums\Weekday;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Series;
use App\Models\SeriesVersion;
use App\Models\User;
use App\Support\Library\LessonContentCopier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * "Usar numa turma": a turma recebe uma cópia editável da versão do estudo
 * oficial. As lições nascem em rascunho (ninguém é avisado até a igreja
 * publicar) e guardam de onde vieram, para o aviso de versão nova.
 */
class CopySeriesToClassroom
{
    public function __construct(
        private CreateSeries $createSeries,
        private LessonContentCopier $copier,
    ) {}

    public function handle(User $by, SeriesVersion $version, Classroom $target): Series
    {
        Gate::forUser($by)->authorize('manageContent', $target);

        try {
            return DB::transaction(function () use ($by, $version, $target) {
                $series = $this->createSeries->handle($target, [
                    'title' => $version->snapshot['title'],
                    'description' => $version->snapshot['description'],
                ]);
                $series->forceFill([
                    'source_series_id' => $version->series_id,
                    'source_version' => $version->version,
                ])->save();

                // O plano de leitura foi feito para o dia da turma de origem (a
                // biblioteca oficial, que fica no domingo).
                $fromMeeting = $version->series?->classroom->meeting_weekday ?? Weekday::Sunday;

                foreach ($version->snapshot['lessons'] as $lesson) {
                    $this->copier->copy($lesson, $series, $target, $by, $lesson['source_lesson_id'], $fromMeeting);
                }

                AuditLog::query()->create([
                    'user_id' => $by->id,
                    'source' => 'web',
                    'tool' => 'library.copy',
                    'arguments' => ['series_id' => $version->series_id, 'version' => $version->version],
                    'subject_type' => $series->getMorphClass(),
                    'subject_id' => $series->id,
                    'classroom_id' => $target->id,
                    'status' => AuditLog::STATUS_OK,
                ]);

                return $series;
            });
        } catch (Throwable $e) {
            $this->copier->discardFiles();

            throw $e;
        }
    }
}
