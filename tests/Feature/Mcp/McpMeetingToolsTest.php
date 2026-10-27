<?php

namespace Tests\Feature\Mcp;

use App\Enums\MeetingStatus;
use App\Mcp\Servers\EbdServer;
use App\Mcp\Tools\AdvanceLessonTool;
use App\Mcp\Tools\CancelMeetingTool;
use App\Mcp\Tools\FinishMeetingTool;
use App\Mcp\Tools\RecordAttendanceTool;
use App\Mcp\Tools\SaveMeetingTool;
use App\Mcp\Tools\SetCurrentLessonTool;
use App\Models\AuditLog;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agenda e chamada pelo servidor MCP.
 */
class McpMeetingToolsTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'Europe/Madrid']);
        // Domingo, 27/09/2026, durante a EBD.
        $this->travelTo(now('Europe/Madrid')->setDate(2026, 9, 27)->setTime(10, 0));

        $this->classroom = Classroom::factory()->create(['slug' => 'jovens', 'name' => 'Jovens']);
        $this->teacher = User::factory()->teacherOf($this->classroom)->create();
    }

    private function student(string $name): User
    {
        return User::factory()->managed()->studentOf($this->classroom)->create(['name' => $name]);
    }

    public function test_attendance_add_mode_keeps_who_was_already_present(): void
    {
        $ana = $this->student('Ana Clara');
        $bia = $this->student('Beatriz');
        $this->student('Carlos');
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-09-27')->create();

        EbdServer::actingAs($this->teacher)->tool(RecordAttendanceTool::class, ['classroom' => 'jovens', 'meeting' => '2026-09-27', 'mode' => 'set', 'present' => ['ana clara'], 'visitors' => 2])
            ->assertOk();

        EbdServer::actingAs($this->teacher)->tool(RecordAttendanceTool::class, ['classroom' => 'jovens', 'meeting' => '2026-09-27', 'mode' => 'add', 'present' => ['Beatriz']])
            ->assertOk()
            ->assertSee(['"present":["Ana Clara","Beatriz"]', '"absent":["Carlos"]', '"visitors":2']);

        $meeting->refresh();
        $this->assertEqualsCanonicalizing([$ana->id, $bia->id], $meeting->attendances()->pluck('user_id')->all());
        $this->assertSame(MeetingStatus::Held, $meeting->status);

        $last = AuditLog::query()->latest('id')->first();
        $this->assertSame(['Ana Clara'], $last->changes['before']['present']);
        $this->assertSame(['Ana Clara', 'Beatriz'], $last->changes['after']['present']);
    }

    public function test_attendance_set_mode_replaces_the_list_and_rejects_unknown_names(): void
    {
        $this->student('Ana');
        $bia = $this->student('Bia');
        $meeting = ClassMeeting::factory()->for($this->classroom)->on('2026-09-27')->create();

        EbdServer::actingAs($this->teacher)->tool(RecordAttendanceTool::class, ['classroom' => 'jovens', 'meeting' => $meeting->id, 'mode' => 'set', 'present' => ['Ana', 'Bia']])->assertOk();
        EbdServer::actingAs($this->teacher)->tool(RecordAttendanceTool::class, ['classroom' => 'jovens', 'meeting' => $meeting->id, 'mode' => 'set', 'present' => [(string) $bia->id]])->assertOk();

        $this->assertSame([$bia->id], $meeting->attendances()->pluck('user_id')->all());

        EbdServer::actingAs($this->teacher)->tool(RecordAttendanceTool::class, ['classroom' => 'jovens', 'meeting' => $meeting->id, 'mode' => 'add', 'present' => ['Zé Ninguém']])
            ->assertHasErrors(['"Zé Ninguém" não é aluno(a) da classe']);
    }

    public function test_future_meetings_cannot_have_attendance(): void
    {
        $this->student('Ana');
        ClassMeeting::factory()->for($this->classroom)->on('2026-10-04')->create();

        EbdServer::actingAs($this->teacher)->tool(RecordAttendanceTool::class, ['classroom' => 'jovens', 'meeting' => '2026-10-04', 'mode' => 'add', 'present' => ['Ana']])
            ->assertHasErrors(['só pode ser feita no dia do domingo']);
    }

    public function test_save_meeting_adds_and_edits_keeping_omitted_fields(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create();

        EbdServer::actingAs($this->teacher)->tool(SaveMeetingTool::class, ['classroom' => 'jovens', 'held_on' => '2026-10-11', 'lesson_id' => $lesson->id, 'notes' => 'Trazer mapas'])
            ->assertOk();
        $meeting = ClassMeeting::query()->sole();

        EbdServer::actingAs($this->teacher)->tool(SaveMeetingTool::class, ['classroom' => 'jovens', 'meeting' => '2026-10-11', 'title' => 'Dia das crianças'])
            ->assertOk();

        $meeting->refresh();
        $this->assertSame(['Dia das crianças', 'Trazer mapas', $lesson->id], [$meeting->title, $meeting->notes, $meeting->lesson_id]);

        EbdServer::actingAs($this->teacher)->tool(SaveMeetingTool::class, ['classroom' => 'jovens', 'held_on' => '2026-10-11'])
            ->assertHasErrors(['Já existe um encontro desta classe nesta data.']);
    }

    public function test_finish_and_cancel(): void
    {
        $today = Lesson::factory()->for($this->classroom)->published()->on('2026-09-27')->create(['title' => 'Hoje']);
        Lesson::factory()->for($this->classroom)->published()->on('2026-10-04')->create(['title' => 'Próxima']);

        EbdServer::actingAs($this->teacher)->tool(FinishMeetingTool::class, ['classroom' => 'jovens', 'meeting' => '2026-09-27', 'continues' => true, 'notes' => 'Paramos no ponto 2'])
            ->assertOk()
            ->assertSee(['"status":"held"', 'Paramos no ponto 2']);

        // A lição continua: o próximo encontro passa a ser dela.
        $this->assertSame($today->id, ClassMeeting::query()->whereDate('held_on', '2026-10-04')->value('lesson_id'));

        EbdServer::actingAs($this->teacher)->tool(CancelMeetingTool::class, ['classroom' => 'jovens', 'meeting' => '2026-10-04', 'reason' => 'Congresso'])
            ->assertOk()
            ->assertSee(['"status":"cancelled"', 'Congresso']);

        $this->assertSame($today->id, ClassMeeting::query()->whereDate('held_on', '2026-10-11')->value('lesson_id'));
    }

    public function test_current_lesson_tools_choose_and_conclude(): void
    {
        $series = Series::factory()->for($this->classroom)->create();
        $first = Lesson::factory()->forSeries($series)->number(1)->published()->create(['title' => 'Primeira']);
        $second = Lesson::factory()->forSeries($series)->number(2)->published()->create(['title' => 'Segunda']);

        EbdServer::actingAs($this->teacher)->tool(SetCurrentLessonTool::class, ['classroom' => 'jovens', 'lesson_id' => $first->id])
            ->assertOk()
            ->assertSee('Primeira');
        $this->assertSame($first->id, $this->classroom->refresh()->current_lesson_id);

        EbdServer::actingAs($this->teacher)->tool(AdvanceLessonTool::class, ['classroom' => 'jovens', 'lesson_id' => $first->id])
            ->assertOk()
            ->assertSee('Segunda');
        $this->assertSame($second->id, $this->classroom->refresh()->current_lesson_id);

        // Hoje é domingo: a chamada cria o encontro do dia, com a lição atual.
        $student = User::factory()->studentOf($this->classroom)->create(['name' => 'Ana']);
        EbdServer::actingAs($this->teacher)->tool(RecordAttendanceTool::class, ['classroom' => 'jovens', 'meeting' => '2026-09-27', 'mode' => 'add', 'present' => ['Ana']])
            ->assertOk();
        $meeting = ClassMeeting::query()->whereDate('held_on', '2026-09-27')->sole();
        $this->assertSame($second->id, $meeting->lesson_id);
        $this->assertSame([$student->id], $meeting->attendances()->pluck('user_id')->all());
    }

    public function test_teacher_of_another_classroom_cannot_touch_the_agenda(): void
    {
        $other = Classroom::factory()->create(['slug' => 'adultos']);
        ClassMeeting::factory()->for($other)->on('2026-09-27')->create();

        EbdServer::actingAs($this->teacher)->tool(CancelMeetingTool::class, ['classroom' => 'adultos', 'meeting' => '2026-09-27'])
            ->assertHasErrors(['Classe "adultos" não encontrada']);

        $foreign = ClassMeeting::query()->sole();
        EbdServer::actingAs($this->teacher)->tool(CancelMeetingTool::class, ['classroom' => 'jovens', 'meeting' => (string) $foreign->id])
            ->assertHasErrors(['não encontrado']);

        $this->assertSame(MeetingStatus::Planned, $foreign->fresh()->status);
    }
}
