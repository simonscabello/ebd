<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Classroom;
use App\Models\User;
use Database\Seeders\ClassroomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A igreja que agrupa as classes e os seus administradores.
 */
class ChurchModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_classrooms_from_the_factory_share_one_church(): void
    {
        $jovens = Classroom::factory()->create();
        $adultos = Classroom::factory()->create();

        $this->assertSame($jovens->church_id, $adultos->church_id);
        $this->assertSame(1, Church::query()->count());
        $this->assertTrue($jovens->church->is(Church::primary()));
    }

    public function test_church_admin_check(): void
    {
        $church = Church::factory()->create();
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $church->admins()->attach($admin);

        $this->assertTrue($admin->isChurchAdminOf($church));
        $this->assertFalse($other->isChurchAdminOf($church));
        $this->assertTrue($admin->administeredChurches()->sole()->is($church));
    }

    public function test_primary_or_create_uses_the_configured_name_only_when_empty(): void
    {
        config(['ebd.church_name' => 'Igreja Teste', 'ebd.timezone' => 'America/Sao_Paulo']);

        $this->assertNull(Church::primary());

        $church = Church::primaryOrCreate();
        Church::primaryOrCreate();

        $this->assertSame(['Igreja Teste', 'igreja-teste', 'America/Sao_Paulo'], [$church->name, $church->slug, $church->timezone]);
        $this->assertSame(1, Church::query()->count());
    }

    public function test_seeder_puts_seeded_classrooms_in_a_church(): void
    {
        $this->seed(ClassroomSeeder::class);

        $churches = Classroom::query()->whereIn('slug', ['jovens', 'adultos'])->pluck('church_id')->unique();

        $this->assertCount(1, $churches);
        $this->assertNotNull($churches->first());
    }
}
