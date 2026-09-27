<?php

namespace App\Queries;

use App\Enums\ClassroomRole;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Queries\Data\AttendanceBook;
use App\Queries\Data\Period;
use App\Support\ChurchCalendar;
use App\Support\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * Monta o livro de chamada de uma classe em quatro consultas: alunos, início de
 * cada um, domingos com chamada do período e as presenças desses domingos.
 */
class AttendanceBookQuery
{
    public function for(Classroom $classroom, ?Period $period = null): AttendanceBook
    {
        $period ??= Period::allTime();
        $today = ChurchCalendar::todayString();

        $students = DB::table('classroom_user')
            ->join('users', 'users.id', '=', 'classroom_user.user_id')
            ->where('classroom_user.classroom_id', $classroom->id)
            ->where('classroom_user.role', ClassroomRole::Student->value)
            ->orderBy('users.name')
            ->selectRaw('users.id, users.name, users.phone, users.gender, users.birth_date, '.ChurchCalendar::JOINED_ON_SQL.' AS joined_on', [ChurchCalendar::timezone()])
            ->get()
            ->map(fn (object $row) => (object) [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'phone' => is_string($row->phone) ? $row->phone : null,
                'gender' => is_string($row->gender) ? $row->gender : null,
                'birth_date' => $row->birth_date !== null ? substr((string) $row->birth_date, 0, 10) : null,
                'joined_on' => substr((string) $row->joined_on, 0, 10),
            ]);

        $meetings = ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->where('status', MeetingStatus::Held)
            ->whereNotNull('attendance_taken_at')
            ->whereDate('held_on', '<=', min($period->to, $today))
            ->when($period->from, fn ($query, string $from) => $query->whereDate('held_on', '>=', $from))
            ->chronological()
            ->with(['lesson' => fn ($query) => $query->withTrashed()->select(['id', 'number', 'title'])])
            ->get();

        $presence = [];

        foreach (DB::table('attendances')->whereIn('class_meeting_id', $meetings->modelKeys())->get(['class_meeting_id', 'user_id']) as $row) {
            $presence[(int) $row->class_meeting_id][] = (int) $row->user_id;
        }

        return new AttendanceBook(
            students: $students,
            since: Enrollment::sinceMap($classroom->id),
            meetings: $meetings,
            presence: $presence,
            period: $period,
        );
    }
}
