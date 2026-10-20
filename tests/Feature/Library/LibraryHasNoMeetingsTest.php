<?php

namespace Tests\Feature\Library;

use App\Actions\Meetings\FillSundays;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\User;
use App\Support\OfficialLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A biblioteca oficial não se reúne: o preenchimento de domingos a ignora,
 * e a migração apaga os encontros que já tinham sido criados para ela.
 */
class LibraryHasNoMeetingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fill_sundays_skips_the_library_but_not_real_classrooms(): void
    {
        $jovens = Classroom::factory()->create();
        User::factory()->admin()->create();
        $library = OfficialLibrary::classroom();

        $this->assertSame(0, app(FillSundays::class)->handle($library));
        $this->assertSame(0, ClassMeeting::query()->where('classroom_id', $library->id)->count());
        $this->assertGreaterThan(0, app(FillSundays::class)->handle($jovens));
    }

    public function test_the_migration_removes_existing_library_meetings_only(): void
    {
        $jovens = Classroom::factory()->create();
        User::factory()->admin()->create();
        $library = OfficialLibrary::classroom();
        ClassMeeting::factory()->for($library)->on('2026-10-25')->create();
        ClassMeeting::factory()->for($jovens)->on('2026-10-25')->create();

        (require base_path('database/migrations/2026_10_27_000100_remove_official_library_meetings.php'))->up();

        $this->assertSame(0, ClassMeeting::query()->where('classroom_id', $library->id)->count());
        $this->assertSame(1, ClassMeeting::query()->where('classroom_id', $jovens->id)->count());
    }
}
