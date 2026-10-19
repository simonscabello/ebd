<?php

namespace Tests\Feature\Library;

use App\Actions\Library\CopySeriesToClassroom;
use App\Actions\Library\PublishSeriesVersion;
use App\Http\Resources\SeriesResource;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use App\Support\OfficialLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Casos de borda da biblioteca oficial apontados na revisão.
 */
class LibraryHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_long_title_still_copies_when_the_original_slug_is_taken(): void
    {
        Classroom::factory()->create();
        $owner = User::factory()->admin()->create();
        $series = Series::factory()->for(OfficialLibrary::classroom())->create();
        $title = trim(Str::repeat('Palavra ', 21));
        Lesson::factory()->for($series->classroom)->forSeries($series)->published()->create(['title' => $title, 'slug' => Str::of(Str::slug($title))->limit(150, '')->trim('-')->value()]);
        $version = app(PublishSeriesVersion::class)->handle($owner, $series, null);

        $classroom = Classroom::factory()->for(Church::factory())->create(['slug' => Str::repeat('turma-', 11).'b']);
        $teacher = User::factory()->teacherOf($classroom)->create();

        $copy = app(CopySeriesToClassroom::class)->handle($teacher, $version, $classroom);
        app(CopySeriesToClassroom::class)->handle($teacher, $version, $classroom);

        $slugs = Lesson::query()->where('classroom_id', $classroom->id)->pluck('slug');
        $this->assertCount(2, $slugs->unique());
        $this->assertTrue($slugs->every(fn ($slug) => strlen($slug) <= 200));
        $this->assertSame($classroom->id, $copy->classroom_id);
    }

    public function test_promoting_an_owner_later_also_makes_them_admin_of_the_library(): void
    {
        Classroom::factory()->create();
        $library = OfficialLibrary::church();
        $user = User::factory()->create(['email' => 'novo-dono@example.test']);
        config(['ebd.admin_emails' => ['novo-dono@example.test']]);

        $this->artisan('ebd:promote-admins')->assertSuccessful();

        $this->assertTrue($user->fresh()->isChurchAdminOf($library));
    }

    public function test_rolling_back_the_library_with_content_stops_with_a_message(): void
    {
        Classroom::factory()->create();
        Series::factory()->for(OfficialLibrary::classroom())->create();

        $this->expectExceptionMessage('A biblioteca oficial já tem estudos');

        (require base_path('database/migrations/2026_10_24_000100_create_official_library.php'))->down();
    }

    public function test_the_owner_can_still_join_classrooms_of_his_own_church(): void
    {
        $classroom = Classroom::factory()->create();
        $owner = User::factory()->admin()->create();
        OfficialLibrary::classroom();

        $this->assertTrue($owner->fresh()->isFreeFor($classroom->church_id));
        $this->assertFalse($owner->fresh()->isFreeFor(Church::factory()->create()->id));
    }

    public function test_series_lists_outside_the_series_page_do_not_query_versions(): void
    {
        Classroom::factory()->create();
        $series = Series::factory()->for(Classroom::factory()->create())->create();
        $series->forceFill(['source_series_id' => Series::factory()->for(OfficialLibrary::classroom())->create()->id, 'source_version' => 1])->save();

        $payload = SeriesResource::make($series->fresh())->resolve(request());

        $this->assertArrayNotHasKey('update_available', $payload);
    }

    public function test_a_new_classroom_goes_to_the_owner_church_even_if_the_library_is_older(): void
    {
        $library = OfficialLibrary::church();
        $owner = User::factory()->admin()->create();
        $library->admins()->syncWithoutDetaching([$owner->id]);
        $church = Church::primaryOrCreate();
        $church->admins()->syncWithoutDetaching([$owner->id]);

        $this->actingAs($owner)->post(route('admin.classrooms.store'), ['name' => 'Adultos', 'meeting_weekday' => 7])->assertSessionHasNoErrors();

        $this->assertSame($church->id, Classroom::query()->where('name', 'Adultos')->value('church_id'));
    }
}
