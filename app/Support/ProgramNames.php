<?php

namespace App\Support;

use App\Enums\MeetingStatus;
use Illuminate\Support\Facades\DB;

/**
 * Nome curto do programa de cada turma ("EBD", "Discipulado"), para textos
 * montados a partir de encontros sem a turma carregada. Uma consulta por
 * turma por requisição (registrado como scoped).
 */
final class ProgramNames
{
    /** @var array<int, string> */
    private array $short = [];

    public function shortFor(int $classroomId): string
    {
        return $this->short[$classroomId] ??= (string) (DB::table('classrooms')
            ->join('programs', 'programs.id', '=', 'classrooms.program_id')
            ->where('classrooms.id', $classroomId)
            ->value('programs.short_name') ?? 'EBD');
    }

    /** "Sem EBD", "Sem Discipulado"; os outros status como sempre. */
    public function statusLabel(MeetingStatus $status, int $classroomId): string
    {
        return $status === MeetingStatus::Cancelled ? "Sem {$this->shortFor($classroomId)}" : $status->label();
    }
}
