<?php

namespace Tests\Feature\Owner;

use App\Enums\AiUsageKind;
use App\Models\AiUsage;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Painel do dono do sistema: as igrejas, só com números, e as chaves de IA,
 * áudio e texto bíblico de cada uma. Nada do conteúdo de dentro aparece.
 */
class OwnerPanelTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_KEYS = [
        'id', 'name', 'created_at', 'classrooms_count', 'people_count', 'last_activity_at',
        'questions_this_month', 'audios_this_month',
        'helper_enabled', 'helper_daily_limit', 'audio_enabled', 'audio_monthly_limit', 'shows_bible_text',
    ];

    private User $owner;

    private Church $other;

    private User $otherAdmin;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 10, 15)->setTime(12, 0));

        $this->owner = User::factory()->admin()->create();
        $this->other = Church::factory()->create(['name' => 'Igreja Esperança']);
        $this->otherAdmin = User::factory()->create();
        $this->other->admins()->attach($this->otherAdmin);

        $a = Classroom::factory()->for($this->other)->create();
        $b = Classroom::factory()->for($this->other)->create();
        $teacher = User::factory()->teacherOf($a)->create();
        User::factory()->studentOf($a)->create();
        User::factory()->studentOf($b)->create();
        $teacher->classrooms()->attach($b, ['role' => 'student']);
        $this->lesson = Lesson::factory()->for($a)->published()->create(['title' => 'Título secreto']);

        AiUsage::record($this->other, AiUsageKind::Question, $this->lesson);
        AiUsage::record($this->other, AiUsageKind::Audio, $this->lesson);
        AiUsage::query()->create(['church_id' => $this->other->id, 'kind' => AiUsageKind::Audio, 'created_at' => '2026-09-20 12:00:00']);
    }

    public function test_church_admins_and_teachers_cannot_open_it(): void
    {
        $teacher = User::factory()->teacherOf(Classroom::factory()->for($this->other)->create())->create();

        foreach ([$this->otherAdmin, $teacher] as $user) {
            $this->actingAs($user)->get('/dono/igrejas')->assertForbidden();
            $this->actingAs($user)->put("/dono/igrejas/{$this->other->id}", ['helper_enabled' => true])->assertForbidden();
        }
    }

    public function test_the_owner_sees_only_numbers_of_each_church(): void
    {
        $this->actingAs($this->owner)
            ->get('/dono/igrejas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('owner/churches')
                ->has('churches', 2)
                ->where('churches.1.name', 'Igreja Esperança')
                ->where('churches.1.classrooms_count', 2)
                ->where('churches.1.people_count', 3)
                ->where('churches.1.questions_this_month', 1)
                ->where('churches.1.audios_this_month', 1)
                ->where('churches.1.helper_enabled', false)
                ->where('churches.1.last_activity_at', fn ($value) => $value !== null)
                ->where('churches.0.helper_enabled', true)
                ->where('churches', fn ($churches) => collect($churches)->every(
                    fn ($church) => array_keys((array) $church) === self::ITEM_KEYS,
                )));

        $this->actingAs($this->owner)->get('/dono/igrejas')->assertDontSee('Título secreto');
    }

    public function test_the_owner_turns_on_ai_for_a_church(): void
    {
        config(['services.openai.key' => 'sk-test']);
        $student = User::factory()->studentOf($this->lesson->classroom)->create();

        $this->actingAs($this->owner)
            ->put("/dono/igrejas/{$this->other->id}", [
                'helper_enabled' => true,
                'helper_daily_limit' => 3,
                'audio_enabled' => true,
                'audio_monthly_limit' => 5,
                'shows_bible_text' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('churches', ['id' => $this->other->id, 'helper_enabled' => true, 'helper_daily_limit' => 3, 'audio_monthly_limit' => 5]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->owner->id, 'tool' => 'owner.church_settings', 'subject_id' => $this->other->id]);

        $this->actingAs($student)
            ->get("/licoes/{$this->lesson->slug}")
            ->assertInertia(fn (Assert $page) => $page->where('helper.daily_limit', 3));
    }

    public function test_limits_must_be_between_1_and_100(): void
    {
        $this->actingAs($this->owner)
            ->put("/dono/igrejas/{$this->other->id}", [
                'helper_enabled' => true,
                'helper_daily_limit' => 0,
                'audio_enabled' => false,
                'audio_monthly_limit' => 101,
                'shows_bible_text' => false,
            ])
            ->assertSessionHasErrors(['helper_daily_limit', 'audio_monthly_limit']);
    }

    public function test_the_owner_sees_the_link_in_the_menu_and_others_do_not(): void
    {
        $this->actingAs($this->owner)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('auth.user.is_owner', true));
        $this->actingAs($this->otherAdmin)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('auth.user.is_owner', false));
    }
}
