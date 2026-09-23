<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use Carbon\CarbonInterface;
use Database\Factories\ClassMeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Um encontro (domingo) de uma classe. Aponta para a lição estudada naquele dia;
 * a mesma lição pode aparecer em vários encontros seguidos.
 *
 * @property int $id
 * @property int $classroom_id
 * @property int|null $lesson_id
 * @property Carbon $held_on
 * @property MeetingStatus $status
 * @property string|null $title
 * @property string|null $notes
 * @property Carbon|null $attendance_taken_at
 * @property int $visitors_count
 * @property-read Classroom $classroom
 * @property-read Lesson|null $lesson
 */
#[Fillable(['classroom_id', 'held_on', 'lesson_id', 'status', 'title', 'notes', 'visitors_count'])]
class ClassMeeting extends Model
{
    /** @use HasFactory<ClassMeetingFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planned',
        'visitors_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'status' => MeetingStatus::class,
            'attendance_taken_at' => 'datetime',
            'visitors_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Lição excluída (soft delete) volta como null: o encontro fica como histórico.
     *
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === MeetingStatus::Cancelled;
    }

    public function hasAttendance(): bool
    {
        return $this->attendance_taken_at !== null;
    }

    /**
     * Encontros que acontecem (ou aconteceram): tudo menos "sem EBD".
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), '!=', MeetingStatus::Cancelled);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function fromDate(Builder $query, CarbonInterface $date): void
    {
        $query->whereDate($query->qualifyColumn('held_on'), '>=', $date->toDateString());
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function chronological(Builder $query): void
    {
        $query->orderBy($query->qualifyColumn('held_on'));
    }
}
