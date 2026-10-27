<?php

namespace Tests\Feature;

use App\Enums\Badge;
use App\Enums\Weekday;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\User;
use App\Queries\AttendanceBookQuery;
use App\Queries\Data\Period;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/**
 * Textos que diziam "domingo" seguem o dia da turma. Para quem se reúne no
 * domingo, continuam iguais.
 */
class MeetingDayLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfect_attendance_badge_follows_the_meeting_day(): void
    {
        $this->assertSame('Presença em todos os domingos', Badge::PerfectAttendance->label());
        $this->assertSame('Presença em todos os domingos', Badge::PerfectAttendance->label(Weekday::Sunday));
        $this->assertSame('Esteve em todos os domingos da série.', Badge::PerfectAttendance->description(Weekday::Sunday));
        $this->assertSame('Presença em todos os encontros', Badge::PerfectAttendance->label(Weekday::Wednesday));
        $this->assertSame('Esteve em todos os encontros da série.', Badge::PerfectAttendance->description(Weekday::Wednesday));
    }

    public function test_progress_page_shows_the_badge_with_the_classroom_day(): void
    {
        $wednesday = Classroom::factory()->create(['meeting_weekday' => Weekday::Wednesday]);
        $student = User::factory()->studentOf($wednesday)->create();

        $this->actingAs($student)->get('/meu-progresso')
            ->assertInertia(fn (Assert $page) => $page
                ->where('progress.available_badges.4.badge', Badge::PerfectAttendance->value)
                ->where('progress.available_badges.4.label', 'Presença em todos os encontros'));
    }

    public function test_all_time_period_label_follows_the_meeting_day(): void
    {
        $this->assertSame('Todos os domingos', Period::allTime()->label);
        $this->assertSame('Todos os encontros', Period::allTime(Weekday::Wednesday)->label);

        $sunday = Classroom::factory()->create();
        $wednesday = Classroom::factory()->create(['meeting_weekday' => Weekday::Wednesday]);

        $this->assertSame('Todos os domingos', app(AttendanceBookQuery::class)->for($sunday)->period->label);
        $this->assertSame('Todos os encontros', app(AttendanceBookQuery::class)->for($wednesday)->period->label);
    }

    public function test_opening_programs_twice_at_once_does_not_break_on_the_default_program(): void
    {
        $church = Church::factory()->create();
        $admin = User::factory()->create();
        $church->admins()->attach($admin);

        // Outra abertura gravou o programa padrão entre a leitura e a gravação
        // desta: a gravação daqui esbarra na chave única (church_id, slug).
        Program::creating(function (Program $program) {
            throw new UniqueConstraintViolationException('pgsql', 'insert into "programs"', [], new RuntimeException('duplicate key'));
        });

        $this->actingAs($admin)->get('/admin/programas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/programs/index'));

        // Sem a corrida, a primeira abertura cria o programa padrão uma vez só.
        Program::flushEventListeners();
        $this->actingAs($admin)->get('/admin/programas')->assertOk();
        $this->actingAs($admin)->get('/admin/programas')
            ->assertInertia(fn (Assert $page) => $page->has('programs', 1)->where('programs.0.short_name', 'EBD'));

        $this->assertSame(1, Program::query()->where('church_id', $church->id)->count());
    }
}
