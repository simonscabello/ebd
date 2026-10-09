<?php

namespace Tests\Feature\Admin;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * "Onde paramos": salvo sozinho enquanto o professor digita, sem toast.
 */
class MeetingNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_saves_where_the_class_stopped_without_a_toast(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $meeting = ClassMeeting::factory()->for($classroom)->on('2026-10-04')->create();

        $this->actingAs($teacher)
            ->from('/admin')
            ->put("/admin/encontros/{$meeting->id}/anotacao", ['notes' => '  Paramos no tópico 3. '])
            ->assertRedirect('/admin')
            ->assertSessionMissing(SessionKey::FLASH_DATA);

        $this->assertSame('Paramos no tópico 3.', $meeting->refresh()->notes);
    }

    public function test_empty_text_clears_the_note(): void
    {
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->teacherOf($classroom)->create();
        $meeting = ClassMeeting::factory()->for($classroom)->on('2026-10-04')->create(['notes' => 'Antiga']);

        $this->actingAs($teacher)->put("/admin/encontros/{$meeting->id}/anotacao", ['notes' => '']);

        $this->assertNull($meeting->refresh()->notes);
    }

    public function test_teacher_of_another_class_cannot_write_the_note(): void
    {
        $meeting = ClassMeeting::factory()->for(Classroom::factory())->on('2026-10-04')->create();
        $other = User::factory()->teacherOf(Classroom::factory()->create())->create();

        $this->actingAs($other)
            ->put("/admin/encontros/{$meeting->id}/anotacao", ['notes' => 'Invasão'])
            ->assertForbidden();

        $this->assertNull($meeting->refresh()->notes);
    }
}
