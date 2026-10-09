<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\EbdServer;
use App\Mcp\Tools\GetClassroomOverview;
use App\Mcp\Tools\GetLesson;
use App\Mcp\Tools\GetStudentProgress;
use App\Mcp\Tools\ListClassrooms;
use App\Mcp\Tools\ListLessons;
use App\Mcp\Tools\ListMeetings;
use App\Mcp\Tools\ListStudents;
use App\Models\AuditLog;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ferramentas de consulta do servidor MCP: o agente vê só o que a pessoa
 * logada gerencia, e consultar não grava nada no histórico.
 */
class McpReadToolsTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private Classroom $other;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'Europe/Madrid']);
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 24)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['name' => 'Jovens', 'slug' => 'jovens']);
        $this->other = Classroom::factory()->create(['name' => 'Adultos', 'slug' => 'adultos']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    public function test_students_and_guests_do_not_see_the_tools(): void
    {
        $student = User::factory()->studentOf($this->classroom)->create();

        EbdServer::actingAs($student)->tools()->assertNotRegistered([ListClassrooms::class, GetLesson::class]);
        EbdServer::actingAs($this->teacher)->tools()->assertRegistered([ListClassrooms::class, GetLesson::class]);
    }

    public function test_teacher_lists_only_the_classrooms_they_manage(): void
    {
        ClassMeeting::factory()->for($this->classroom)->on('2026-09-27')->create();

        EbdServer::actingAs($this->teacher)->tool(ListClassrooms::class)
            ->assertOk()
            ->assertSee(['"slug":"jovens"', '"held_on":"2026-09-27"'])
            ->assertDontSee('adultos');

        EbdServer::actingAs(User::factory()->admin()->create())->tool(ListClassrooms::class)
            ->assertSee(['jovens', 'adultos']);
    }

    public function test_other_classrooms_are_not_found_and_the_error_lists_the_valid_ones(): void
    {
        EbdServer::actingAs($this->teacher)->tool(ListLessons::class, ['classroom' => 'adultos'])
            ->assertHasErrors(['Classe "adultos" não encontrada', 'jovens (Jovens)']);

        $foreign = Lesson::factory()->for($this->other)->create();

        EbdServer::actingAs($this->teacher)->tool(GetLesson::class, ['lesson_id' => $foreign->id])
            ->assertHasErrors(["Lição {$foreign->id} não encontrada"]);
    }

    public function test_get_lesson_includes_markdown_content_and_teacher_blocks(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create(['title' => 'A fé de Abraão', 'content' => "## Introdução\nTexto"]);
        LessonBlock::factory()->for($lesson)->create(['kind' => 'teacher_note', 'audience' => 'teacher', 'body' => 'Abrir com oração']);

        EbdServer::actingAs($this->teacher)->tool(GetLesson::class, ['lesson_id' => $lesson->id])
            ->assertOk()
            ->assertSee(['A fé de Abraão', '## Introdução', 'Abrir com oração', '"status":"draft"']);
    }

    public function test_list_lessons_filters_by_title_without_accents(): void
    {
        Lesson::factory()->for($this->classroom)->create(['title' => 'A oração do justo']);
        Lesson::factory()->for($this->classroom)->create(['title' => 'Outra lição']);

        EbdServer::actingAs($this->teacher)->tool(ListLessons::class, ['classroom' => 'jovens', 'q' => 'oracao'])
            ->assertOk()
            ->assertSee(['A oração do justo', '"count":1'])
            ->assertDontSee('Outra lição');
    }

    public function test_meetings_can_include_who_was_present(): void
    {
        $ana = User::factory()->studentOf($this->classroom)->create(['name' => 'Ana']);
        User::factory()->studentOf($this->classroom)->create(['name' => 'Bia']);
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-09-20')->held()->create(['attendance_taken_at' => now(), 'visitors_count' => 2]);
        DB::table('attendances')->insert(['class_meeting_id' => $meeting->id, 'user_id' => $ana->id, 'recorded_by' => $this->teacher->id, 'created_at' => now()]);

        EbdServer::actingAs($this->teacher)->tool(ListMeetings::class, ['classroom' => 'jovens', 'with_attendance' => true])
            ->assertOk()
            ->assertSee(['"held_on":"2026-09-20"', '"present":["Ana"]', '"visitors_count":2'])
            ->assertDontSee('Bia');
    }

    public function test_students_list_and_progress(): void
    {
        $ana = User::factory()->managed()->studentOf($this->classroom)->create(['name' => 'Ana Lúcia']);

        EbdServer::actingAs($this->teacher)->tool(ListStudents::class, ['classroom' => 'jovens'])
            ->assertOk()
            ->assertSee(['Ana Lúcia', '"is_managed":true']);

        EbdServer::actingAs($this->teacher)->tool(GetStudentProgress::class, ['classroom' => 'jovens', 'student' => 'ana lucia'])
            ->assertOk()
            ->assertSee(['"streak"', "\"id\":{$ana->id}"]);
    }

    public function test_ambiguous_student_names_return_the_candidates(): void
    {
        $a = User::factory()->studentOf($this->classroom)->create(['name' => 'Ana Silva']);
        $b = User::factory()->studentOf($this->classroom)->create(['name' => 'Ana Souza']);

        EbdServer::actingAs($this->teacher)->tool(GetStudentProgress::class, ['classroom' => 'jovens', 'student' => 'Ana'])
            ->assertHasErrors(['"Ana" é ambíguo', "{$a->id} — Ana Silva", "{$b->id} — Ana Souza"]);
    }

    public function test_classroom_overview(): void
    {
        Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create(['title' => 'Lição da semana']);

        EbdServer::actingAs($this->teacher)->tool(GetClassroomOverview::class, ['classroom' => 'Jovens'])
            ->assertOk()
            ->assertSee(['Lição da semana', '"kpis"']);

        $this->assertSame(0, AuditLog::query()->count());
    }
}
