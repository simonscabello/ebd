<?php

namespace App\Models;

use App\Enums\ClassroomRole;
use App\Enums\Gender;
use App\Notifications\ResetPasswordNotification;
use App\Support\ChurchCalendar;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $avatar_path
 * @property string|null $phone
 * @property Carbon|null $birth_date
 * @property Gender|null $gender
 * @property string|null $password
 * @property bool $is_admin
 * @property Carbon|null $email_verified_at
 */
#[Fillable(['name', 'email', 'phone', 'birth_date', 'gender', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Papéis do usuário por classe, carregados uma única vez por instância
     * para que policies não disparem uma consulta a cada verificação.
     *
     * @var array<int, ClassroomRole>|null
     */
    protected ?array $classroomRoles = null;

    /** @var list<int>|null */
    protected ?array $coordinatedClassroomIds = null;

    /** @var list<int>|null */
    protected ?array $administeredChurchIds = null;

    /** @var list<int>|null */
    protected ?array $administeredClassroomIds = null;

    /**
     * Espelha o default do banco para instâncias recém-criadas (ex.: após o cadastro).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
    ];

    protected static function booted(): void
    {
        // A foto sai do disco junto com a conta.
        static::deleted(function (User $user) {
            if ($user->avatar_path !== null) {
                Storage::disk('public')->delete($user->avatar_path);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'gender' => Gender::class,
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Igrejas que a pessoa administra (monta classes, nomeia professores).
     *
     * @return BelongsToMany<Church, $this>
     */
    public function administeredChurches(): BelongsToMany
    {
        return $this->belongsToMany(Church::class)->withTimestamps();
    }

    /**
     * Programas que a pessoa coordena (gerencia todas as turmas deles).
     *
     * @return BelongsToMany<Program, $this>
     */
    public function coordinatedPrograms(): BelongsToMany
    {
        return $this->belongsToMany(Program::class)->withTimestamps();
    }

    public function isCoordinatorOf(Program $program): bool
    {
        return $this->coordinatedPrograms()->whereKey($program->id)->exists();
    }

    public function isChurchAdminOf(Church $church): bool
    {
        return $this->administeredChurches()->whereKey($church->id)->exists();
    }

    /**
     * @return BelongsToMany<Classroom, $this, ClassroomMember>
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class)
            ->using(ClassroomMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<AccessLink, $this>
     */
    public function accessLinks(): HasMany
    {
        return $this->hasMany(AccessLink::class);
    }

    /**
     * @return HasMany<ReadingCheckin, $this>
     */
    public function readingCheckins(): HasMany
    {
        return $this->hasMany(ReadingCheckin::class);
    }

    /**
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * @return HasMany<UserBadge, $this>
     */
    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path !== null ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /**
     * Conta criada pelo professor, sem senha: entra só pelo link pessoal.
     */
    public function isManaged(): bool
    {
        return $this->password === null;
    }

    /**
     * Conta sem senha devolve '' em vez de null: o Laravel recusa o cookie
     * "lembrar de mim" quando a senha não é string, e o aluno do link era
     * deslogado quando a sessão expirava. Entrar por senha continua
     * impossível, porque Hash::check() com hash vazio é sempre falso.
     */
    public function getAuthPassword(): string
    {
        return $this->password ?? '';
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function roleIn(Classroom|int $classroom): ?ClassroomRole
    {
        $id = $classroom instanceof Classroom ? $classroom->id : $classroom;

        return $this->classroomRoles()[$id] ?? null;
    }

    public function isTeacherOf(Classroom|int $classroom): bool
    {
        return $this->roleIn($classroom) === ClassroomRole::Teacher;
    }

    public function isMemberOf(Classroom|int $classroom): bool
    {
        return $this->roleIn($classroom) !== null;
    }

    /**
     * Pode gerenciar conteúdo (séries, lições) da classe.
     */
    public function canManageClassroom(Classroom|int $classroom): bool
    {
        $id = $classroom instanceof Classroom ? $classroom->id : $classroom;

        return $this->isTeacherOf($classroom)
            || in_array($id, $this->coordinatedClassroomIds(), true)
            || in_array($id, $this->administeredClassroomIds(), true);
    }

    /**
     * Professores de ao menos uma classe e administradores acessam a área de gestão.
     */
    public function canAccessAdmin(): bool
    {
        return in_array(ClassroomRole::Teacher, $this->classroomRoles(), true)
            || $this->coordinatedClassroomIds() !== []
            || $this->administersAnyChurch();
    }

    /** Administra alguma igreja (o dono do sistema administra a dele). */
    public function administersAnyChurch(): bool
    {
        return $this->administeredChurchIds() !== [];
    }

    /**
     * @return list<int>
     */
    public function administeredChurchIds(): array
    {
        return $this->administeredChurchIds ??= array_values(DB::table('church_user')
            ->where('user_id', $this->id)
            ->orderBy('church_id')
            ->pluck('church_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /**
     * Turmas das igrejas que a pessoa administra.
     *
     * @return list<int>
     */
    public function administeredClassroomIds(): array
    {
        return $this->administeredClassroomIds ??= $this->administeredChurchIds() === [] ? [] : array_values(Classroom::query()
            ->whereIn('church_id', $this->administeredChurchIds())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /**
     * Gerencia todas as turmas de que a pessoa participa (e ela participa de
     * alguma). Condição para emitir link pessoal ou editar os dados dela: nada
     * de alguém de outra igreja mexendo numa conta que não acompanha.
     */
    public function managesAllClassroomsOf(User $person): bool
    {
        $theirs = $person->memberClassroomIds();

        return $theirs !== [] && array_diff($theirs, $this->manageableClassroomIds() ?? []) === [];
    }

    /**
     * A pessoa pode ser vinculada a esta igreja sem atravessar outra: não está
     * em turma, administração ou coordenação de outra igreja e não deixou
     * histórico (presença, leitura, anotação, dúvida, link) em outra. É o que
     * impede alguém de outra igreja de "capturar" uma conta e tomar o login.
     */
    public function isFreeFor(int $churchId): bool
    {
        $elsewhere = fn (array $ids) => array_diff($ids, [$churchId]) !== [];

        if ($elsewhere($this->memberChurchIds()) || $elsewhere($this->administeredChurchIds())) {
            return false;
        }

        $coordinated = DB::table('program_user')->join('programs', 'programs.id', '=', 'program_user.program_id')
            ->where('program_user.user_id', $this->id)->where('programs.church_id', '<>', $churchId)->exists();

        $classroomsElsewhere = Classroom::query()->where('church_id', '<>', $churchId)->select('id');
        $lessonsElsewhere = DB::table('lessons')->whereIn('classroom_id', $classroomsElsewhere)->select('id');

        return ! $coordinated
            && ! DB::table('attendances')->join('class_meetings', 'class_meetings.id', '=', 'attendances.class_meeting_id')
                ->where('attendances.user_id', $this->id)->whereIn('class_meetings.classroom_id', $classroomsElsewhere)->exists()
            && ! DB::table('reading_checkins')->where('user_id', $this->id)->whereIn('lesson_id', $lessonsElsewhere)->exists()
            && ! DB::table('lesson_notes')->where('user_id', $this->id)->whereIn('lesson_id', $lessonsElsewhere)->exists()
            && ! DB::table('study_questions')->where('user_id', $this->id)->whereIn('lesson_id', $lessonsElsewhere)->exists()
            && ! DB::table('access_links')->where('user_id', $this->id)->whereIn('classroom_id', $classroomsElsewhere)->exists();
    }

    /**
     * Igrejas das turmas de que a pessoa participa.
     *
     * @return list<int>
     */
    public function memberChurchIds(): array
    {
        return array_values(array_unique(Classroom::query()
            ->whereIn('id', $this->memberClassroomIds())
            ->pluck('church_id')
            ->map(fn ($id) => (int) $id)
            ->all()));
    }

    /**
     * A igreja de quem está usando: a que administra ou, senão, a da primeira
     * turma. Sem nenhuma, null.
     */
    public function currentChurch(): ?Church
    {
        $id = $this->administeredChurchIds()[0]
            ?? Classroom::query()->whereIn('id', $this->memberClassroomIds())->orderBy('id')->value('church_id');

        return $id === null ? null : Church::query()->whereKey($id)->first();
    }

    /**
     * Turmas dos programas que a pessoa coordena.
     *
     * @return list<int>
     */
    public function coordinatedClassroomIds(): array
    {
        return $this->coordinatedClassroomIds ??= array_values(Classroom::query()
            ->whereIn('program_id', DB::table('program_user')->where('user_id', $this->id)->select('program_id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /**
     * @return list<int>
     */
    public function memberClassroomIds(): array
    {
        return array_keys($this->classroomRoles());
    }

    /**
     * Aluno de ao menos uma classe.
     */
    public function isStudentAnywhere(): bool
    {
        return in_array(ClassroomRole::Student, $this->classroomRoles(), true);
    }

    /**
     * Cadastro que o aluno completa no primeiro acesso: e-mail e senha para
     * entrar, WhatsApp para o professor, nascimento e gênero para a classe.
     */
    public function hasCompleteProfile(): bool
    {
        return $this->password !== null
            && filled($this->email)
            && filled($this->phone)
            && $this->birth_date !== null
            && $this->gender !== null;
    }

    /**
     * Alunos (não professores nem administradores) com cadastro incompleto
     * passam pela tela "Completar cadastro" antes de usar o app.
     */
    public function needsProfileCompletion(): bool
    {
        return ! $this->hasCompleteProfile() && ! $this->canAccessAdmin() && $this->isStudentAnywhere();
    }

    /**
     * Idade hoje, no fuso da igreja.
     */
    public function age(): ?int
    {
        if ($this->birth_date === null) {
            return null;
        }

        $born = CarbonImmutable::parse($this->birth_date->toDateString(), ChurchCalendar::timezone());

        return (int) $born->diffInYears(ChurchCalendar::today());
    }

    /**
     * IDs das classes cujo conteúdo o usuário pode gerenciar.
     * Para administradores retorna null, que significa "todas".
     *
     * @return list<int>|null
     */
    public function manageableClassroomIds(): ?array
    {
        // Nunca "todas": cada um gerencia as turmas da própria igreja.
        return array_values(array_unique([
            ...array_keys(array_filter(
                $this->classroomRoles(),
                fn (ClassroomRole $role) => $role === ClassroomRole::Teacher,
            )),
            ...$this->coordinatedClassroomIds(),
            ...$this->administeredClassroomIds(),
        ]));
    }

    /**
     * @return array<int, ClassroomRole>
     */
    protected function classroomRoles(): array
    {
        return $this->classroomRoles ??= ClassroomMember::query()
            ->where('user_id', $this->id)
            ->pluck('role', 'classroom_id')
            ->map(fn (ClassroomRole|string $role) => $role instanceof ClassroomRole ? $role : ClassroomRole::from($role))
            ->all();
    }

    /**
     * Descarta o cache de papéis (útil após alterar vínculos na mesma requisição).
     */
    public function flushClassroomRoles(): static
    {
        $this->classroomRoles = null;
        $this->coordinatedClassroomIds = null;
        $this->administeredChurchIds = null;
        $this->administeredClassroomIds = null;

        return $this;
    }
}
