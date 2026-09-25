<?php

namespace App\Actions\Classrooms;

use App\Enums\ClassroomRole;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Passa um aluno de uma classe para outra (ex.: mudou de faixa etária).
 * Presenças e leituras antigas ficam como histórico da classe de origem; o
 * link pessoal ativo continua valendo e passa a apontar para a nova classe.
 */
class MoveStudentToClassroom
{
    public function handle(User $student, Classroom $from, Classroom $to): void
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['to_classroom' => 'A classe de destino é a mesma de origem.']);
        }

        if ($student->roleIn($from) !== ClassroomRole::Student) {
            throw ValidationException::withMessages(['student' => "{$student->name} não é aluno(a) da classe {$from->name}."]);
        }

        if ($student->isMemberOf($to)) {
            throw ValidationException::withMessages(['to_classroom' => "{$student->name} já faz parte da classe {$to->name}."]);
        }

        DB::transaction(function () use ($student, $from, $to) {
            $from->members()->detach($student->id);
            $to->members()->attach($student->id, ['role' => ClassroomRole::Student->value]);
            $student->accessLinks()->active()->update(['classroom_id' => $to->id]);
        });

        $student->flushClassroomRoles();
    }
}
