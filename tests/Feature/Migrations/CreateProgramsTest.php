<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Programas de ensino: as turmas de cada igreja entram no programa
 * "Escola Bíblica Dominical" dela, sem nada mudar para quem usa.
 */
class CreateProgramsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_22_000100_create_programs_table.php';

    private function church(string $slug): int
    {
        return DB::table('churches')->insertGetId(['name' => $slug, 'slug' => $slug, 'timezone' => 'America/Sao_Paulo', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function classroom(int $church, string $slug): int
    {
        return DB::table('classrooms')->insertGetId(['church_id' => $church, 'name' => $slug, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_each_church_gets_an_ebd_program_with_its_classrooms(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();

        $a = $this->church('a');
        $b = $this->church('b');
        $jovens = $this->classroom($a, 'jovens');
        $adultos = $this->classroom($a, 'adultos');
        $outra = $this->classroom($b, 'outra');

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $programs = DB::table('programs')->orderBy('church_id')->get();
        $this->assertCount(2, $programs);
        $this->assertSame(['Escola Bíblica Dominical', 'EBD', 'ebd'], [$programs[0]->name, $programs[0]->short_name, $programs[0]->slug]);
        $this->assertSame($a, $programs[0]->church_id);
        $this->assertSame([$programs[0]->id, $programs[0]->id], DB::table('classrooms')->whereIn('id', [$jovens, $adultos])->pluck('program_id')->all());
        $this->assertSame($programs[1]->id, DB::table('classrooms')->where('id', $outra)->value('program_id'));

        $this->expectException(QueryException::class);
        DB::table('classrooms')->insert(['church_id' => $a, 'name' => 'Sem programa', 'slug' => 'sem-programa', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_an_empty_database_gets_no_program_and_rollback_restores(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();
        $this->assertSame(0, DB::table('programs')->count());

        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('programs'));
        $this->assertFalse(Schema::hasTable('program_user'));
        $this->assertFalse(Schema::hasColumn('classrooms', 'program_id'));
    }
}
