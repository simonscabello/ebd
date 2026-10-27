<?php

namespace Tests\Feature;

use App\Actions\Lessons\AnswerStudyQuestion;
use App\Enums\Weekday;
use App\Mcp\Servers\EbdServer;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Avisos que falavam "domingo" e "EBD" seguem o dia e o programa da classe.
 * Na EBD de domingo, tudo continua escrito como antes.
 */
class MeetingDayTextsTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $ebd;

    private Classroom $discipulado;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Quinta, 08/10/2026: domingo 04/10 e quarta 07/10 já passaram.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 8)->setTime(10, 0));

        $this->ebd = Classroom::factory()->create(['slug' => 'jovens']);
        $program = Program::factory()->create(['church_id' => $this->ebd->church_id, 'name' => 'Discipulado', 'short_name' => 'Discipulado']);
        $this->discipulado = Classroom::factory()->create(['slug' => 'grupo', 'program_id' => $program->id, 'meeting_weekday' => Weekday::Wednesday]);
    }

    /**
     * @return list<string>
     */
    private function toastsFor(Classroom $classroom, string $date): array
    {
        $teacher = User::factory()->teacherOf($classroom)->create();
        $toasts = [];

        foreach (['cancelar', 'restaurar', 'realizado'] as $action) {
            $meeting = ClassMeeting::factory()->for($classroom)->on($date)->create();
            if ($action === 'restaurar') {
                $meeting->update(['status' => 'cancelled']);
            }

            $this->actingAs($teacher)->post("/admin/encontros/{$meeting->id}/{$action}")
                ->assertSessionHas(SessionKey::FLASH_DATA, function (array $flash) use (&$toasts) {
                    $toasts[] = $flash['toast']['message'];

                    return true;
                });
            $meeting->delete();
        }

        return $toasts;
    }

    public function test_sunday_ebd_class_reads_as_before(): void
    {
        $this->assertSame([
            'Domingo marcado como "sem EBD".',
            'O domingo voltou para a agenda.',
            'Domingo marcado como realizado.',
        ], $this->toastsFor($this->ebd, '2026-10-04'));
    }

    public function test_wednesday_class_follows_its_day_and_program(): void
    {
        $this->assertSame([
            'Encontro marcado como "sem Discipulado".',
            'O encontro voltou para a agenda.',
            'Encontro marcado como realizado.',
        ], $this->toastsFor($this->discipulado, '2026-10-07'));
    }

    public function test_study_helper_points_to_the_meeting_day(): void
    {
        $ebdLesson = Lesson::factory()->for($this->ebd)->create();
        $grupoLesson = Lesson::factory()->for($this->discipulado)->create();

        $this->assertStringContainsString('levar a dúvida para o professor no domingo.', AnswerStudyQuestion::instructionsFor($ebdLesson));
        $this->assertStringContainsString('levar a dúvida para o professor na quarta-feira.', AnswerStudyQuestion::instructionsFor($grupoLesson));
    }

    public function test_mcp_instructions_do_not_assume_sunday(): void
    {
        $instructions = (string) (new ReflectionProperty(EbdServer::class, 'instructions'))->getDefaultValue();

        $this->assertStringNotContainsString('aos domingos', $instructions);
        $this->assertStringContainsString('Cada classe se reúne num dia da semana', $instructions);
    }
}
