<?php

namespace App\Mcp\Concerns;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Encontra classes, lições, encontros e alunos a partir do que o agente
 * conhece numa conversa (slug, nome, data, id), sempre dentro das classes
 * que a pessoa logada gerencia. Quando não acha ou acha mais de um, o erro
 * lista as opções para o agente confirmar com a pessoa.
 */
trait ResolvesEbdModels
{
    /**
     * @return Builder<Classroom>
     */
    protected function manageableClassrooms(User $user): Builder
    {
        $ids = $user->manageableClassroomIds();

        return Classroom::query()
            ->when($ids !== null, fn (Builder $q) => $q->whereKey($ids))
            ->ordered();
    }

    protected function resolveClassroom(User $user, mixed $reference, string $field = 'classroom'): Classroom
    {
        $reference = trim((string) $reference);
        $query = $this->manageableClassrooms($user);

        $classroom = ctype_digit($reference)
            ? (clone $query)->whereKey((int) $reference)->first()
            : (clone $query)->where('slug', $reference)->first()
                ?? (clone $query)->whereRaw('unaccent(name) ILIKE unaccent(?)', [$reference])->first();

        if ($classroom === null) {
            $options = $query->get(['name', 'slug'])->map(fn (Classroom $c) => "{$c->slug} ({$c->name})")->implode(', ');

            throw ValidationException::withMessages([
                $field => "Classe \"{$reference}\" não encontrada entre as que você gerencia. Use uma destas: {$options}.",
            ]);
        }

        return $classroom;
    }

    protected function resolveLesson(User $user, mixed $id, string $field = 'lesson_id'): Lesson
    {
        $ids = $user->manageableClassroomIds();

        $lesson = Lesson::query()
            ->when($ids !== null, fn (Builder $q) => $q->whereIn('classroom_id', $ids))
            ->whereKey((int) $id)
            ->first();

        if ($lesson === null) {
            throw ValidationException::withMessages([
                $field => "Lição {$id} não encontrada nas classes que você gerencia. Use list_lessons para achar o id.",
            ]);
        }

        return $lesson;
    }

    protected function resolveSeries(Classroom $classroom, mixed $reference, string $field = 'series'): Series
    {
        $reference = trim((string) $reference);
        $query = Series::query()->whereBelongsTo($classroom);

        $series = ctype_digit($reference)
            ? (clone $query)->whereKey((int) $reference)->first()
            : (clone $query)->where('slug', $reference)->first();

        if ($series === null) {
            $options = $query->orderByDesc('starts_on')->get(['id', 'slug', 'title'])
                ->map(fn (Series $s) => "{$s->slug} ({$s->title})")->implode(', ');

            throw ValidationException::withMessages([
                $field => "Série \"{$reference}\" não encontrada na classe {$classroom->name}.".($options !== '' ? " Séries da classe: {$options}." : ''),
            ]);
        }

        return $series;
    }

    /**
     * Encontro por id ou pela data (AAAA-MM-DD) dentro da classe.
     */
    protected function resolveMeeting(User $user, mixed $reference, ?Classroom $classroom = null, string $field = 'meeting'): ClassMeeting
    {
        $reference = trim((string) $reference);
        $ids = $user->manageableClassroomIds();

        $query = ClassMeeting::query()
            ->when($ids !== null, fn (Builder $q) => $q->whereIn('classroom_id', $ids))
            ->when($classroom !== null, fn (Builder $q) => $q->whereBelongsTo($classroom));

        if (ctype_digit($reference)) {
            $meeting = $query->whereKey((int) $reference)->first();
        } elseif ($classroom !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $reference) === 1) {
            $meeting = $query->whereDate('held_on', $reference)->first();
        } else {
            throw ValidationException::withMessages([
                $field => 'Informe o id do encontro ou a data (AAAA-MM-DD) junto com a classe.',
            ]);
        }

        if ($meeting === null) {
            throw ValidationException::withMessages([
                $field => "Encontro \"{$reference}\" não encontrado. Use list_meetings para ver a agenda.",
            ]);
        }

        return $meeting;
    }

    /**
     * Um aluno da classe por id ou nome.
     */
    protected function resolveStudent(Classroom $classroom, mixed $reference, string $field = 'student'): User
    {
        return $this->resolveStudents($classroom, [$reference], $field)->first();
    }

    /**
     * Alunos da classe por id ou nome (parte do nome, sem diferenciar acentos).
     * Um nome que casa com mais de um aluno é recusado com a lista de candidatos.
     *
     * @param  list<int|string>  $references
     * @return Collection<int, User>
     */
    protected function resolveStudents(Classroom $classroom, array $references, string $field = 'students'): Collection
    {
        $students = $classroom->students()->orderBy('name')->get();
        $found = collect();
        $problems = [];

        foreach ($references as $reference) {
            $reference = trim((string) $reference);

            if ($reference === '') {
                continue;
            }

            if (ctype_digit($reference)) {
                $match = $students->where('id', (int) $reference);
            } else {
                $needle = $this->normalizeName($reference);
                $match = $students->filter(fn (User $s) => $this->normalizeName($s->name) === $needle);

                if ($match->isEmpty()) {
                    $match = $students->filter(fn (User $s) => str_contains($this->normalizeName($s->name), $needle));
                }
            }

            if ($match->count() === 1) {
                $found->put($match->first()->id, $match->first());
            } elseif ($match->isEmpty()) {
                $problems[] = "\"{$reference}\" não é aluno(a) da classe {$classroom->name}";
            } else {
                $problems[] = "\"{$reference}\" é ambíguo: ".$match->map(fn (User $s) => "{$s->id} — {$s->name}")->implode('; ');
            }
        }

        if ($problems !== []) {
            throw ValidationException::withMessages([
                $field => implode('. ', $problems).'. Confirme com a pessoa e use o id do aluno se precisar (list_students).',
            ]);
        }

        return $found->values();
    }

    protected function parseDate(mixed $value, string $field): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw ValidationException::withMessages([$field => "Data inválida em {$field}: use AAAA-MM-DD."]);
        }

        return CarbonImmutable::parse($value);
    }

    private function normalizeName(string $name): string
    {
        return Str::squish(Str::lower(Str::ascii($name)));
    }
}
