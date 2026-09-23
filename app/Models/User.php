<?php

namespace App\Models;

use App\Enums\ClassroomRole;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $password
 * @property bool $is_admin
 * @property Carbon|null $email_verified_at
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Papéis do usuário por classe, carregados uma única vez por instância
     * para que policies não disparem uma consulta a cada verificação.
     *
     * @var array<int, ClassroomRole>|null
     */
    protected ?array $classroomRoles = null;

    /**
     * Espelha o default do banco para instâncias recém-criadas (ex.: após o cadastro).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
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
     * @return HasMany<UserBadge, $this>
     */
    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    /**
     * Conta criada pelo professor, sem senha: entra só pelo link pessoal.
     */
    public function isManaged(): bool
    {
        return $this->password === null;
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
        return $this->isAdmin() || $this->isTeacherOf($classroom);
    }

    /**
     * Professores de ao menos uma classe e administradores acessam a área de gestão.
     */
    public function canAccessAdmin(): bool
    {
        return $this->isAdmin() || in_array(ClassroomRole::Teacher, $this->classroomRoles(), true);
    }

    /**
     * @return list<int>
     */
    public function memberClassroomIds(): array
    {
        return array_keys($this->classroomRoles());
    }

    /**
     * IDs das classes cujo conteúdo o usuário pode gerenciar.
     * Para administradores retorna null, que significa "todas".
     *
     * @return list<int>|null
     */
    public function manageableClassroomIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        return array_keys(array_filter(
            $this->classroomRoles(),
            fn (ClassroomRole $role) => $role === ClassroomRole::Teacher,
        ));
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

        return $this;
    }
}
