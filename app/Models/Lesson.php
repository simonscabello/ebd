<?php

namespace App\Models;

use App\Enums\LessonStatus;
use App\Enums\LessonVisibility;
use Carbon\CarbonInterface;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $classroom_id
 * @property int|null $series_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property Carbon|null $scheduled_for
 * @property string|null $bible_reference
 * @property string|null $bible_text
 * @property string|null $content
 * @property string|null $teacher_notes
 * @property LessonStatus $status
 * @property LessonVisibility $visibility
 * @property Carbon|null $published_at
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property-read Classroom $classroom
 * @property-read Series|null $series
 */
#[Fillable([
    'title',
    'summary',
    'scheduled_for',
    'bible_reference',
    'bible_text',
    'content',
    'teacher_notes',
    'visibility',
])]
#[Hidden(['search_vector', 'teacher_notes'])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'visibility' => 'public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'status' => LessonStatus::class,
            'visibility' => LessonVisibility::class,
            'published_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Series, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Professores responsáveis pela lição.
     *
     * @return BelongsToMany<User, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lesson_authors');
    }

    /**
     * @return HasMany<LessonMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class)->ordered();
    }

    /**
     * @return HasMany<LessonQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(LessonQuestion::class)->ordered();
    }

    /**
     * @return HasMany<LessonReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(LessonReading::class)->ordered();
    }

    public function isPublic(): bool
    {
        return $this->status->isVisible() && $this->visibility === LessonVisibility::Public;
    }

    /**
     * Lições publicadas ou concluídas.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), LessonStatus::visibleCases());
    }

    /**
     * Lições que a pessoa (ou visitante, quando $user é null) pode ler.
     * Espelha a regra de LessonPolicy::view para consultas em lote.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): void
    {
        $query->visible();

        if ($user?->isAdmin()) {
            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->where($query->qualifyColumn('visibility'), LessonVisibility::Public);

            if ($user !== null && $user->memberClassroomIds() !== []) {
                $query->orWhereIn($query->qualifyColumn('classroom_id'), $user->memberClassroomIds());
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query, CarbonInterface $today): void
    {
        $query->where($query->qualifyColumn('status'), LessonStatus::Published)
            ->whereDate($query->qualifyColumn('scheduled_for'), '>=', $today->toDateString())
            ->orderBy($query->qualifyColumn('scheduled_for'));
    }
}
