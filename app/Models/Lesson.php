<?php

namespace App\Models;

use App\Enums\LessonStatus;
use App\Enums\LessonVisibility;
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
 * @property int|null $number
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property Carbon|null $scheduled_for
 * @property string|null $bible_reference
 * @property string|null $key_verse
 * @property string|null $goal
 * @property string|null $content
 * @property string|null $blocks_text
 * @property LessonStatus $status
 * @property LessonVisibility $visibility
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property-read Classroom $classroom
 * @property-read Series|null $series
 */
#[Fillable([
    'number',
    'title',
    'summary',
    'bible_reference',
    'key_verse',
    'goal',
    'content',
    'visibility',
])]
#[Hidden(['search_vector', 'blocks_text'])]
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
            'number' => 'integer',
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
     * @return HasMany<LessonReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(LessonReading::class)->ordered();
    }

    /**
     * @return HasMany<LessonBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(LessonBlock::class)->ordered();
    }

    /**
     * Encontros em que a lição é (ou foi) estudada, em ordem de data.
     *
     * @return HasMany<ClassMeeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(ClassMeeting::class)->chronological();
    }

    /**
     * "Lição 11 — É Necessário", como na revista.
     */
    public function displayTitle(): string
    {
        return $this->number ? "Lição {$this->number} — {$this->title}" : $this->title;
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
}
