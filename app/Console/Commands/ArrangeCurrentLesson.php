<?php

namespace App\Console\Commands;

use App\Actions\Meetings\SyncUpcomingMeeting;
use App\Enums\MeetingStatus;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Passagem da agenda planejada para a lição atual: em cada classe, o próximo
 * encontro em aberto fica (com a lição atual) e os encontros planejados mais
 * adiante, vazios de tudo, são apagados. Os que têm anotação ou visitantes
 * ficam, só sem lição.
 *
 * Sem --force só mostra o que faria. Com --force, classe por classe e com a
 * classe travada, relê os encontros, grava a cópia dos que serão apagados em
 * storage (backups/) e só então apaga. Rodar de novo não muda nada.
 */
#[Signature('ebd:licao-atual {--force : Aplica (sem ele, só mostra)}')]
#[Description('Arruma os encontros planejados à frente para seguirem a lição atual de cada classe')]
class ArrangeCurrentLesson extends Command
{
    public function handle(SyncUpcomingMeeting $sync): int
    {
        $apply = (bool) $this->option('force');
        $stamp = now()->format('Ymd-His');
        $rows = [];
        $removed = 0;

        $classrooms = Classroom::query()->with('currentLesson')->orderBy('church_id')->orderBy('position')->get()
            ->filter(fn (Classroom $c) => $c->holdsMeetings());

        foreach ($classrooms as $classroom) {
            ChurchCalendar::using($classroom->church, function () use ($classroom, $apply, $sync, $stamp, &$rows, &$removed) {
                $later = $this->openMeetings($classroom)->skip(1);
                $empty = $this->empty($later);

                $rows[] = [
                    $classroom->church->name,
                    $classroom->name,
                    $classroom->currentLesson?->displayTitle() ?? '(sem lição atual)',
                    $empty->count(),
                    $later->count() - $empty->count(),
                ];

                if (! $apply) {
                    return;
                }

                DB::transaction(function () use ($classroom, $sync, $stamp, &$removed) {
                    Classroom::query()->lockForUpdate()->findOrFail($classroom->id);
                    // Relidos com trava: anotação salva ou "sem EBD" marcado agora tira o encontro da lista.
                    $empty = $this->empty($this->openMeetings($classroom, lock: true)->skip(1));

                    if ($empty->isNotEmpty()) {
                        $path = "backups/licao-atual-{$stamp}-{$classroom->id}.json";
                        $saved = Storage::disk('local')->put($path, (string) json_encode(
                            $empty->map(fn (ClassMeeting $m) => $m->getAttributes())->values()->all(),
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                        ));

                        if (! $saved) {
                            throw new RuntimeException("Não deu para gravar a cópia em {$path}: nada foi apagado nesta classe.");
                        }

                        $this->components->info("Cópia de {$classroom->name}: storage/app/private/{$path}");
                        ClassMeeting::query()->whereKey($empty->modelKeys())->delete();
                        $removed += $empty->count();
                    }

                    $sync->handle($classroom);
                });
            });
        }

        $this->table(['Igreja', 'Classe', 'Lição atual', 'Encontros vazios a apagar', 'Só perdem a lição'], $rows);

        if (! $apply) {
            $this->components->info('Nada foi alterado. Rode com --force para aplicar.');

            return self::SUCCESS;
        }

        $this->components->info("{$removed} encontro(s) apagado(s).");

        return self::SUCCESS;
    }

    /**
     * Sem nada do professor: sem anotação, sem visitantes, sem presença.
     *
     * @param  Collection<int, ClassMeeting>  $meetings
     * @return Collection<int, ClassMeeting>
     */
    private function empty(Collection $meetings): Collection
    {
        return $meetings->filter(fn (ClassMeeting $m) => blank($m->notes) && $m->visitors_count === 0 && ! $m->attendances()->exists())->values();
    }

    /**
     * Mesma regra de SyncUpcomingMeeting: planejado, de hoje em diante, sem
     * chamada, não encerrado e sem título.
     *
     * @return Collection<int, ClassMeeting>
     */
    private function openMeetings(Classroom $classroom, bool $lock = false): Collection
    {
        return ClassMeeting::query()
            ->whereBelongsTo($classroom)
            ->where('status', MeetingStatus::Planned)
            ->whereDate('held_on', '>=', ChurchCalendar::todayString())
            ->whereNull('attendance_taken_at')
            ->whereNull('finished_at')
            ->whereNull('title')
            ->chronological()
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get();
    }
}
