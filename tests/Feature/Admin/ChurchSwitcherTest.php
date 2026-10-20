<?php

namespace Tests\Feature\Admin;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\User;
use App\Support\OfficialLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Quem administra mais de uma igreja escolhe a atual na gestão; o cabeçalho,
 * o painel, as listas e a criação de classes seguem a escolha. A biblioteca
 * oficial nunca é uma opção.
 */
class ChurchSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private Church $first;

    private Church $second;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->first = Church::factory()->owner()->create(['name' => 'Igreja Primeira']);
        $this->second = Church::factory()->create(['name' => 'Igreja Segunda']);
        Classroom::factory()->for($this->first)->create(['name' => 'Jovens da Primeira']);
        Classroom::factory()->for($this->second)->create(['name' => 'Adultos da Segunda']);

        $this->admin = User::factory()->create();
        $this->first->admins()->attach($this->admin);
        $this->second->admins()->attach($this->admin);
    }

    public function test_the_switcher_only_appears_with_two_or_more_churches(): void
    {
        $single = User::factory()->create();
        $this->first->admins()->attach($single);

        // O dono administra a biblioteca oficial, que nunca conta como opção.
        $owner = User::factory()->admin()->create();
        OfficialLibrary::church()->admins()->syncWithoutDetaching([$owner->id]);

        foreach ([$single, $owner] as $user) {
            $this->actingAs($user)->get('/admin')->assertInertia(fn (Assert $page) => $page
                ->where('church.name', 'Igreja Primeira')
                ->where('church.can_edit', true)
                ->where('church.choices', []));
        }

        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page
            ->where('church.name', 'Igreja Primeira')
            ->where('church.current_id', $this->first->id)
            ->where('church.choices', [
                ['id' => $this->first->id, 'name' => 'Igreja Primeira'],
                ['id' => $this->second->id, 'name' => 'Igreja Segunda'],
            ]));
    }

    public function test_switching_changes_header_dashboard_lists_and_where_classrooms_are_created(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/igreja-atual', ['church_id' => $this->second->id])
            ->assertRedirect('/admin');

        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page
            ->where('church.name', 'Igreja Segunda')
            ->has('classrooms', 1)
            ->where('classrooms.0.name', 'Adultos da Segunda'));

        $this->actingAs($this->admin)->get('/admin/classes')->assertInertia(fn (Assert $page) => $page
            ->has('classrooms', 1)
            ->where('classrooms.0.name', 'Adultos da Segunda'));

        $this->actingAs($this->admin)->post('/admin/classes', ['name' => 'Nova', 'meeting_weekday' => 7]);

        $this->assertSame($this->second->id, Classroom::query()->where('name', 'Nova')->value('church_id'));
    }

    public function test_cannot_pick_a_church_you_do_not_administer_nor_the_official_library(): void
    {
        $foreign = Church::factory()->create();
        $library = OfficialLibrary::church();
        $library->admins()->attach($this->admin);

        foreach ([$foreign, $library] as $church) {
            $this->actingAs($this->admin)
                ->put('/admin/igreja-atual', ['church_id' => $church->id])
                ->assertSessionHasErrors('church_id');
        }

        $this->assertNull($this->admin->fresh()->current_church_id);
    }

    public function test_a_choice_no_longer_administered_falls_back_to_the_first_church(): void
    {
        $this->admin->forceFill(['current_church_id' => $this->second->id])->save();
        $this->second->admins()->detach($this->admin);

        $this->assertSame($this->first->id, $this->admin->fresh()->currentChurch()?->id);
    }

    public function test_church_admins_edit_the_name_and_timezone_of_the_current_church(): void
    {
        $this->actingAs($this->admin)->put('/admin/igreja-atual', ['church_id' => $this->second->id]);

        $this->actingAs($this->admin)->get('/admin/igreja')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/church')
            ->where('settings.name', 'Igreja Segunda')
            ->has('church.choices', 2));

        $this->actingAs($this->admin)
            ->put('/admin/igreja', ['name' => 'Igreja de Lisboa', 'timezone' => 'Europe/Lisbon'])
            ->assertRedirect('/admin/igreja');

        $this->assertSame(['Igreja de Lisboa', 'Europe/Lisbon'], [$this->second->fresh()->name, $this->second->fresh()->timezone]);
        $this->assertSame('Igreja Primeira', $this->first->fresh()->name);

        $this->actingAs($this->admin)
            ->put('/admin/igreja', ['name' => 'X', 'timezone' => 'Lua/Base'])
            ->assertSessionHasErrors('timezone');
    }

    public function test_teachers_cannot_edit_the_church(): void
    {
        $teacher = User::factory()->teacherOf(Classroom::query()->firstOrFail())->create();

        $this->actingAs($teacher)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('church.can_edit', false));
        $this->actingAs($teacher)->get('/admin/igreja')->assertForbidden();
        $this->actingAs($teacher)->put('/admin/igreja', ['name' => 'X', 'timezone' => 'UTC'])->assertForbidden();
    }
}
