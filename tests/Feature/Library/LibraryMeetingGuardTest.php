<?php

namespace Tests\Feature\Library;

use App\Actions\Lessons\CreateLesson;
use App\Actions\Meetings\ContinueLessonNextMeeting;
use App\Actions\Meetings\PlanMeetings;
use App\Actions\Meetings\SaveMeeting;
use App\Mcp\Servers\EbdServer;
use App\Mcp\Tools\ListClassrooms;
use App\Mcp\Tools\SaveMeetingTool;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Support\OfficialLibrary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Nenhum caminho cria encontro na turma da biblioteca oficial: lição com
 * data, "planejar", salvar encontro, "continua no próximo" e as ferramentas
 * do MCP. Turmas de verdade seguem normais.
 */
class LibraryMeetingGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Classroom $library;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Terça-feira, 20/10/2026.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 20)->setTime(10, 0));

        Classroom::factory()->create(); // igreja do dono
        $this->owner = User::factory()->admin()->create();
        $this->library = OfficialLibrary::classroom();
    }

    private function assertNoLibraryMeetings(): void
    {
        $this->assertSame(0, ClassMeeting::query()->where('classroom_id', $this->library->id)->count());
    }

    public function test_holds_meetings_is_false_only_for_the_library(): void
    {
        $this->assertFalse($this->library->holdsMeetings());
        $this->assertTrue(Classroom::factory()->create()->holdsMeetings());
    }

    public function test_a_date_on_a_new_library_lesson_creates_no_meeting(): void
    {
        app(CreateLesson::class)->handle($this->owner, $this->library, ['title' => 'Oficial', 'visibility' => 'public', 'meeting_on' => '2026-10-25']);

        $this->assertSame(1, Lesson::query()->where('classroom_id', $this->library->id)->count());
        $this->assertNoLibraryMeetings();
    }

    public function test_create_lesson_without_a_date_still_works(): void
    {
        $lesson = app(CreateLesson::class)->handle($this->owner, $this->library, ['title' => 'Oficial', 'visibility' => 'public']);

        $this->assertSame($this->library->id, $lesson->classroom_id);
    }

    public function test_plan_meetings_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(PlanMeetings::class)->handle($this->library, CarbonImmutable::parse('2026-10-20'), CarbonImmutable::parse('2026-12-31'), null);
        } finally {
            $this->assertNoLibraryMeetings();
        }
    }

    public function test_save_meeting_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(SaveMeeting::class)->handle($this->library, ['held_on' => '2026-10-25']);
        } finally {
            $this->assertNoLibraryMeetings();
        }
    }

    public function test_continue_next_meeting_is_refused(): void
    {
        // Um encontro antigo, de antes da migração que os apagou.
        $lesson = Lesson::factory()->for($this->library)->create();
        $meeting = ClassMeeting::factory()->for($this->library)->on('2026-10-18')->create(['lesson_id' => $lesson->id]);

        $this->expectException(ValidationException::class);

        try {
            app(ContinueLessonNextMeeting::class)->handle($meeting);
        } finally {
            $this->assertSame(1, ClassMeeting::query()->where('classroom_id', $this->library->id)->count());
        }
    }

    public function test_mcp_meeting_tools_are_refused_for_the_library(): void
    {
        $slug = $this->library->slug;

        EbdServer::actingAs($this->owner)->tool(SaveMeetingTool::class, ['classroom' => $slug, 'held_on' => '2026-10-25'])
            ->assertHasErrors(['não tem agenda de encontros']);

        $this->assertNoLibraryMeetings();
    }

    public function test_mcp_lists_the_library_without_meetings(): void
    {
        EbdServer::actingAs($this->owner)->tool(ListClassrooms::class)
            ->assertOk()
            ->assertSee(['Biblioteca oficial', '"holds_meetings":false', '"holds_meetings":true']);
    }

    public function test_real_classrooms_still_plan_meetings(): void
    {
        $jovens = Classroom::factory()->create();

        $result = app(PlanMeetings::class)->handle($jovens, CarbonImmutable::parse('2026-10-20'), CarbonImmutable::parse('2026-11-08'), null);

        $this->assertSame(3, $result['created']);
    }
}
