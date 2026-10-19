<?php

namespace Tests\Feature\Migrations;

use App\Models\Church;
use App\Models\User;
use App\Queries\OwnerChurchStats;
use App\Support\OfficialLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A biblioteca oficial é uma organização própria, administrada pelo dono,
 * com a turma "Estudos oficiais". Ela não muda a igreja do dono nem aparece
 * no painel das igrejas.
 */
class OfficialLibraryMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_24_000100_create_official_library.php';

    public function test_the_migration_creates_the_library_with_the_owner_as_admin(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();

        $first = DB::table('churches')->insertGetId(['name' => 'Igreja do Simon', 'slug' => 'igreja-do-simon', 'timezone' => 'America/Sao_Paulo', 'created_at' => now(), 'updated_at' => now()]);
        $owner = DB::table('users')->insertGetId(['name' => 'Simon', 'email' => 'simon@example.test', 'is_admin' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->insert(['name' => 'Outro', 'email' => 'outro@example.test', 'is_admin' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('church_user')->insert(['church_id' => $first, 'user_id' => $owner, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $library = Church::query()->where('is_official', true)->sole();
        $this->assertSame('Biblioteca Didaché', $library->name);
        $this->assertFalse($library->shows_bible_text);
        $this->assertFalse($library->helper_enabled);
        $this->assertSame([$owner], $library->admins()->pluck('users.id')->all());
        $this->assertSame(['Estudos oficiais'], $library->classrooms()->pluck('name')->all());
        $this->assertSame('Acervo', $library->programs()->value('name'));

        $simon = User::query()->find($owner);
        $this->assertSame($first, $simon->currentChurch()->id);
        $this->assertSame($first, Church::primary()->id);
        $this->assertSame([$first], array_column(app(OwnerChurchStats::class)->all(), 'id'));
    }

    public function test_an_empty_database_gets_the_library_on_demand(): void
    {
        $this->assertSame(0, Church::query()->count());

        $owner = User::factory()->create(['is_admin' => true]);
        $classroom = OfficialLibrary::classroom();

        $this->assertTrue($classroom->church->is_official);
        $this->assertSame('estudos-oficiais', $classroom->slug);
        $this->assertTrue($owner->isChurchAdminOf($classroom->church));
        $this->assertSame($classroom->id, OfficialLibrary::classroom()->id);
        // A biblioteca nunca é a igreja principal.
        $this->assertNull(Church::primary());
    }
}
