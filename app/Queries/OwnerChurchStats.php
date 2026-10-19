<?php

namespace App\Queries;

use App\Enums\AiUsageKind;
use App\Models\Church;
use App\Support\ChurchCalendar;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Números de cada igreja para o painel do dono. Só contagens e datas: nada
 * de nomes de pessoas, títulos ou perguntas de dentro da igreja.
 */
class OwnerChurchStats
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $monthStart = ChurchCalendar::today()->startOfMonth()->utc();

        $usage = fn (AiUsageKind $kind) => fn (Builder $query) => $query
            ->from('ai_usages')
            ->selectRaw('count(*)')
            ->whereColumn('ai_usages.church_id', 'churches.id')
            ->where('kind', $kind->value)
            ->where('created_at', '>=', $monthStart);

        $classroomIds = fn (Builder $query) => $query->from('classrooms')->select('id')->whereColumn('classrooms.church_id', 'churches.id');

        return array_values(Church::query()
            ->orderBy('id')
            ->withCount('classrooms')
            ->selectSub(fn (Builder $query) => $query
                ->from('classroom_user')
                ->selectRaw('count(distinct user_id)')
                ->whereIn('classroom_id', $classroomIds), 'people_count')
            ->selectSub(fn (Builder $query) => $query
                ->selectRaw('greatest(
                    (select max(reading_checkins.created_at) from reading_checkins join lessons on lessons.id = reading_checkins.lesson_id join classrooms on classrooms.id = lessons.classroom_id where classrooms.church_id = churches.id),
                    (select max(attendances.created_at) from attendances join class_meetings on class_meetings.id = attendances.class_meeting_id join classrooms on classrooms.id = class_meetings.classroom_id where classrooms.church_id = churches.id),
                    (select max(lessons.updated_at) from lessons join classrooms on classrooms.id = lessons.classroom_id where classrooms.church_id = churches.id)
                )'), 'last_activity_at')
            ->selectSub($usage(AiUsageKind::Question), 'questions_this_month')
            ->selectSub($usage(AiUsageKind::Audio), 'audios_this_month')
            ->addSelect('churches.*')
            ->get()
            ->map(fn (Church $church) => [
                'id' => $church->id,
                'name' => $church->name,
                'created_at' => $church->created_at?->toDateString(),
                'classrooms_count' => (int) $church->getAttribute('classrooms_count'),
                'people_count' => (int) $church->getAttribute('people_count'),
                'last_activity_at' => ($last = $church->getAttribute('last_activity_at'))
                    ? ChurchCalendar::formatShort(Carbon::parse((string) $last)->timezone(ChurchCalendar::timezone()))
                    : null,
                'questions_this_month' => (int) $church->getAttribute('questions_this_month'),
                'audios_this_month' => (int) $church->getAttribute('audios_this_month'),
                'helper_enabled' => $church->helper_enabled,
                'helper_daily_limit' => $church->helper_daily_limit,
                'audio_enabled' => $church->audio_enabled,
                'audio_monthly_limit' => $church->audio_monthly_limit,
                'shows_bible_text' => $church->shows_bible_text,
            ])
            ->all());
    }
}
