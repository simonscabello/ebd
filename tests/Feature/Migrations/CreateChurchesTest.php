<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A igreja vira entidade: as classes que já existem (a igreja do Simon, em
 * produção) entram nela sem que nada mude para quem usa.
 */
class CreateChurchesTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_20_000100_create_churches_table.php';

    private function rollback(): void
    {
        // O uso da IA, os programas e a igreja atual (migrations posteriores) dependem da igreja: saem antes.
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_10_25_000100_add_current_church_to_users.php'])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_10_23_000200_create_ai_usages_table.php'])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_10_23_000100_add_settings_to_churches.php'])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_10_22_000100_create_programs_table.php'])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
    }

    private function migrate(): void
    {
        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();
    }

    private function classroom(string $slug): int
    {
        return DB::table('classrooms')->insertGetId(['name' => ucfirst($slug), 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function user(string $email, bool $admin = false): int
    {
        return DB::table('users')->insertGetId(['name' => $email, 'email' => $email, 'is_admin' => $admin, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_existing_classrooms_and_admins_move_into_the_current_church(): void
    {
        config(['ebd.church_name' => 'Segunda Igreja Batista em Barcelona', 'ebd.timezone' => 'America/Sao_Paulo']);
        $this->rollback();

        $jovens = $this->classroom('jovens');
        $adultos = $this->classroom('adultos');
        $admin = $this->user('simon@example.com', admin: true);
        $this->user('aluno@example.com');

        $this->migrate();

        $this->assertSame(1, DB::table('churches')->count());
        $church = DB::table('churches')->first();
        $this->assertSame(
            ['Segunda Igreja Batista em Barcelona', 'segunda-igreja-batista-em-barcelona', 'America/Sao_Paulo'],
            [$church->name, $church->slug, $church->timezone],
        );
        $this->assertSame([$church->id, $church->id], DB::table('classrooms')->whereIn('id', [$jovens, $adultos])->pluck('church_id')->all());
        $this->assertSame([$admin], DB::table('church_user')->where('church_id', $church->id)->pluck('user_id')->all());

        $this->expectException(QueryException::class);
        DB::table('classrooms')->insert(['name' => 'Sem igreja', 'slug' => 'sem-igreja', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_an_empty_database_gets_no_church(): void
    {
        $this->rollback();
        $this->migrate();

        $this->assertSame(0, DB::table('churches')->count());
        $this->assertTrue(Schema::hasColumn('classrooms', 'church_id'));
    }

    public function test_a_name_without_letters_gets_a_fallback_slug(): void
    {
        config(['ebd.church_name' => '***']);
        $this->rollback();
        $this->classroom('jovens');

        $this->migrate();

        $this->assertSame('igreja', DB::table('churches')->value('slug'));
    }

    public function test_rollback_after_backfill_restores_the_old_schema(): void
    {
        $this->rollback();
        $this->classroom('jovens');
        $this->migrate();

        $this->rollback();

        $this->assertFalse(Schema::hasTable('churches'));
        $this->assertFalse(Schema::hasTable('church_user'));
        $this->assertFalse(Schema::hasColumn('classrooms', 'church_id'));
        $this->assertSame(1, DB::table('classrooms')->count());
    }
}
