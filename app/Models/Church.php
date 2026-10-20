<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ChurchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Uma igreja (ou grupo) que usa o app: tem as suas classes e os seus
 * administradores. Professor e aluno continuam definidos por classe.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property bool $helper_enabled "Tirar dúvida" liberado pelo dono
 * @property int $helper_daily_limit dúvidas por pessoa por dia
 * @property bool $audio_enabled áudio do estudo liberado pelo dono
 * @property int $audio_monthly_limit áudios gerados por mês na igreja
 * @property bool $shows_bible_text texto da NAA (só onde há licença)
 * @property bool $is_official a biblioteca oficial do dono (ver OfficialLibrary)
 */
#[Fillable(['name', 'slug', 'timezone', 'helper_enabled', 'helper_daily_limit', 'audio_enabled', 'audio_monthly_limit', 'shows_bible_text'])]
class Church extends Model
{
    /** @use HasFactory<ChurchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'helper_enabled' => 'boolean',
            'helper_daily_limit' => 'integer',
            'audio_enabled' => 'boolean',
            'audio_monthly_limit' => 'integer',
            'shows_bible_text' => 'boolean',
            'is_official' => 'boolean',
        ];
    }

    /**
     * Configurações da igreja do dono (a da configuração): IA, áudio e texto
     * bíblico ligados, no limite diário do .env.
     *
     * @return array<string, bool|int>
     */
    public static function ownerSettings(): array
    {
        return [
            'helper_enabled' => true,
            'helper_daily_limit' => max(1, (int) config('ebd.helper.daily_limit')),
            'audio_enabled' => true,
            'audio_monthly_limit' => 30,
            'shows_bible_text' => true,
        ];
    }

    /**
     * @return HasMany<AiUsage, $this>
     */
    public function aiUsages(): HasMany
    {
        return $this->hasMany(AiUsage::class);
    }

    /**
     * @return HasMany<Classroom, $this>
     */
    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    /**
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class)->orderBy('position')->orderBy('id');
    }

    /**
     * O programa onde entram as turmas novas quando a igreja tem um só (ou o
     * primeiro). Igreja sem programa ganha a Escola Bíblica Dominical.
     */
    public function defaultProgram(): Program
    {
        return $this->programs()->first()
            ?? $this->programs()->create(['name' => 'Escola Bíblica Dominical', 'short_name' => 'EBD', 'slug' => 'ebd']);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * A igreja mais antiga, sem criar nada. Enquanto só existe a igreja que
     * veio da configuração, é ela; quando houver cadastro de igrejas, a igreja
     * passa a vir de quem está logado ou da classe.
     */
    public static function primary(): ?self
    {
        // A biblioteca oficial nunca é a igreja principal.
        return self::query()->where('is_official', false)->orderBy('id')->first();
    }

    /**
     * Igrejas cujo conteúdo a pessoa vê nas listas (início, biblioteca): as
     * das turmas dela, as que administra e as dos programas que coordena.
     * Quem entrou e ainda não tem igreja não vê nenhuma (nada de cair na
     * primeira, que é de outra pessoa); o visitante vê só a biblioteca
     * oficial (os estudos de exemplo do site).
     *
     * @return list<int>
     */
    public static function idsVisibleTo(?User $user): array
    {
        // Lição pública de qualquer igreja continua abrindo pelo link (LessonPolicy).
        if ($user === null) {
            return array_values(self::query()->where('is_official', true)->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        return array_values(array_unique([
            ...$user->memberChurchIds(),
            ...$user->administeredChurchIds(),
            ...Classroom::query()->whereIn('id', $user->coordinatedClassroomIds())->pluck('church_id')->map(fn ($id) => (int) $id)->all(),
        ]));
    }

    /**
     * Igrejas em que o relógio delas está nos primeiros 15 minutos de $hour:
     * os lembretes rodam a cada 15 minutos e cada igreja recebe uma vez, na
     * hora local dela (inclusive fusos de meia hora, como a Índia).
     *
     * @return Collection<int, self>
     */
    public static function atLocalHour(int $hour): Collection
    {
        return self::query()->orderBy('id')->get()
            ->filter(function (self $church) use ($hour) {
                $now = CarbonImmutable::now($church->timezone);

                return $now->hour === $hour && $now->minute < 15;
            })
            ->values();
    }

    /**
     * Nome mostrado no app e nos e-mails. Quem entrou mostra o da igreja
     * dele; sem igreja, o da configuração (nunca o de outra igreja). O
     * visitante continua vendo o da primeira igreja.
     */
    public static function displayName(?User $user = null): string
    {
        $church = $user !== null ? $user->currentChurch() : self::primary();

        return $church->name ?? (string) config('ebd.church_name');
    }

    /**
     * Igual a primary(), mas cria a igreja da configuração se ainda não
     * existir nenhuma (instalação nova). Só para caminhos de escrita.
     */
    public static function primaryOrCreate(): self
    {
        if ($church = self::primary()) {
            return $church;
        }

        $name = (string) config('ebd.church_name');

        return self::query()->create([
            'name' => $name,
            'slug' => Str::slug($name) ?: 'igreja',
            'timezone' => (string) config('ebd.timezone'),
            ...self::ownerSettings(),
        ]);
    }
}
