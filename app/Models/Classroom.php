<?php

namespace App\Models;

use App\Enums\ClassroomRole;
use App\Enums\Weekday;
use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Uma classe da EBD (ex.: Jovens, Adultos). "Class" é palavra reservada no PHP.
 *
 * @property int $id
 * @property int $church_id
 * @property int $program_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property int $position
 * @property Weekday $meeting_weekday
 * @property int|null $current_series_id
 * @property int|null $current_lesson_id
 * @property Carbon|null $current_lesson_started_at
 * @property-read Series|null $currentSeries
 * @property-read Lesson|null $currentLesson
 */
#[Fillable(['name', 'slug', 'description', 'is_active', 'position', 'meeting_weekday'])]
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    /** O programa entra nos textos ("Amanhã tem EBD!") e a igreja decide IA e texto bíblico: vêm sempre junto. */
    protected $with = ['program', 'church'];

    /** Turma nova se reúne no domingo, até alguém escolher outro dia. */
    protected $attributes = ['meeting_weekday' => 7];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
            'meeting_weekday' => Weekday::class,
            'current_lesson_started_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Church, $this>
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsToMany<User, $this, ClassroomMember>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ClassroomMember::class)
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this, ClassroomMember>
     */
    public function teachers(): BelongsToMany
    {
        return $this->members()->wherePivot('role', ClassroomRole::Teacher->value);
    }

    /**
     * @return HasMany<Series, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Série que a classe estuda agora. Pode existir sem lição atual (série
     * nova, ainda sem lição pronta). Muda pelas ações em App\Actions\Teaching.
     *
     * @return BelongsTo<Series, $this>
     */
    public function currentSeries(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'current_series_id');
    }

    /**
     * Lição que a classe estuda agora: fica quantos encontros precisar e só
     * sai quando o professor conclui (AdvanceLesson).
     *
     * @return BelongsTo<Lesson, $this>
     */
    public function currentLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'current_lesson_id');
    }

    /**
     * @return HasMany<ClassMeeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(ClassMeeting::class);
    }

    /**
     * @return BelongsToMany<User, $this, ClassroomMember>
     */
    public function students(): BelongsToMany
    {
        return $this->members()->wherePivot('role', ClassroomRole::Student->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    /**
     * A turma da biblioteca oficial guarda estudos, não se reúne: não tem
     * agenda de encontros.
     */
    public function holdsMeetings(): bool
    {
        return ! $this->church->is_official;
    }

    /**
     * Recusa, com mensagem para a tela, criar ou mexer em encontro de uma
     * turma que não se reúne (a biblioteca oficial).
     *
     * @throws ValidationException
     */
    public function ensureHoldsMeetings(string $field = 'held_on'): void
    {
        if (! $this->holdsMeetings()) {
            throw ValidationException::withMessages([
                $field => 'A biblioteca oficial guarda estudos e não tem agenda de encontros. Copie a série para uma classe e planeje lá.',
            ]);
        }
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
