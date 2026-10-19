<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * IA, áudio e texto bíblico passam a ser por igreja. A igreja que já existe
 * (a primeira) continua com tudo ligado, nos limites de hoje; as outras
 * ficam desligadas até o dono liberar.
 */
class ChurchSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const SETTINGS = 'database/migrations/2026_10_23_000100_add_settings_to_churches.php';

    private const USAGES = 'database/migrations/2026_10_23_000200_create_ai_usages_table.php';

    private function church(string $slug): int
    {
        return DB::table('churches')->insertGetId(['name' => $slug, 'slug' => $slug, 'timezone' => 'America/Sao_Paulo', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function lesson(int $church, string $slug, ?string $audioAt = null): int
    {
        $program = DB::table('programs')->insertGetId(['church_id' => $church, 'name' => 'EBD '.$slug, 'short_name' => 'EBD', 'slug' => 'ebd-'.$slug, 'created_at' => now(), 'updated_at' => now()]);
        $classroom = DB::table('classrooms')->insertGetId(['church_id' => $church, 'program_id' => $program, 'name' => $slug, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('lessons')->insertGetId(['classroom_id' => $classroom, 'title' => $slug, 'slug' => $slug, 'audio_generated_at' => $audioAt, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_first_church_keeps_everything_on_and_others_start_off(): void
    {
        config(['ebd.helper.daily_limit' => 7]);
        $this->artisan('migrate:rollback', ['--path' => self::USAGES])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => self::SETTINGS])->assertSuccessful();

        $first = $this->church('primeira');
        $other = $this->church('outra');
        $lessonA = $this->lesson($first, 'licao-a', '2026-10-01 10:00:00');
        $lessonB = $this->lesson($other, 'licao-b');
        $user = DB::table('users')->insertGetId(['name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('study_questions')->insert([
            ['user_id' => $user, 'lesson_id' => $lessonA, 'question' => 'q', 'answer' => 'a', 'created_at' => '2026-10-02 12:00:00', 'updated_at' => now()],
            ['user_id' => $user, 'lesson_id' => $lessonB, 'question' => 'q', 'answer' => 'a', 'created_at' => '2026-10-03 12:00:00', 'updated_at' => now()],
        ]);

        $this->artisan('migrate', ['--path' => self::SETTINGS])->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::USAGES])->assertSuccessful();

        $a = DB::table('churches')->find($first);
        $this->assertTrue((bool) $a->helper_enabled);
        $this->assertTrue((bool) $a->audio_enabled);
        $this->assertTrue((bool) $a->shows_bible_text);
        $this->assertSame(7, (int) $a->helper_daily_limit);
        $this->assertSame(30, (int) $a->audio_monthly_limit);

        $b = DB::table('churches')->find($other);
        $this->assertFalse((bool) $b->helper_enabled);
        $this->assertFalse((bool) $b->audio_enabled);
        $this->assertFalse((bool) $b->shows_bible_text);

        $usages = DB::table('ai_usages')->orderBy('created_at')->get();
        $this->assertSame(
            [['audio', $first, $lessonA, '2026-10-01 10:00:00'], ['question', $first, $lessonA, '2026-10-02 12:00:00'], ['question', $other, $lessonB, '2026-10-03 12:00:00']],
            $usages->map(fn ($u) => [$u->kind, $u->church_id, $u->lesson_id, (string) $u->created_at])->all(),
        );
    }

    public function test_a_new_church_starts_with_everything_off(): void
    {
        $id = $this->church('nova');
        $church = DB::table('churches')->find($id);

        $this->assertFalse((bool) $church->helper_enabled);
        $this->assertFalse((bool) $church->audio_enabled);
        $this->assertFalse((bool) $church->shows_bible_text);
        $this->assertSame(10, (int) $church->helper_daily_limit);
        $this->assertSame(4, (int) $church->audio_monthly_limit);
    }
}
