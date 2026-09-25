<?php

namespace App\Queries;

use App\Enums\ClassroomRole;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Evolução da classe para o professor: presença por encontro, estudo em casa
 * por lição e alunos que precisam de atenção.
 *
 * Denominadores respeitam a data de entrada do aluno na classe: quem entrou
 * depois de um encontro não conta como falta nele.
 */
class ClassroomInsightsQuery
{
    /**
     * @return array{kpis: array<string, mixed>, meetings: Collection<int, covariant array<string, mixed>>, lessons: Collection<int, covariant array<string, mixed>>, students: list<array<string, mixed>>, thresholds: array<string, int>}
     */
    public function for(Classroom $classroom, int $meetingsLimit = 12): array
    {
        $today = ChurchCalendar::today();

        /** @var Collection<int, object{user_id: int, name: string, joined_on: string}> $students */
        $students = DB::table('classroom_user')
            ->join('users', 'users.id', '=', 'classroom_user.user_id')
            ->where('classroom_user.classroom_id', $classroom->id)
            ->where('classroom_user.role', ClassroomRole::Student->value)
            ->orderBy('users.name')
            ->get(['users.id as user_id', 'users.name', DB::raw('classroom_user.created_at::date as joined_on')]);

        $studentIds = $students->pluck('user_id')->all();

        $meetings = ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->where('status', MeetingStatus::Held)
            ->whereDate('held_on', '<=', $today->toDateString())
            ->orderByDesc('held_on')
            ->limit($meetingsLimit)
            ->with('lesson:id,title,number')
            ->get()
            ->reverse()
            ->values();

        $presence = DB::table('attendances')
            ->whereIn('class_meeting_id', $meetings->pluck('id'))
            ->get(['class_meeting_id', 'user_id'])
            ->groupBy('class_meeting_id')
            ->map(fn ($rows) => $rows->pluck('user_id')->map(fn ($id) => (int) $id)->all());

        $enrolledOn = fn (string $date) => $students->filter(fn ($s) => $s->joined_on <= $date)->pluck('user_id')->all();

        $attendanceSeries = $meetings->map(function (ClassMeeting $m) use ($presence, $enrolledOn) {
            $date = $m->held_on->toDateString();
            $enrolled = $enrolledOn($date);
            $present = array_intersect($presence->get($m->id, []), $enrolled);

            return [
                'id' => $m->id,
                'date' => $date,
                'date_short' => ChurchCalendar::formatShort($m->held_on),
                'lesson' => $m->lesson?->displayTitle(),
                'taken' => $m->hasAttendance(),
                'present' => count($present),
                'enrolled' => count($enrolled),
                'visitors' => $m->visitors_count,
                'rate' => $m->hasAttendance() && count($enrolled) > 0 ? round(count($present) / count($enrolled) * 100) : null,
            ];
        })->values();

        // Estudo em casa por lição (lições dos encontros acima).
        $lessonIds = $meetings->pluck('lesson_id')->filter()->unique()->values();

        $reading = DB::table('reading_checkins')
            ->whereIn('lesson_id', $lessonIds)
            ->whereIn('user_id', $studentIds)
            ->groupBy('lesson_id')
            ->selectRaw('lesson_id, COUNT(DISTINCT user_id) AS readers, COUNT(*) AS checkins')
            ->get()
            ->keyBy('lesson_id');

        $studentCount = max(count($studentIds), 1);

        $lessons = $lessonIds->map(function (int $lessonId) use ($meetings, $reading, $studentCount) {
            $meeting = $meetings->firstWhere('lesson_id', $lessonId);
            $r = $reading->get($lessonId);

            return [
                'id' => $lessonId,
                'title' => $meeting?->lesson?->displayTitle(),
                'readers' => (int) ($r->readers ?? 0),
                'readers_rate' => round(((int) ($r->readers ?? 0)) / $studentCount * 100),
                'avg_days' => $r && $r->readers > 0 ? round($r->checkins / $r->readers, 1) : 0,
            ];
        })->values();

        $studentsView = $this->studentsAtRisk($students, $attendanceSeries->all(), $presence->all(), $today);

        $taken = $attendanceSeries->filter(fn ($m) => $m['taken'])->take(-4);

        // Quem marcou alguma leitura nos últimos 7 dias (qualquer lição da classe).
        $studyingNow = DB::table('reading_checkins')
            ->join('lessons', 'lessons.id', '=', 'reading_checkins.lesson_id')
            ->where('lessons.classroom_id', $classroom->id)
            ->whereIn('reading_checkins.user_id', $studentIds)
            ->where('reading_checkins.read_on', '>', $today->subDays(7)->toDateString())
            ->distinct()
            ->count('reading_checkins.user_id');

        return [
            'kpis' => [
                'students' => count($studentIds),
                'attendance_rate' => $taken->isNotEmpty() && $taken->sum('enrolled') > 0
                    ? round($taken->sum('present') / $taken->sum('enrolled') * 100)
                    : null,
                'studying_rate' => $studentIds !== [] ? round($studyingNow / count($studentIds) * 100) : null,
                'at_risk' => collect($studentsView)->where('at_risk', true)->count(),
            ],
            'meetings' => $attendanceSeries,
            'lessons' => $lessons,
            'students' => $studentsView,
            'thresholds' => [
                'missed_meetings' => (int) config('ebd.insights.missed_meetings'),
                'inactive_days' => (int) config('ebd.insights.inactive_days'),
            ],
        ];
    }

    /**
     * @param  Collection<int, object{user_id: int, name: string, joined_on: string}>  $students
     * @param  array<int, array<string, mixed>>  $meetings
     * @param  array<int|string, array<int>>  $presence
     * @return list<array<string, mixed>>
     */
    private function studentsAtRisk(Collection $students, array $meetings, array $presence, CarbonImmutable $today): array
    {
        $missedLimit = (int) config('ebd.insights.missed_meetings');
        $inactiveLimit = (int) config('ebd.insights.inactive_days');

        $lastReading = DB::table('reading_checkins')
            ->whereIn('user_id', $students->pluck('user_id'))
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(read_on) AS last_read')
            ->pluck('last_read', 'user_id');

        $lastPresence = DB::table('attendances')
            ->join('class_meetings', 'class_meetings.id', '=', 'attendances.class_meeting_id')
            ->whereIn('attendances.user_id', $students->pluck('user_id'))
            ->groupBy('attendances.user_id')
            ->selectRaw('attendances.user_id, MAX(class_meetings.held_on) AS last_present')
            ->pluck('last_present', 'user_id');

        $taken = collect($meetings)->filter(fn ($m) => $m['taken'])->values();

        return array_values($students->map(function ($student) use ($taken, $presence, $lastReading, $lastPresence, $today, $missedLimit, $inactiveLimit) {
            // Faltas seguidas nos encontros mais recentes com chamada.
            $missed = 0;
            foreach ($taken->reverse() as $meeting) {
                if ($meeting['date'] < $student->joined_on) {
                    break;
                }
                if (in_array($student->user_id, $presence[$meeting['id']] ?? [], true)) {
                    break;
                }
                $missed++;
            }

            $lastRead = $lastReading[$student->user_id] ?? null;
            $reference = $lastRead ?? $student->joined_on;
            $inactiveDays = (int) CarbonImmutable::parse(substr((string) $reference, 0, 10))->diffInDays($today);
            $isNew = CarbonImmutable::parse($student->joined_on)->diffInDays($today) < 14;

            $reasons = [];
            if ($missed >= $missedLimit) {
                $reasons[] = "{$missed} faltas seguidas";
            }
            if ($inactiveDays >= $inactiveLimit) {
                $reasons[] = $lastRead ? "{$inactiveDays} dias sem leitura" : 'ainda não marcou leitura';
            }

            return [
                'id' => $student->user_id,
                'name' => $student->name,
                'missed_in_a_row' => $missed,
                'last_read' => $lastRead ? ChurchCalendar::formatShort(CarbonImmutable::parse(substr((string) $lastRead, 0, 10))) : null,
                'last_present' => isset($lastPresence[$student->user_id]) ? ChurchCalendar::formatShort(CarbonImmutable::parse(substr((string) $lastPresence[$student->user_id], 0, 10))) : null,
                'at_risk' => ! $isNew && $reasons !== [],
                'reasons' => $reasons,
                'is_new' => $isNew,
            ];
        })->all());
    }
}
