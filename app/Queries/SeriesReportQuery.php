<?php

namespace App\Queries;

use App\Enums\ClassroomRole;
use App\Enums\MeetingStatus;
use App\Enums\QuestionKind;
use App\Models\ClassMeeting;
use App\Models\Lesson;
use App\Models\Series;
use App\Support\ChurchCalendar;
use Illuminate\Support\Facades\DB;

/**
 * Relatório do trimestre (série): lição a lição e aluno a aluno.
 */
class SeriesReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function for(Series $series): array
    {
        $lessons = Lesson::query()
            ->whereBelongsTo($series)
            ->visible()
            ->orderByRaw('number IS NULL, number')
            ->orderBy('scheduled_for')
            ->with(['meetings' => fn ($q) => $q->active()])
            ->withCount(['questions as review_total' => fn ($q) => $q->where('kind', QuestionKind::Review)])
            ->get();

        $students = DB::table('classroom_user')
            ->join('users', 'users.id', '=', 'classroom_user.user_id')
            ->where('classroom_user.classroom_id', $series->classroom_id)
            ->where('classroom_user.role', ClassroomRole::Student->value)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name']);

        $studentIds = $students->pluck('id');
        $lessonIds = $lessons->pluck('id');

        $meetingIds = ClassMeeting::query()
            ->whereIn('lesson_id', $lessonIds)
            ->where('status', MeetingStatus::Held)
            ->whereNotNull('attendance_taken_at')
            ->pluck('id');

        $attendance = DB::table('attendances')
            ->whereIn('class_meeting_id', $meetingIds)
            ->whereIn('user_id', $studentIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) AS present')
            ->pluck('present', 'user_id');

        $presentByMeeting = DB::table('attendances')
            ->whereIn('class_meeting_id', $meetingIds)
            ->groupBy('class_meeting_id')
            ->selectRaw('class_meeting_id, COUNT(*) AS present')
            ->pluck('present', 'class_meeting_id');

        $readingByStudent = DB::table('reading_checkins')
            ->whereIn('lesson_id', $lessonIds)
            ->whereIn('user_id', $studentIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(DISTINCT lesson_id) AS lessons')
            ->pluck('lessons', 'user_id');

        $readersByLesson = DB::table('reading_checkins')
            ->whereIn('lesson_id', $lessonIds)
            ->whereIn('user_id', $studentIds)
            ->groupBy('lesson_id')
            ->selectRaw('lesson_id, COUNT(DISTINCT user_id) AS readers')
            ->pluck('readers', 'lesson_id');

        $reviewByStudent = DB::table('question_attempts')
            ->join('lesson_questions', 'lesson_questions.id', '=', 'question_attempts.lesson_question_id')
            ->whereIn('lesson_questions.lesson_id', $lessonIds)
            ->whereIn('question_attempts.user_id', $studentIds)
            ->groupBy('question_attempts.user_id')
            ->selectRaw('question_attempts.user_id, COUNT(*) AS answered')
            ->pluck('answered', 'user_id');

        $badges = DB::table('user_badges')
            ->where('series_id', $series->id)
            ->whereIn('user_id', $studentIds)
            ->get(['user_id', 'badge'])
            ->groupBy('user_id');

        $taught = $lessons->filter(fn (Lesson $l) => $l->meetings->contains(fn ($m) => $m->held_on->lte(ChurchCalendar::today())))->count();
        $reviewTotal = (int) $lessons->sum('review_total');
        $studentCount = max($students->count(), 1);

        return [
            'series' => [
                'id' => $series->id,
                'title' => $series->title,
                'starts_on' => $series->starts_on ? ChurchCalendar::formatShort($series->starts_on) : null,
                'ends_on' => $series->ends_on ? ChurchCalendar::formatShort($series->ends_on) : null,
            ],
            'totals' => [
                'lessons' => $lessons->count(),
                'taught' => $taught,
                'meetings_with_attendance' => $meetingIds->count(),
                'review_total' => $reviewTotal,
                'students' => $students->count(),
            ],
            'lessons' => $lessons->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->displayTitle(),
                'dates' => $lesson->meetings->map(fn ($m) => ChurchCalendar::formatShort($m->held_on))->all(),
                'present' => $lesson->meetings->map(fn ($m) => $presentByMeeting[$m->id] ?? null)->filter(fn ($v) => $v !== null)->values()->all(),
                'readers_rate' => round(((int) ($readersByLesson[$lesson->id] ?? 0)) / $studentCount * 100),
            ])->values(),
            'students' => $students->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'present' => (int) ($attendance[$s->id] ?? 0),
                'lessons_studied' => (int) ($readingByStudent[$s->id] ?? 0),
                'review_answered' => (int) ($reviewByStudent[$s->id] ?? 0),
                'badges' => $badges->get($s->id, collect())->pluck('badge')->all(),
            ])->values(),
        ];
    }
}
