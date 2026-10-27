<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Preenche a lição atual de cada classe a partir da agenda, como a lição da
 * semana era escolhida até aqui: a do próximo encontro (hoje incluído) com
 * lição; sem nenhum, a do último encontro realizado. Lições com encontro
 * realizado antes de hoje (menos a atual) ficam como estudadas, na data do
 * último encontro: aproximação, já que antes não havia "concluir".
 *
 * Só preenche, não apaga nada; classes já preenchidas ficam como estão.
 * Os encontros planejados à frente são arrumados pelo comando ebd:licao-atual.
 */
return new class extends Migration
{
    public function up(): void
    {
        $classrooms = DB::table('classrooms')
            ->join('churches', 'churches.id', '=', 'classrooms.church_id')
            ->where('churches.is_official', false)
            ->whereNull('classrooms.current_lesson_id')
            ->whereNull('classrooms.current_series_id')
            ->get(['classrooms.id', 'churches.timezone']);

        foreach ($classrooms as $classroom) {
            $this->backfill((int) $classroom->id, CarbonImmutable::now($classroom->timezone)->toDateString());
        }
    }

    private function backfill(int $classroomId, string $today): void
    {
        $meetings = fn () => DB::table('class_meetings')
            ->where('classroom_id', $classroomId)
            ->whereIn('lesson_id', DB::table('lessons')->whereNull('deleted_at')->select('id'));

        $current = $meetings()->where('status', '!=', 'cancelled')->where('held_on', '>=', $today)->orderBy('held_on')->value('lesson_id')
            ?? $meetings()->where('status', 'held')->where('held_on', '<', $today)->orderByDesc('held_on')->value('lesson_id');

        DB::update(<<<'SQL'
            UPDATE lessons SET studied_at = held.last
            FROM (
                SELECT lesson_id, MAX(held_on)::timestamp AS last FROM class_meetings
                WHERE classroom_id = ? AND status = 'held' AND held_on < ?
                GROUP BY lesson_id
            ) held
            WHERE held.lesson_id = lessons.id AND lessons.studied_at IS NULL AND lessons.id <> ?
            SQL, [$classroomId, $today, $current ?? 0]);

        $seriesId = $current !== null
            ? DB::table('lessons')->where('id', $current)->value('series_id')
            : DB::table('lessons')->where('classroom_id', $classroomId)->whereNull('deleted_at')
                ->whereNotNull('series_id')->whereNotNull('studied_at')->orderByDesc('studied_at')->value('series_id');

        $firstMeeting = $current !== null
            ? $meetings()->where('lesson_id', $current)->where('status', '!=', 'cancelled')->where('held_on', '<=', $today)->min('held_on')
            : null;

        DB::table('classrooms')->where('id', $classroomId)->update([
            'current_lesson_id' => $current,
            'current_series_id' => $seriesId,
            'current_lesson_started_at' => $current === null ? null : ($firstMeeting ?? now()),
        ]);
    }

    public function down(): void
    {
        DB::table('classrooms')->update(['current_lesson_id' => null, 'current_series_id' => null, 'current_lesson_started_at' => null]);
        DB::table('lessons')->update(['studied_at' => null]);
    }
};
