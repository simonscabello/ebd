<?php

namespace Tests\Feature;

use App\Actions\Lessons\AnswerStudyQuestion;
use App\Actions\Notifications\SendLessonReminder;
use App\Http\Resources\ClassMeetingResource;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePushSender;
use Tests\TestCase;

/**
 * Os textos que falavam "EBD" usam o nome curto do programa da turma. Na
 * Escola Bíblica Dominical continua "EBD".
 */
class ProgramTextsTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ebd.timezone' => 'America/Sao_Paulo']);
        // Sábado, 03/10/2026, 8h.
        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 3)->setTime(8, 0));

        $ebd = Classroom::factory()->create(['slug' => 'jovens']);
        $discipulado = Program::factory()->create(['church_id' => $ebd->church_id, 'name' => 'Discipulado', 'short_name' => 'Discipulado']);
        $this->grupo = Classroom::factory()->create(['slug' => 'grupo', 'program_id' => $discipulado->id]);
    }

    public function test_eve_reminder_and_home_use_the_program_name(): void
    {
        $push = new FakePushSender;
        $this->app->instance(PushSender::class, $push);
        $student = User::factory()->studentOf($this->grupo)->create();
        PushSubscription::factory()->for($student)->create();
        Lesson::factory()->for($this->grupo)->published()->on('2026-10-04')->create();

        app(SendLessonReminder::class)->handle();
        $this->assertSame('Amanhã tem Discipulado!', $push->sentTo($student)->first()->title);

        $this->actingAs($student)->get('/?classe=grupo')
            ->assertInertia(fn (Assert $page) => $page->where('classroom.program_short_name', 'Discipulado'));
    }

    public function test_cancelled_label_follows_the_program(): void
    {
        $ebdSunday = ClassMeeting::factory()->for(Classroom::query()->where('slug', 'jovens')->sole())->on('2026-10-04')->cancelled()->create();
        $grupoSunday = ClassMeeting::factory()->for($this->grupo)->on('2026-10-04')->cancelled()->create();

        $this->assertSame('Sem EBD', ClassMeetingResource::make($ebdSunday)->resolve()['status_label']);
        $this->assertSame('Sem Discipulado', ClassMeetingResource::make($grupoSunday)->resolve()['status_label']);
    }

    public function test_study_helper_names_the_program_of_the_lesson(): void
    {
        config(['services.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Resposta.']]]],
        ])]);
        $student = User::factory()->studentOf($this->grupo)->create();
        Lesson::factory()->for($this->grupo)->published()->create(['slug' => 'licao-do-grupo']);

        $this->actingAs($student)->postJson('/licoes/licao-do-grupo/duvidas', ['question' => 'O que é graça?'])->assertOk();

        Http::assertSent(function (Request $request) {
            $instructions = (string) $request['instructions'];

            return str_contains($instructions, 'Discipulado') && ! str_contains($instructions, 'Escola Bíblica Dominical');
        });
    }

    public function test_ebd_prompt_keeps_the_original_sentence(): void
    {
        $lesson = Lesson::factory()->for(Classroom::query()->where('slug', 'jovens')->sole())->create();

        $this->assertStringContainsString(
            'Você ajuda alunos da Escola Bíblica Dominical (EBD) de uma igreja evangélica a tirar dúvidas sobre a lição da semana',
            AnswerStudyQuestion::instructionsFor($lesson),
        );
    }
}
