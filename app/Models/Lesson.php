<?php

namespace App\Models;

use App\Enums\LessonStatus;
use App\Enums\LessonVisibility;
use App\Support\Audio\StudyNarration;
use App\Support\ChurchCalendar;
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
 * @property string|null $audio_disk
 * @property string|null $audio_path
 * @property int|null $audio_duration
 * @property string|null $audio_source_hash
 * @property Carbon|null $audio_generated_at
 * @property string|null $audio_status
 * @property string|null $audio_error
 * @property Carbon|null $audio_requested_at
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
#[Hidden(['search_vector', 'blocks_text', 'audio_disk', 'audio_path', 'audio_source_hash', 'audio_error'])]
class Lesson extends Model
{
    /** Geração de áudio em andamento (ver GenerateLessonAudio). */
    public const AUDIO_GENERATING = 'generating';

    public const AUDIO_FAILED = 'failed';

    /** 2: sem citações de versículos; 3: narradores por parte; 4: roteiro para ouvir. */
    public const AUDIO_NARRATION_VERSION = 4;

    /** Depois disso, uma geração "em andamento" é dada como perdida (processo caiu). */
    public const AUDIO_GENERATION_TIMEOUT_MINUTES = 15;

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
            'audio_duration' => 'integer',
            'audio_generated_at' => 'datetime',
            'audio_requested_at' => 'datetime',
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
     * Dia do plano de leitura que cai hoje (1 = segunda ... 7 = domingo), na
     * semana que termina no próximo encontro da lição (hoje incluído). Sem
     * encontro por vir, ou antes da segunda-feira dessa semana, null.
     */
    public function readingWeekdayToday(): ?int
    {
        $today = ChurchCalendar::today();

        $next = $this->relationLoaded('meetings')
            ? $this->meetings->first(fn (ClassMeeting $m) => ! $m->isCancelled() && $m->held_on->toDateString() >= $today->toDateString())
            : $this->meetings()->active()->fromDate($today)->first();

        return ChurchCalendar::readingWeekday($next?->held_on, $today);
    }

    /**
     * "Lição 11 — É Necessário", como na revista.
     */
    public function displayTitle(): string
    {
        return $this->number ? "Lição {$this->number} — {$this->title}" : $this->title;
    }

    /** Texto que vira o áudio do estudo: o título e o campo de estudo. */
    public function narration(): StudyNarration
    {
        return StudyNarration::make($this->displayTitle(), $this->content);
    }

    /**
     * Impressão digital do texto narrado; muda quando o estudo é editado.
     * Mude AUDIO_NARRATION_VERSION quando a conversão do texto (StudyNarration)
     * mudar: os áudios já gerados ficam desatualizados e podem ser regenerados.
     */
    public function audioSourceHash(): string
    {
        return sha1(self::AUDIO_NARRATION_VERSION."\n".$this->displayTitle()."\n".$this->content);
    }

    public function hasAudio(): bool
    {
        return $this->audio_path !== null;
    }

    /** O estudo mudou depois que o áudio foi gerado. */
    public function isAudioStale(): bool
    {
        return $this->hasAudio() && $this->audio_source_hash !== $this->audioSourceHash();
    }

    public function isGeneratingAudio(): bool
    {
        return $this->audio_status === self::AUDIO_GENERATING
            && $this->audio_requested_at?->gt(now()->subMinutes(self::AUDIO_GENERATION_TIMEOUT_MINUTES));
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
