<?php

namespace Database\Seeders;

use App\Enums\ClassroomRole;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\User;
use App\Queries\CurrentLessonQuery;
use App\Support\ChurchCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Presenças e leituras de exemplo para os painéis de evolução e "Minha semana"
 * não ficarem vazios em desenvolvimento.
 */
class EngagementSeeder extends Seeder
{
    public function run(): void
    {
        $jovens = Classroom::query()->where('slug', 'jovens')->firstOrFail();
        $today = ChurchCalendar::today();

        // Alunos entraram há dois meses (quem entrou há menos de 2 semanas não
        // aparece em "precisam de atenção").
        DB::table('classroom_user')
            ->where('classroom_id', $jovens->id)
            ->update(['created_at' => $today->subMonths(2)]);

        $students = $jovens->members()->wherePivot('role', ClassroomRole::Student->value)->orderBy('name')->get();
        $rafael = $students->firstWhere('name', 'Rafael Lima');

        $held = ClassMeeting::query()
            ->whereBelongsTo($jovens)
            ->where('status', MeetingStatus::Held)
            ->orderBy('held_on')
            ->get();

        foreach ($held as $index => $meeting) {
            $recent = $index >= $held->count() - 2;

            foreach ($students as $student) {
                // Rafael sumiu nos dois últimos domingos.
                if ($recent && $student->is($rafael)) {
                    continue;
                }

                DB::table('attendances')->insertOrIgnore([
                    'class_meeting_id' => $meeting->id,
                    'user_id' => $student->id,
                    'created_at' => now(),
                ]);
            }

            $meeting->forceFill(['attendance_taken_at' => $meeting->held_on, 'visitors_count' => $index % 3])->save();
        }

        // João estudou nos últimos dias, na lição da semana.
        $joao = User::query()->where('email', 'aluno@ebd.test')->first();
        $lesson = $joao ? app(CurrentLessonQuery::class)->for($jovens, $joao)->lesson : null;

        if ($joao !== null && $lesson !== null) {
            foreach (range(1, 4) as $daysAgo) {
                $day = $today->subDays($daysAgo);

                DB::table('reading_checkins')->insertOrIgnore([
                    'user_id' => $joao->id,
                    'lesson_id' => $lesson->id,
                    'weekday' => $day->dayOfWeekIso,
                    'read_on' => $day->toDateString(),
                    'created_at' => now(),
                ]);
            }
        }
    }
}
